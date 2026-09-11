#!/usr/bin/env -S npx tsx
/**
 * Two-tier launch test suite. Pure: no chain access, no SOL, no mint, no SDK.
 * Checks prepared descriptors and a specification model of the INDEPENDENT per-phase
 * cap design (5 early + 5 public); does NOT prove the Candy Guard program itself (that
 * needs a validator — see the retained onchain_program_enforcement_untested blocker).
 * SDK-backed PDA proofs live in test_pda.ts.
 */
import {
  loadLaunchConfig, assertConfig, EARLY_LAMPORTS, PUBLIC_LAMPORTS, TREASURY,
  CANDY_MACHINE_SUPPLY, ID_FIRST, ID_LAST, EARLY_MINT_LIMIT_ID, PUBLIC_MINT_LIMIT_ID,
  MAX_PER_WALLET, type LaunchConfig, type GroupLabel,
} from "./launch_config.js";
import {
  buildGuardLayout, assertNoCheapDefaultPath, assertIndependentLimitLayout, getGroup,
  effectiveMintLimit, resolveGuardSet, type CandyGuardLayout,
} from "./guard_groups.js";
import { computeBlockers, deploymentPermitted, isEarlyAllowlistBlocking, type LaunchReadiness } from "./launch_blockers.js";
import { executeMint, buildBuyerQuote, buildMintArgs, mintLimitIdFor, preflight, reconcilePrice, type OnChainGroup, type OnChainGuardConfig } from "./mint_helper.js";
import { buildAllowlist, isValidSolanaPubkey } from "./allowlist_builder.js";

let pass = 0, fail = 0;
function test(name: string, fn: () => void | Promise<void>): Promise<void> {
  return Promise.resolve()
    .then(fn)
    .then(() => { console.log(`PASS  ${name}`); pass++; })
    .catch((e) => { console.log(`FAIL  ${name} — ${e instanceof Error ? e.message : e}`); fail++; });
}
function assert(cond: boolean, msg: string): void { if (!cond) throw new Error(msg); }
function aborts(fn: () => unknown, re: RegExp): void {
  try { fn(); throw new Error("expected ABORT, got none"); }
  catch (e) { const m = e instanceof Error ? e.message : String(e); if (!re.test(m)) throw new Error(`wrong error: ${m}`); }
}

const cfg: LaunchConfig = loadLaunchConfig();
const layout: CandyGuardLayout = buildGuardLayout(cfg);
// On-chain view: each group carries its OWN mintLimit; the default is empty.
const onchain = (label: "early" | "public"): OnChainGroup => {
  const g = getGroup(layout, label);
  return { label, solPaymentLamports: BigInt(g.guards.solPayment!.lamports), solPaymentDestination: g.guards.solPayment!.destination, mintLimit: g.guards.mintLimit ?? null };
};
const onchainCfg = (): OnChainGuardConfig => ({ defaultMintLimit: layout.default.mintLimit ?? null, groups: [onchain("early"), onchain("public")] });

const BLOCKING_READINESS: LaunchReadiness = {
  fullRenderApproved: true, finalMetadataComplete: true, permanentUrisAvailable: true,
  costQuoteReviewed: true, mainnetDeployAuthorized: true,
}; // Artifact flags cannot clear the permanent integration/verification blockers.

/** Independent-counter model: ONE counter per mintLimit id; each phase increments its own. */
function independentCounterModel() {
  const counters = new Map<number, number>();
  return {
    counters,
    mint(phase: GroupLabel): boolean {
      const ml = effectiveMintLimit(layout, phase); // the group's own limiter (early id 1 / public id 2)
      const n = counters.get(ml.id) ?? 0;
      if (n >= ml.limit) return false;
      counters.set(ml.id, n + 1);
      return true;
    },
  };
}

