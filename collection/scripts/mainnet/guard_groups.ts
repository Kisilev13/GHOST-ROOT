/**
 * Prepared guard descriptors, not SDK objects or verified on-chain enforcement.
 *
 * Per-wallet caps are INDEPENDENT per phase (5 early + 5 public = 10 combined). Each group
 * defines its OWN Mint Limit with a DISTINCT id (early id 1, public id 2). Because the
 * Candy Guard MintCounter PDA seeds are
 *   ['mint_limit', id(u8), user, candyGuard, candyMachine]
 * (verified from installed mpl-core-candy-machine@0.3.0 source — see mint_counter_pda.ts),
 * distinct ids derive DIFFERENT counter PDAs, so early and public consume SEPARATE wallet
 * counters.
 *
 * The DEFAULT guard set is EMPTY: any guard placed there is inherited by every group — a
 * default mintLimit would collapse the two phases onto one shared counter, a default
 * solPayment would open a second ungrouped price, and a default addressGate would block
 * ordinary buyers. "No ungrouped/free default mint route" comes from the Candy Guard
 * program requiring a valid group label to mint when groups exist (program-level; not
 * executable offline this phase).
 */
import { assertConfig, MAX_PER_WALLET, EARLY_MINT_LIMIT_ID, PUBLIC_MINT_LIMIT_ID, type LaunchConfig, type GroupLabel } from "./launch_config.js";

export interface SolPaymentGuard { lamports: string; destination: string; }
export interface MintLimitGuard { id: number; limit: number; }
export interface StartDateGuard { date: string; }
export interface AllowListGuard { merkleRoot: string; }
export interface AddressGateGuard { address: string; }

export interface GuardSet {
  solPayment?: SolPaymentGuard;
  mintLimit?: MintLimitGuard;
  startDate?: StartDateGuard;
  endDate?: StartDateGuard;
  allowList?: AllowListGuard;
  addressGate?: AddressGateGuard;
}

export interface GroupDescriptor { label: GroupLabel; guards: GuardSet; }

export interface CandyGuardLayout {
  default: GuardSet;
  groups: GroupDescriptor[];
}

/** Build the full guard layout (empty default + priced groups) from validated config. */
export function buildGuardLayout(cfg: LaunchConfig): CandyGuardLayout {
  assertConfig(cfg);

  const early: GroupDescriptor = {
    label: "early",
    guards: {
      solPayment: { lamports: String(cfg.pricing.early.price_lamports), destination: cfg.pricing.early.destination },
      // Early carries its OWN mintLimit (id 1) — an independent per-wallet counter.
      mintLimit: { id: cfg.candy_guard.mint_limit_ids.early, limit: cfg.pricing.early.max_per_wallet },
    },
  };
  // startDate only when a real date exists — never invented.
  if (cfg.pricing.early.start_date) early.guards.startDate = { date: cfg.pricing.early.start_date };
  if (cfg.pricing.early.end_date) early.guards.endDate = { date: cfg.pricing.early.end_date };
  // allowList only when a real merkle root exists (PENDING_ALLOWLIST => omitted).
  if (cfg.pricing.early.merkle_root) early.guards.allowList = { merkleRoot: cfg.pricing.early.merkle_root };

  const pub: GroupDescriptor = {
    label: "public",
    guards: {
      solPayment: { lamports: String(cfg.pricing.public.price_lamports), destination: cfg.pricing.public.destination },
      // Public carries its OWN mintLimit (id 2) — a separate counter from early.
      mintLimit: { id: cfg.candy_guard.mint_limit_ids.public, limit: cfg.pricing.public.max_per_wallet },
    },
  };
  if (cfg.pricing.public.start_date) pub.guards.startDate = { date: cfg.pricing.public.start_date };
  if (cfg.pricing.public.end_date) pub.guards.endDate = { date: cfg.pricing.public.end_date };

  // Default is EMPTY — see file header. Nothing here is inherited into the groups.
  const def: GuardSet = {};

  return { default: def, groups: [early, pub] };
}

export function getGroup(layout: CandyGuardLayout, label: GroupLabel): GroupDescriptor {
  const g = layout.groups.find((x) => x.label === label);
  if (!g) throw new Error(`ABORT: group '${label}' not found in guard layout`);
  return g;
}

