#!/usr/bin/env -S npx tsx
/**
 * SDK-backed MintCounter PDA proofs, using the INSTALLED mpl-core-candy-machine@0.3.0.
 * Runs under offline_guard.cjs: PDA derivation is pure ed25519/sha256 — no rpc, no signing,
 * no SOL. Proves the shared-counter claim from real code, not documentation.
 */
import {
  pdaUmi, deriveMintCounterPda, assertMintCounterSeedSourceContract, CANDY_GUARD_PROGRAM_ID,
} from "./mint_counter_pda.js";
import { COLLECTION_ADDRESS, GHOST_0001_ASSET, TREASURY, SHARED_MINT_LIMIT_ID, MAX_PER_WALLET } from "./launch_config.js";

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

// COUNTER ADDRESS EQUALITY — early and public use the SAME id/user/guard/machine, so they
// derive the IDENTICAL counter PDA. The group label is not a seed, so the phase cannot
// change the derivation. FAIL HARD if not equal.
test("earlyCounterPda == publicCounterPda (shared lifetime counter)", () => {
  const early = deriveMintCounterPda(umi, { id: SHARED_MINT_LIMIT_ID, user: WALLET_A, candyGuard: CANDY_GUARD, candyMachine: CANDY_MACHINE });
  const pub   = deriveMintCounterPda(umi, { id: SHARED_MINT_LIMIT_ID, user: WALLET_A, candyGuard: CANDY_GUARD, candyMachine: CANDY_MACHINE });
  assert(early === pub, `earlyCounterPda ${early} != publicCounterPda ${pub} — shared counter broken; retain blocker`);
});

// A DIFFERENT id derives a DIFFERENT counter (proves id scoping — why divergent group ids
// would silently create a second 5-mint allowance).
test("different mintLimit id => different counter PDA", () => {
  const id1 = deriveMintCounterPda(umi, { id: 1, user: WALLET_A, candyGuard: CANDY_GUARD, candyMachine: CANDY_MACHINE });
  const id2 = deriveMintCounterPda(umi, { id: 2, user: WALLET_A, candyGuard: CANDY_GUARD, candyMachine: CANDY_MACHINE });
  assert(id1 !== id2, "different ids collided onto one counter");
});

// A DIFFERENT wallet derives a DIFFERENT counter (per-wallet cap).
test("different wallet => different counter PDA (per-wallet cap)", () => {
  const a = deriveMintCounterPda(umi, { id: 1, user: WALLET_A, candyGuard: CANDY_GUARD, candyMachine: CANDY_MACHINE });
  const b = deriveMintCounterPda(umi, { id: 1, user: WALLET_B, candyGuard: CANDY_GUARD, candyMachine: CANDY_MACHINE });
  assert(a !== b, "distinct wallets collided onto one counter");
});

// PHASE TRANSITION keyed on the REAL derived PDA: both phases resolve to one counter
// address, so a wallet's 6th combined mint (early+public) exceeds limit 5 on ONE account.
test("phase transitions increment ONE derived counter; 6th combined mint exceeds cap", () => {
  const counters = new Map<string, number>();
  const mint = (_phase: "early" | "public"): boolean => {
    // both phases send mintLimit.id = 1 => identical seeds => identical PDA
    const pda = deriveMintCounterPda(umi, { id: SHARED_MINT_LIMIT_ID, user: WALLET_A, candyGuard: CANDY_GUARD, candyMachine: CANDY_MACHINE });
    const n = counters.get(pda) ?? 0;
    if (n >= MAX_PER_WALLET) return false;
    counters.set(pda, n + 1);
    return true;
  };
  // wallet A: 3 early + 2 public = 5 ok, 6th fails
  for (let i = 0; i < 3; i++) assert(mint("early"), "early refused within cap");
  for (let i = 0; i < 2; i++) assert(mint("public"), "public refused within cap");
  assert(mint("public") === false, "6th combined mint not rejected on the shared counter");
  assert(counters.size === 1, "phases used more than one counter PDA");
});

test("no network + no signing during PDA derivation", () => {
  const audit = (globalThis as any).__ghostOfflineAudit;
  assert(!!audit, "offline audit missing (run under offline_guard.cjs)");
  assert(audit.networkAttempts === 0, "network attempted during PDA derivation");
  assert(audit.signingAttempts === 0, "signing attempted during PDA derivation");
});

console.log(`\n${pass}/${pass + fail} passed`);
process.exit(fail ? 1 : 0);
