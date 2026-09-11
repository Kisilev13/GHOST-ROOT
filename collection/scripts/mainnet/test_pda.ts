#!/usr/bin/env -S npx tsx
/**
 * SDK-backed MintCounter PDA proofs, using the INSTALLED mpl-core-candy-machine@0.3.0.
 * Runs under offline_guard.cjs: PDA derivation is pure ed25519/sha256 — no rpc, no signing,
 * no SOL. Proves the shared-counter claim from real code, not documentation.
 */
import {
  pdaUmi, deriveMintCounterPda, assertMintCounterSeedSourceContract, CANDY_GUARD_PROGRAM_ID,
} from "./mint_counter_pda.js";
import { COLLECTION_ADDRESS, GHOST_0001_ASSET, TREASURY, EARLY_MINT_LIMIT_ID, PUBLIC_MINT_LIMIT_ID, MAX_PER_WALLET } from "./launch_config.js";

let pass = 0, fail = 0;
function test(name: string, fn: () => void): void {
  try { fn(); console.log(`PASS  ${name}`); pass++; }
  catch (e) { console.log(`FAIL  ${name} — ${e instanceof Error ? e.message : e}`); fail++; }
}
function assert(cond: boolean, msg: string): void { if (!cond) throw new Error(msg); }

// Deterministic stand-in addresses (all real base58 pubkeys). Fixed Candy Guard + Candy
// Machine for the launch; two distinct wallets to prove per-wallet scoping.
const CANDY_GUARD = TREASURY;                 // fixed guard (stand-in)
const CANDY_MACHINE = COLLECTION_ADDRESS;     // fixed machine (stand-in)
const WALLET_A = GHOST_0001_ASSET;
const WALLET_B = "11111111111111111111111111111111"; // system program id — a valid, distinct pubkey

const umi = pdaUmi();

// SOURCE CONTRACT — the seeds are exactly ['mint_limit', id, user, candyGuard, candyMachine].
test("PDA source contract: seeds exclude group label / price / phase", () => {
  const c = assertMintCounterSeedSourceContract();
  assert(c.seeds.join(",") === "mint_limit,id,user,candyGuard,candyMachine", "unexpected seed list");
  assert(c.programId === CANDY_GUARD_PROGRAM_ID, "unexpected candy guard program id");
});

// COUNTER ADDRESS INDEPENDENCE — early (id 1) and public (id 2) use DIFFERENT mintLimit ids,
// so they derive DIFFERENT counter PDAs for the same wallet. This is what gives independent
// 5+5 caps. FAIL HARD if they collide.
test("earlyCounterPda != publicCounterPda (independent per-phase counters)", () => {
  const early = deriveMintCounterPda(umi, { id: EARLY_MINT_LIMIT_ID, user: WALLET_A, candyGuard: CANDY_GUARD, candyMachine: CANDY_MACHINE });
  const pub   = deriveMintCounterPda(umi, { id: PUBLIC_MINT_LIMIT_ID, user: WALLET_A, candyGuard: CANDY_GUARD, candyMachine: CANDY_MACHINE });
  assert(early !== pub, `earlyCounterPda ${early} == publicCounterPda ${pub} — phases would share one counter; independence broken`);
});

// The id IS a seed: same id => same counter; different id => different counter. This is the
// mechanism that makes early (id 1) and public (id 2) independent.
test("same id => same counter; different id => different counter", () => {
  const a = deriveMintCounterPda(umi, { id: EARLY_MINT_LIMIT_ID, user: WALLET_A, candyGuard: CANDY_GUARD, candyMachine: CANDY_MACHINE });
  const aAgain = deriveMintCounterPda(umi, { id: EARLY_MINT_LIMIT_ID, user: WALLET_A, candyGuard: CANDY_GUARD, candyMachine: CANDY_MACHINE });
  const b = deriveMintCounterPda(umi, { id: PUBLIC_MINT_LIMIT_ID, user: WALLET_A, candyGuard: CANDY_GUARD, candyMachine: CANDY_MACHINE });
  assert(a === aAgain, "same id derived different counters");
  assert(a !== b, "different ids collided onto one counter");
});

// A DIFFERENT wallet derives a DIFFERENT counter (per-wallet cap).
test("different wallet => different counter PDA (per-wallet cap)", () => {
  const a = deriveMintCounterPda(umi, { id: 1, user: WALLET_A, candyGuard: CANDY_GUARD, candyMachine: CANDY_MACHINE });
  const b = deriveMintCounterPda(umi, { id: 1, user: WALLET_B, candyGuard: CANDY_GUARD, candyMachine: CANDY_MACHINE });
  assert(a !== b, "distinct wallets collided onto one counter");
});

// PHASE TRANSITION keyed on the REAL derived PDAs: early (id 1) and public (id 2) resolve
// to DIFFERENT counter addresses, so each phase independently allows 5 (10 combined) and the
// 6th mint WITHIN a phase exceeds that phase's own counter.
test("phase counters are independent; each rejects its own 6th; two distinct PDAs", () => {
  const counters = new Map<string, number>();
  const idFor = (phase: "early" | "public") => (phase === "early" ? EARLY_MINT_LIMIT_ID : PUBLIC_MINT_LIMIT_ID);
  const mint = (phase: "early" | "public"): boolean => {
    const pda = deriveMintCounterPda(umi, { id: idFor(phase), user: WALLET_A, candyGuard: CANDY_GUARD, candyMachine: CANDY_MACHINE });
    const n = counters.get(pda) ?? 0;
    if (n >= MAX_PER_WALLET) return false;
    counters.set(pda, n + 1);
    return true;
  };
  // wallet A: 5 early ok, 6th early fails; then 5 public ok (independent), 6th public fails
  for (let i = 0; i < 5; i++) assert(mint("early"), "early refused within cap");
  assert(mint("early") === false, "6th early not rejected on the early counter");
  for (let i = 0; i < 5; i++) assert(mint("public"), "public refused within cap (should be independent of early)");
  assert(mint("public") === false, "6th public not rejected on the public counter");
  assert(counters.size === 2, "phases did not use two distinct counter PDAs");
});

test("no network + no signing during PDA derivation", () => {
  const audit = (globalThis as any).__ghostOfflineAudit;
  assert(!!audit, "offline audit missing (run under offline_guard.cjs)");
  assert(audit.networkAttempts === 0, "network attempted during PDA derivation");
  assert(audit.signingAttempts === 0, "signing attempted during PDA derivation");
});

console.log(`\n${pass}/${pass + fail} passed`);
process.exit(fail ? 1 : 0);
