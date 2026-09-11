/**
 * Buyer-facing mint helper for the two-tier launch. The buyer explicitly selects a
 * phase ("early" | "public"); the helper NEVER trusts a hard-coded price.
 *
 * Trust model:
 *   - The connected buyer is the minter, the transaction payer, the payer of Core
 *     asset creation/rent, and the payer of network fees. The creator/authority does
 *     NOT sponsor ordinary buyer mint costs.
 *   - The price shown is ALWAYS read fresh from the on-chain Candy Guard group, then
 *     reconciled against the expected lamports from launch-config. On any mismatch the
 *     UI must warn and refuse to mint until refreshed/reconciled.
 *
 * The pure functions below (quote, reconciliation, group lookup) are unit-testable
 * with no SDK. executeMint is disabled until integration and enforcement are verified.
 */
import type { GroupLabel } from "./launch_config.js";
import { EARLY_LAMPORTS, PUBLIC_LAMPORTS, MAX_PER_WALLET, EARLY_MINT_LIMIT_ID, PUBLIC_MINT_LIMIT_ID, lamportsToSol } from "./launch_config.js";

export const MINT_PHASES: GroupLabel[] = ["early", "public"];

/** The mintLimit id (independent counter) for a phase: early=1, public=2. */
export function mintLimitIdFor(label: GroupLabel): number {
  if (label === "early") return EARLY_MINT_LIMIT_ID;
  if (label === "public") return PUBLIC_MINT_LIMIT_ID;
  throw new Error(`ABORT: unknown mint phase '${label}'`);
}

/** Expected lamports for a phase, from config constants (the reconciliation target). */
export function expectedLamports(label: GroupLabel): bigint {
  if (label === "early") return EARLY_LAMPORTS;
  if (label === "public") return PUBLIC_LAMPORTS;
  throw new Error(`ABORT: unknown mint phase '${label}'`);
}

/** Conservative Metaplex Core creation/rent + base network fee estimate (lamports). */
export const EST_CORE_RENT_LAMPORTS = 2_900_000n; // ~0.0029 SOL Core asset account rent
export const EST_NETWORK_FEE_LAMPORTS = 15_000n;  // base sig + a little headroom

export interface OnChainGroup { label: string; solPaymentLamports: bigint; solPaymentDestination: string; mintLimit?: { id: number; limit: number } | null; }

/**
 * The freshly-fetched Candy Guard, as the buyer path sees it: an (empty) DEFAULT guard set
 * plus the priced groups, each carrying its OWN independent mintLimit. The effective limiter
 * for a group is its own mintLimit if present, else the (normally absent) default one.
 */
export interface OnChainGuardConfig {
  defaultMintLimit: { id: number; limit: number } | null;
  groups: OnChainGroup[];
}

/** Verify the requested group exists in the freshly-fetched guard groups. */
export function verifyGroupExists(groups: OnChainGroup[], label: GroupLabel): OnChainGroup {
  const g = groups.find((x) => x.label === label);
  if (!g) throw new Error(`ABORT: requested group '${label}' does not exist on-chain`);
  return g;
}

/**
 * Resolve the effective (inherited) mintLimit a group is subject to: the group's own
 * mintLimit if it overrides, otherwise the default guard set's shared mintLimit. Mirrors
 * the Candy Guard program's default/group inheritance so the buyer path asserts the SAME
 * limiter both phases consume.
 */
export function resolveEffectiveMintLimit(cfg: OnChainGuardConfig, group: OnChainGroup): { id: number; limit: number } {
  const eff = group.mintLimit ?? cfg.defaultMintLimit;
  if (!eff) throw new Error(`ABORT: group '${group.label}' resolves to no mintLimit (no group override and no inherited default limiter)`);
  return eff;
}

/**
 * Mint arguments the frontend MUST send for a phase: the phase's OWN mintLimit id (early=1,
 * public=2, so the program derives that phase's independent wallet counter) plus, for the
 * early group, the caller's allowlist proof when required. The group LABEL is passed
 * separately to mintV1 (never as a mintArg) and selects which priced route/guards apply.
 * This is prepared data only — it constructs no transaction and reaches no signer.
 */
export interface MintArgs { mintLimit: { id: number }; allowListProof?: string[]; }
export function buildMintArgs(phase: GroupLabel, allowListProof?: string[]): MintArgs {
  const args: MintArgs = { mintLimit: { id: mintLimitIdFor(phase) } };
  if (allowListProof && allowListProof.length) args.allowListProof = allowListProof;
  return args;
}

export interface Reconciliation { ok: boolean; onchain: bigint; expected: bigint; message: string; }

