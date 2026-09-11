/**
 * Prepared guard descriptors, not SDK objects or verified on-chain enforcement.
 *
 * The lifetime Mint Limit (id 1, limit 5) is defined ONCE, in the DEFAULT guard set.
 * Both the `early` and `public` groups INHERIT it — they define no mintLimit of their
 * own. Because the Candy Guard MintCounter PDA seeds are
 *   ['mint_limit', id(u8), user, candyGuard, candyMachine]
 * (verified from installed mpl-core-candy-machine@0.3.0 source — see mint_counter_pda.ts),
 * the group label is NOT part of the counter seeds, so early + public share ONE wallet
 * counter under the fixed Candy Guard / Candy Machine.
 *
 * The default set holds ONLY the mintLimit: any restrictive guard placed in the default
 * (an addressGate, a solPayment) is inherited by every group and would break ordinary
 * public minting or open a second price. Program-level guard inheritance and the
 * "a valid group label is required to mint when groups exist" routing remain
 * verification blockers (not executable offline this phase).
 */
import { assertConfig, MAX_PER_WALLET, SHARED_MINT_LIMIT_ID, type LaunchConfig, type GroupLabel } from "./launch_config.js";

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

/** Build the full guard layout (default + groups) from validated config. */
export function buildGuardLayout(cfg: LaunchConfig): CandyGuardLayout {
  assertConfig(cfg);

  const early: GroupDescriptor = {
    label: "early",
    guards: {
      solPayment: { lamports: String(cfg.pricing.early.price_lamports), destination: cfg.pricing.early.destination },
      // NOTE: no mintLimit here — the early group inherits the default shared mintLimit.
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
      // NOTE: no mintLimit here — the public group inherits the default shared mintLimit.
    },
  };
  if (cfg.pricing.public.start_date) pub.guards.startDate = { date: cfg.pricing.public.start_date };
  if (cfg.pricing.public.end_date) pub.guards.endDate = { date: cfg.pricing.public.end_date };

  // Default carries ONLY the shared lifetime mintLimit — inherited by every group.
  const def: GuardSet = { mintLimit: { ...cfg.default_guard.mint_limit } };

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
 * This models the Candy Guard program's documented default/group inheritance (point 5),
 * so tests can assert the resolved mintLimit that both phases actually consume.
 */
export function resolveGuardSet(layout: CandyGuardLayout, label: GroupLabel): GuardSet {
  return { ...layout.default, ...getGroup(layout, label).guards };
}

/** The lifetime mintLimit a group effectively enforces (inherited from default unless overridden). */
export function effectiveMintLimit(layout: CandyGuardLayout, label: GroupLabel): MintLimitGuard {
  const ml = resolveGuardSet(layout, label).mintLimit;
  if (!ml) throw new Error(`ABORT: group '${label}' resolves to no mintLimit (default lost its shared limiter)`);
  return ml;
}

/**
 * Validate the prepared layout: the shared limiter lives in the default, both groups are
 * individually priced to the treasury, and nothing opens a cheaper/free ungrouped route.
 * On-chain routing enforcement (a valid group label is required when groups exist) is a
 * program behavior and is NOT proven here.
 */
export function assertNoCheapDefaultPath(layout: CandyGuardLayout, treasury: string): void {
  const d = layout.default;
  // The default must carry the shared limiter and NOTHING that would be inherited badly.
  if (d.solPayment)
    throw new Error("ABORT: default guard set carries a solPayment — it would be inherited by every group as an ungrouped price");
  if (d.addressGate)
    throw new Error("ABORT: default guard set carries an addressGate — it would be inherited by early+public and block ordinary buyers");
  assertSharedLifetimeLayout(layout);
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
 * Reject independent counters, a missing default limiter, and cap drift in prepared
 * descriptors. The shared limiter MUST be defined once in the default (id 1, limit 5);
 * a group may not introduce a different id or a raised limit that would create a second
 * independent counter or a larger per-wallet cap.
 */
export function assertSharedLifetimeLayout(layout: CandyGuardLayout): void {
  if (layout.groups.length !== 2 || layout.groups.map(g => g.label).sort().join(",") !== "early,public")
    throw new Error("ABORT: unexpected mint routes");
  const def = layout.default.mintLimit;
  if (def?.id !== SHARED_MINT_LIMIT_ID || def.limit !== MAX_PER_WALLET)
    throw new Error("ABORT: default guard set must define the shared lifetime mintLimit id 1, limit 5");
  for (const g of layout.groups) {
    const own = g.guards.mintLimit;
    // A group SHOULD inherit (no own mintLimit). If one is present it must NOT diverge —
    // a different id would be an independent counter; a different limit would drift the cap.
    if (own && (own.id !== SHARED_MINT_LIMIT_ID || own.limit !== MAX_PER_WALLET))
      throw new Error(`ABORT: group '${g.label}' overrides the shared mintLimit (id/limit drift => independent counter or raised cap)`);
    // The effective (resolved) limiter must be exactly the shared one.
    const eff = resolveGuardSet(layout, g.label).mintLimit;
    if (eff?.id !== SHARED_MINT_LIMIT_ID || eff.limit !== MAX_PER_WALLET)
      throw new Error(`ABORT: group '${g.label}' does not resolve to the shared lifetime mintLimit id 1, limit 5`);
  }
}