/**
 * Resolve the EFFECTIVE guard set a minter is subject to for a group: the default guard
 * set merged with the group's own guards, where the group overrides the same guard type.
 * With an empty default this is just the group's own guards, but the merge is modelled
 * explicitly so a test can prove nothing leaks in from the default.
 */
export function resolveGuardSet(layout: CandyGuardLayout, label: GroupLabel): GuardSet {
  return { ...layout.default, ...getGroup(layout, label).guards };
}

/** The per-wallet mintLimit a group enforces (its own; default carries none). */
export function effectiveMintLimit(layout: CandyGuardLayout, label: GroupLabel): MintLimitGuard {
  const ml = resolveGuardSet(layout, label).mintLimit;
  if (!ml) throw new Error(`ABORT: group '${label}' resolves to no mintLimit (per-phase cap unenforced)`);
  return ml;
}

/**
 * Validate the prepared layout: the default carries NOTHING (empty), both groups are
 * individually priced to the treasury AND each defines its own independent mintLimit, and
 * nothing opens a cheaper/free ungrouped route. On-chain routing enforcement (a valid group
 * label is required when groups exist) is a program behavior and is NOT proven here.
 */
export function assertNoCheapDefaultPath(layout: CandyGuardLayout, treasury: string): void {
  const d = layout.default;
  // The default must be EMPTY — anything in it is inherited by every group.
  if (d.solPayment)
    throw new Error("ABORT: default guard set carries a solPayment — it would be inherited by every group as an ungrouped price");
  if (d.addressGate)
    throw new Error("ABORT: default guard set carries an addressGate — it would be inherited by early+public and block ordinary buyers");
  if (d.mintLimit)
    throw new Error("ABORT: default guard set carries a mintLimit — it would be inherited and collapse early+public onto one shared counter");
  assertIndependentLimitLayout(layout);
  // Groups must each be individually priced against the treasury.
  for (const g of layout.groups) {
    if (!g.guards.solPayment)
      throw new Error(`ABORT: group '${g.label}' has no solPayment — it would mint for 0 SOL`);
    if (g.guards.solPayment.destination !== treasury)
      throw new Error(`ABORT: group '${g.label}' solPayment destination != treasury`);
    if (BigInt(g.guards.solPayment.lamports) <= 0n)
      throw new Error(`ABORT: group '${g.label}' solPayment must be > 0 lamports`);
  }
}

/**
 * Reject a missing/shared limiter and cap drift in prepared descriptors. Each group MUST
 * define its OWN mintLimit — early id 1, public id 2 (distinct), each limit 5 — so the two
 * phases enforce INDEPENDENT counters. A shared id across groups (one counter) or a raised
 * limit (cap drift) is rejected.
 */
export function assertIndependentLimitLayout(layout: CandyGuardLayout): void {
  if (layout.groups.length !== 2 || layout.groups.map(g => g.label).sort().join(",") !== "early,public")
    throw new Error("ABORT: unexpected mint routes");
  if (layout.default.mintLimit)
    throw new Error("ABORT: default guard set must NOT define a mintLimit (independent per-group counters required)");

  const expected: Record<GroupLabel, number> = { early: EARLY_MINT_LIMIT_ID, public: PUBLIC_MINT_LIMIT_ID };
  const seenIds = new Set<number>();
  for (const g of layout.groups) {
    const own = g.guards.mintLimit;
    if (!own)
      throw new Error(`ABORT: group '${g.label}' defines no mintLimit — per-phase cap unenforced`);
    if (own.id !== expected[g.label])
      throw new Error(`ABORT: group '${g.label}' mintLimit id ${own.id} != expected ${expected[g.label]}`);
    if (own.limit !== MAX_PER_WALLET)
      throw new Error(`ABORT: group '${g.label}' mintLimit limit ${own.limit} != ${MAX_PER_WALLET} (cap drift)`);
    if (seenIds.has(own.id))
      throw new Error(`ABORT: mintLimit id ${own.id} shared across groups — would collapse to one counter`);
    seenIds.add(own.id);
  }
}