/**
 * Reconcile the freshly-read on-chain price against the expected config price.
 * ok=false means the UI MUST warn and refuse the mint until refreshed/reconciled.
 */
export function reconcilePrice(onchainLamports: bigint, expected: bigint): Reconciliation {
  if (onchainLamports === expected)
    return { ok: true, onchain: onchainLamports, expected, message: "On-chain price matches expected config." };
  return {
    ok: false,
    onchain: onchainLamports,
    expected,
    message: `PRICE MISMATCH: on-chain ${lamportsToSol(onchainLamports)} SOL (${onchainLamports}) != expected ${lamportsToSol(expected)} SOL (${expected}). Refusing mint — refresh/reconcile first.`,
  };
}

export interface BuyerQuote {
  phase: GroupLabel;
  label: string;
  mintPriceLamports: bigint;
  mintPriceSol: string;
  estCoreRentLamports: bigint;
  estNetworkFeeLamports: bigint;
  estCoreAndNetworkSol: string;
  totalEstimatedLamports: bigint;
  totalEstimatedSol: string;
  treasuryDestination: string;
  payer: "connected_buyer";
  minter: "connected_buyer";
}

/** Build the buyer-facing quote from the freshly read on-chain group. */
export function buildBuyerQuote(phase: GroupLabel, group: OnChainGroup): BuyerQuote {
  const price = group.solPaymentLamports;
  const estCoreAndNetwork = EST_CORE_RENT_LAMPORTS + EST_NETWORK_FEE_LAMPORTS;
  const total = price + estCoreAndNetwork;
  return {
    phase,
    label: group.label,
    mintPriceLamports: price,
    mintPriceSol: lamportsToSol(price),
    estCoreRentLamports: EST_CORE_RENT_LAMPORTS,
    estNetworkFeeLamports: EST_NETWORK_FEE_LAMPORTS,
    estCoreAndNetworkSol: lamportsToSol(estCoreAndNetwork),
    totalEstimatedLamports: total,
    totalEstimatedSol: lamportsToSol(total),
    treasuryDestination: group.solPaymentDestination,
    payer: "connected_buyer",
    minter: "connected_buyer",
  };
}

export interface PreflightResult { quote: BuyerQuote; reconciliation: Reconciliation; group: OnChainGroup; }

/**
 * Full preflight from freshly-fetched on-chain guard groups. Steps 3-8 + 11-12 of
 * the required helper flow, done purely so a test can assert them. Throws if the
 * group is missing; returns reconciliation.ok=false (do not mint) on price drift.
 */
export function preflight(cfg: OnChainGuardConfig, phase: GroupLabel, treasury: string): PreflightResult {
  // Each phase must resolve to its OWN independent limiter: early id 1, public id 2, each
  // limit 5, and the two ids must be DISTINCT (a shared id would collapse to one counter).
  const seenIds = new Set<number>();
  for (const label of MINT_PHASES) {
    const g = verifyGroupExists(cfg.groups, label);
    const eff = resolveEffectiveMintLimit(cfg, g);
    if (eff.id !== mintLimitIdFor(label) || eff.limit !== MAX_PER_WALLET)
      throw new Error(`ABORT: ${label} mintLimit mismatch (resolved ${eff.id}/${eff.limit}, expected ${mintLimitIdFor(label)}/${MAX_PER_WALLET})`);
    if (seenIds.has(eff.id))
      throw new Error("ABORT: early/public share a mintLimit id — independent per-phase counters required");
    seenIds.add(eff.id);
  }
  const group = verifyGroupExists(cfg.groups, phase);        // (3) verify group exists
  if (group.solPaymentDestination !== treasury)             // (11) sol payment -> treasury
    throw new Error(`ABORT: group '${phase}' solPayment destination ${group.solPaymentDestination} != treasury ${treasury}`);
  const reconciliation = reconcilePrice(group.solPaymentLamports, expectedLamports(phase)); // (4) read on-chain price
  const quote = buildBuyerQuote(phase, group);              // (5-8) label, price, est cost, total
  return { quote, reconciliation, group };
}

/** Prepared interface only. No SDK import, signer, RPC, or broadcast is reachable. */
export interface MintParams {
  candyMachine: string;
  candyGuard: string;
  collection: string;
  phase: GroupLabel;
  treasury: string;
  umi: unknown;
  groups: OnChainGroup[];
}

export async function executeMint(_p: MintParams): Promise<{ asset: string; verified: boolean }> {
  throw new Error("ABORT: SDK broadcast integration incomplete; shared counter enforcement unverified. Mint disabled.");
}