async function run() {
  await test("1. early group exists", () => { assert(!!getGroup(layout, "early"), "no early group"); });
  await test("2. public group exists", () => { assert(!!getGroup(layout, "public"), "no public group"); });

  await test("3. early == 100000000 lamports", () => {
    assert(BigInt(getGroup(layout, "early").guards.solPayment!.lamports) === 100_000_000n, "early lamports wrong");
    assert(EARLY_LAMPORTS === 100_000_000n, "EARLY_LAMPORTS constant wrong");
  });
  await test("4. public == 150000000 lamports", () => {
    assert(BigInt(getGroup(layout, "public").guards.solPayment!.lamports) === 150_000_000n, "public lamports wrong");
    assert(PUBLIC_LAMPORTS === 150_000_000n, "PUBLIC_LAMPORTS constant wrong");
  });
  await test("5. both groups pay treasury", () => {
    assert(getGroup(layout, "early").guards.solPayment!.destination === TREASURY, "early dest != treasury");
    assert(getGroup(layout, "public").guards.solPayment!.destination === TREASURY, "public dest != treasury");
  });

  // Each group defines its OWN mintLimit, limit 5, with a distinct id.
  await test("6. early max per wallet == 5 (own mintLimit id 1)", () => {
    const ml = getGroup(layout, "early").guards.mintLimit;
    assert(!!ml && ml.limit === 5 && ml.id === EARLY_MINT_LIMIT_ID, "early own mintLimit not id 1/limit 5");
    assert(effectiveMintLimit(layout, "early").limit === 5, "early effective cap != 5");
  });
  await test("7. public max per wallet == 5 (own mintLimit id 2)", () => {
    const ml = getGroup(layout, "public").guards.mintLimit;
    assert(!!ml && ml.limit === 5 && ml.id === PUBLIC_MINT_LIMIT_ID, "public own mintLimit not id 2/limit 5");
    assert(effectiveMintLimit(layout, "public").limit === 5, "public effective cap != 5");
  });

  await test("8. null launch dates block deployment", () => {
    assert(cfg.pricing.early.start_date === null && cfg.pricing.public.start_date === null, "dates not null");
    const b = computeBlockers(cfg, BLOCKING_READINESS).map((x) => x.code);
    assert(b.includes("early_start_date_null"), "early null date not blocking");
    assert(b.includes("public_start_date_null"), "public null date not blocking");
    assert(!deploymentPermitted(cfg, BLOCKING_READINESS), "deployment permitted despite null dates");
  });
  await test("9. pending allowlist blocks early activation", () => {
    assert(isEarlyAllowlistBlocking(cfg), "pending allowlist not blocking");
    assert(!getGroup(layout, "early").guards.allowList, "early allowList set with pending source");
    const b = computeBlockers(cfg, BLOCKING_READINESS).map((x) => x.code);
    assert(b.includes("allowlist_required_but_pending"), "allowlist blocker missing");
  });

  await test("10. public cannot use early price", () => {
    const pub = BigInt(getGroup(layout, "public").guards.solPayment!.lamports);
    const early = BigInt(getGroup(layout, "early").guards.solPayment!.lamports);
    assert(pub !== early, "public price collides with early");
    assert(pub === PUBLIC_LAMPORTS && early === EARLY_LAMPORTS, "prices not pinned to config lamports");
    const r = preflight(onchainCfg(), "public", TREASURY).reconciliation;
    assert(r.ok && r.expected === PUBLIC_LAMPORTS, "public preflight not pinned to public price");
    aborts(() => assertConfig({ ...cfg, pricing: { ...cfg.pricing, public: { ...cfg.pricing.public, price_lamports: 100_000_000, price_sol: 0.10 } } } as LaunchConfig), /must be strictly greater|must differ|public price 100000000/);
  });

  // The default set is EMPTY: any guard there is inherited by every group.
  await test("11. no unrestricted default mint route (empty default)", () => {
    assertNoCheapDefaultPath(layout, TREASURY); // real layout is safe
    assert(!layout.default.mintLimit && !layout.default.solPayment && !layout.default.addressGate,
      "default must be empty (no mintLimit, solPayment, or addressGate)");
    // default carrying a solPayment => inherited ungrouped price => must throw
    aborts(() => assertNoCheapDefaultPath({ default: { solPayment: { lamports: "1", destination: TREASURY } }, groups: layout.groups }, TREASURY), /solPayment/);
    // default carrying an addressGate => inherited by groups, blocks buyers => must throw
    aborts(() => assertNoCheapDefaultPath({ default: { addressGate: { address: TREASURY } }, groups: layout.groups }, TREASURY), /addressGate/);
    // default carrying a mintLimit => inherited, collapses to one shared counter => must throw
    aborts(() => assertNoCheapDefaultPath({ default: { mintLimit: { id: 1, limit: 5 } }, groups: layout.groups }, TREASURY), /mintLimit/);
  });

  await test("12. buyer remains payer", () => {
    const q = buildBuyerQuote("public", onchain("public"));
    assert(q.payer === "connected_buyer", "payer not buyer");
    assert(cfg.buyer_pays.transaction_payer === "connected_buyer", "config payer not buyer");
    assert(cfg.buyer_pays.core_asset_rent_payer === "connected_buyer", "config rent payer not buyer");
    assert(cfg.buyer_pays.network_fee_payer === "connected_buyer", "config fee payer not buyer");
  });
  await test("13. buyer remains minter", () => {
    const q = buildBuyerQuote("early", onchain("early"));
    assert(q.minter === "connected_buyer", "minter not buyer");
    assert(cfg.buyer_pays.minter === "connected_buyer", "config minter not buyer");
  });
  await test("14. creator does not sponsor buyer costs", () => {
    assert(cfg.buyer_pays.creator_sponsors_buyer_costs === false, "creator sponsors buyer costs");
    assert(getGroup(layout, "public").guards.solPayment!.destination === TREASURY, "treasury not payment destination");
    assert(buildBuyerQuote("public", onchain("public")).payer === "connected_buyer", "payer is not buyer");
  });
  await test("15. GHOST//0001 excluded", () => {
    assert(cfg.supply.excluded_ghost_0001 === true, "0001 not excluded");
    assert(ID_FIRST === 2, "id range starts at 0001");
  });
  await test("16. candy machine supply == 3332", () => {
    assert(CANDY_MACHINE_SUPPLY === 3332, "supply constant wrong");
    assert(cfg.supply.candy_machine === 3332, "config supply wrong");
    assert(cfg.supply.reserved_pool == null, "unexpected reserved pool");
    aborts(() => assertConfig({ ...cfg, supply: { ...cfg.supply, candy_machine: 3333 } } as LaunchConfig), /candy machine supply/);
  });
  await test("17. IDs 0002-3333, no extra supply", () => {
    assert(ID_FIRST === 2 && ID_LAST === 3333, "id range wrong");
    assert(ID_LAST - ID_FIRST + 1 === CANDY_MACHINE_SUPPLY, "id width != supply");
    aborts(() => assertConfig({ ...cfg, supply: { ...cfg.supply, id_range: { ...cfg.supply.id_range, last: 3334 } } } as LaunchConfig), /id range|width/);
  });
  await test("18. dry run cannot broadcast (no mainnet tx)", () => {
    assert(!deploymentPermitted(cfg, BLOCKING_READINESS), "deployment permitted in current state");
    assert(computeBlockers(cfg, BLOCKING_READINESS).length >= 3, "expected >=3 residual blockers");
    const drift = reconcilePrice(140_000_000n, PUBLIC_LAMPORTS);
    assert(!drift.ok, "price drift not refused");
  });

  // --- independent-counter model (the reconciled architecture) ---
  await test("IND-1. each group defines its OWN mintLimit; ids distinct (early 1, public 2)", () => {
    const e = getGroup(layout, "early").guards.mintLimit!;
    const p = getGroup(layout, "public").guards.mintLimit!;
    assert(e.id === EARLY_MINT_LIMIT_ID && p.id === PUBLIC_MINT_LIMIT_ID, "group ids not 1/2");
    assert(e.id !== p.id, "early/public ids collide");
    assert(cfg.candy_guard.mint_limit_defined_in === "each_group", "config: mintLimit not defined per group");
    assert(cfg.candy_guard.groups_inherit_default_mint_limit === false, "config: groups still inherit default");
    assertIndependentLimitLayout(layout); // full structural proof
  });
  await test("IND-2. each group resolves to its own limiter (not a shared one)", () => {
    assert(resolveGuardSet(layout, "early").mintLimit!.id === EARLY_MINT_LIMIT_ID, "early resolves wrong id");
    assert(resolveGuardSet(layout, "public").mintLimit!.id === PUBLIC_MINT_LIMIT_ID, "public resolves wrong id");
  });
  await test("IND-3. a group sharing the other's id is rejected (would collapse to one counter)", () => {
    const bad = structuredClone(layout);
    bad.groups[1].guards.mintLimit = { id: EARLY_MINT_LIMIT_ID, limit: 5 }; // public grabs early's id
    aborts(() => assertIndependentLimitLayout(bad), /!= expected|shared across groups/);
  });
  await test("IND-4. a group that raises its cap is rejected (cap drift)", () => {
    const bad = structuredClone(layout);
    bad.groups[0].guards.mintLimit = { id: EARLY_MINT_LIMIT_ID, limit: 6 };
    aborts(() => assertIndependentLimitLayout(bad), /cap drift|limit 6/);
  });

  // --- group-bypass / no default route ---
  await test("BYPASS-1. no default-group mint bypass: default has no priced/free mintable route", () => {
    assert(!layout.default.solPayment, "default must not carry a solPayment (would be an ungrouped price)");
    assert(!layout.default.mintLimit, "default must not carry a mintLimit (would be inherited/shared)");
    for (const g of layout.groups) assert(!!g.guards.solPayment, `group ${g.label} missing solPayment`);
    const codes = computeBlockers(cfg, BLOCKING_READINESS).map(b => b.code);
    assert(codes.includes("onchain_program_enforcement_untested"), "program-enforcement blocker (incl. no-default-bypass) missing");
  });

  // --- phase-transition wallets (independent per-phase counters) ---
  await test("IPT-A. 5 early pass, 6th early FAILS", () => {
    const m = independentCounterModel();
    for (let i = 0; i < 5; i++) assert(m.mint("early"), "early refused within cap");
    assert(m.mint("early") === false, "6th early not rejected");
  });
  await test("IPT-B. 5 public pass, 6th public FAILS", () => {
    const m = independentCounterModel();
    for (let i = 0; i < 5; i++) assert(m.mint("public"), "public refused within cap");
    assert(m.mint("public") === false, "6th public not rejected");
  });
  await test("IPT-C. 5 early + 5 public all pass (10 total), 11th of either FAILS; two counters", () => {
    const m = independentCounterModel();
    for (let i = 0; i < 5; i++) assert(m.mint("early"), "early refused within cap");
    for (let i = 0; i < 5; i++) assert(m.mint("public"), "public refused within cap");
    assert(m.mint("early") === false, "extra early not rejected");
    assert(m.mint("public") === false, "extra public not rejected");
    assert(m.counters.size === 2, "phases did not use two distinct counters");
  });
  await test("IPT-D. early cap does not consume public allowance (independence)", () => {
    const m = independentCounterModel();
    for (let i = 0; i < 5; i++) assert(m.mint("early"), "early refused within cap");
    assert(m.mint("early") === false, "6th early not rejected");
    // public untouched: full 5 still available
    for (let i = 0; i < 5; i++) assert(m.mint("public"), "public wrongly limited by early counter");
    assert(m.mint("public") === false, "6th public not rejected");
  });
  await test("IPT-E. every 6-length early/public interleaving admits min(#early,5)+min(#public,5)", () => {
    for (let bits = 0; bits < 64; bits++) {
      const m = independentCounterModel();
      let e = 0, p = 0, accepted = 0;
      for (let i = 0; i < 6; i++) {
        const phase: GroupLabel = (bits & (1 << i)) ? "early" : "public";
        if (phase === "early") e++; else p++;
        if (m.mint(phase)) accepted++;
      }
      assert(accepted === Math.min(e, 5) + Math.min(p, 5), "interleaving admitted wrong count");
    }
  });

  // --- mint args the frontend sends ---
  await test("MA-1. buildMintArgs sends the phase's own mintLimit id (early 1, public 2)", () => {
    assert(buildMintArgs("early").mintLimit.id === EARLY_MINT_LIMIT_ID, "early mintArgs id != 1");
    assert(buildMintArgs("public").mintLimit.id === PUBLIC_MINT_LIMIT_ID, "public mintArgs id != 2");
    assert(mintLimitIdFor("early") !== mintLimitIdFor("public"), "phase ids collide");
    const withProof = buildMintArgs("early", ["aa", "bb"]);
    assert(!!withProof.allowListProof && withProof.allowListProof.length === 2, "early allowlist proof not carried");
  });
  await test("MA-2. on-chain id collision rejected before mint (independence enforced)", () => {
    const bad = onchainCfg();
    bad.groups[1].mintLimit = { id: EARLY_MINT_LIMIT_ID, limit: 5 }; // public collides onto early's counter
    aborts(() => preflight(bad, "public", TREASURY), /mintLimit mismatch|share a mintLimit id/);
  });

  // --- supporting negative cases ---
  await test("A. invalid Solana pubkey rejected", () => {
    assert(!isValidSolanaPubkey("not-a-key"), "bad key accepted");
    assert(!isValidSolanaPubkey("0OIl"), "ambiguous chars accepted");
    assert(isValidSolanaPubkey(TREASURY), "valid treasury key rejected");
  });
  await test("B. allowlist dedups + stays pending when empty", async () => {
    const empty = await buildAllowlist([]);
    assert(empty.status === "PENDING_ALLOWLIST" && empty.merkleRoot === null, "empty allowlist not pending");
    const dup = await buildAllowlist([TREASURY, TREASURY]);
    assert(dup.count === 1 && dup.duplicatesRemoved === 1, "dedup failed");
  });
  await test("C. allowlist aborts on fabricated/invalid wallet", async () => {
    let threw = false;
    try { await buildAllowlist(["FAKEWALLET!!!"]); } catch { threw = true; }
    assert(threw, "invalid wallet not rejected");
  });

  // --- execution / safety gates ---
  let signerAccesses = 0;
  let broadcasts = 0;
  await test("mint execution is disabled before signer/transport", async () => {
    const umi = new Proxy({}, { get() { signerAccesses++; throw new Error("signer access forbidden"); } });
    let refused = false;
    try {
      await executeMint({ umi, phase: "public", groups: [], treasury: TREASURY,
        candyMachine: TREASURY, candyGuard: TREASURY, collection: TREASURY });
      broadcasts++;
    } catch (e) { refused = /SDK broadcast integration incomplete/.test(String(e)); }
    assert(refused, "mint execution was not disabled");
  });
  await test("integration + program-enforcement blockers cannot be asserted away", () => {
    const ready = structuredClone(cfg);
    ready.pricing.early.start_date = "2030-01-01T00:00:00Z";
    ready.pricing.public.start_date = "2030-01-02T00:00:00Z";
    ready.pricing.early.allowlist_source = "test-only";
    ready.pricing.early.merkle_root = "a".repeat(64);
    const codes = computeBlockers(ready, BLOCKING_READINESS).map(b => b.code);
    assert(codes.includes("sdk_broadcast_integration_incomplete"), "integration blocker lost");
    assert(codes.includes("onchain_program_enforcement_untested"), "program-enforcement blocker lost");
    assert(!deploymentPermitted(ready, BLOCKING_READINESS), "false readiness");
  });
  await test("all ten current blockers remain unresolved", () => {
    const codes = computeBlockers(cfg, { fullRenderApproved: false, finalMetadataComplete: false,
      permanentUrisAvailable: false, costQuoteReviewed: false, mainnetDeployAuthorized: false }).map(b => b.code);
    assert(codes.length === 10, `expected 10 blockers, got ${codes.length}`);
    assert(!codes.includes("shared_counter_enforcement_unverified"), "stale shared-counter blocker still present");
    assert(codes.includes("onchain_program_enforcement_untested"), "precise program-enforcement blocker missing");
  });
  await test("no network requests during tests", () => {
    const audit = (globalThis as any).__ghostOfflineAudit;
    assert(!!audit && audit.networkAttempts === 0, "offline guard missing or network attempted");
  });
  await test("no signing during tests", () => {
    assert((globalThis as any).__ghostOfflineAudit?.signingAttempts === 0 && signerAccesses === 0, "signing attempted");
  });
  await test("no SOL spent: mint execution disabled before signer/transport", () => {
    assert(broadcasts === 0 && signerAccesses === 0, "execution reached side effects");
  });

  console.log(`\n${pass}/${pass + fail} passed`);
  process.exit(fail ? 1 : 0);
}
run();
