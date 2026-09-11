/**
 * Pure launch-blocker evaluation. Deployment of the Candy Machine / Candy Guard
 * MUST abort while ANY blocker is unresolved. No chain access — the blocker inputs
 * are supplied by the caller (config + render/metadata/authorization status), so
 * this is fully unit-testable and cannot itself trigger a deploy.
 */
import type { LaunchConfig } from "./launch_config.js";

export interface LaunchReadiness {
  /** Has the full 3333 render been approved? */
  fullRenderApproved: boolean;
  /** Is the final metadata complete for all items? */
  finalMetadataComplete: boolean;
  /** Are permanent (Arweave/Irys) metadata URIs available? */
  permanentUrisAvailable: boolean;
  /** Has the cost quote been reviewed? */
  costQuoteReviewed: boolean;
  /** Explicit human authorization to deploy to mainnet. */
  mainnetDeployAuthorized: boolean;
}

export interface Blocker { code: string; reason: string; }

/**
 * Compute the list of unresolved launch blockers. An empty list means deployment
 * still requires verified integration; a non-empty list means ABORT. Dates being null and a still-pending
 * required allowlist are treated as hard blockers, per the launch spec.
 */
export function computeBlockers(cfg: LaunchConfig, r: LaunchReadiness): Blocker[] {
  const b: Blocker[] = [];

  if (!r.fullRenderApproved) b.push({ code: "full_3333_render_not_approved", reason: "Full 3333 render is not approved." });
  if (!r.finalMetadataComplete) b.push({ code: "final_metadata_incomplete", reason: "Final metadata is not complete." });
  if (!r.permanentUrisAvailable) b.push({ code: "permanent_metadata_uris_unavailable", reason: "Permanent metadata URIs are not available." });

  if (cfg.pricing.early.start_date == null)
    b.push({ code: "early_start_date_null", reason: "Early group start date is null (NOT_SCHEDULED)." });
  if (cfg.pricing.public.start_date == null)
    b.push({ code: "public_start_date_null", reason: "Public group start date is null (NOT_SCHEDULED)." });

  if (isEarlyAllowlistBlocking(cfg))
    b.push({ code: "allowlist_required_but_pending", reason: "Early group requires an allowlist but source is PENDING_ALLOWLIST (no merkle root)." });

  if (!r.costQuoteReviewed) b.push({ code: "cost_quote_not_reviewed", reason: "Cost quote has not been reviewed." });
  if (!r.mainnetDeployAuthorized) b.push({ code: "mainnet_deploy_authorization_absent", reason: "Explicit mainnet deployment authorization is absent." });

  // Deliberately not caller flags: readiness claims cannot clear unfinished code or proof.
  // The broadcast transaction BUILDERS now exist (createCandyMachine/createCandyGuard/
  // addConfigLines/updateCandyGuard construct real umi TransactionBuilders), but a
  // signed+sent deployment path, resumable config-line loading against chain, and a
  // localnet/devnet rehearsal remain — so integration is still incomplete.
  b.push({ code: "sdk_broadcast_integration_incomplete", reason: "Broadcast tx builders exist but the signed deployment path, on-chain resumable config-line loading, and a localnet/devnet rehearsal are not complete." });
  // The MintCounter PDA derivation IS now source-verified (installed mpl-core-candy-machine@0.3.0:
  // seeds ['mint_limit', id, user, candyGuard, candyMachine] — no group label; early==public PDA).
  // What remains is PROGRAM runtime behavior, not observable offline: guard inheritance merge,
  // the "valid group label required to mint when groups exist" routing, and the counter
  // decrement/reject-sixth. These need a validator (localnet/devnet), forbidden this phase.
  b.push({ code: "onchain_program_enforcement_untested", reason: "Candy Guard runtime behavior (default→group inheritance merge, group-label-required routing / no ungrouped default mint, and counter decrement rejecting the sixth) is not executable offline; needs a localnet/devnet rehearsal." });
  return b;
}

/** True when the early group needs an allowlist but no usable merkle root exists yet. */
export function isEarlyAllowlistBlocking(cfg: LaunchConfig): boolean {
  const e = cfg.pricing.early;
  if (e.allowlist_required !== true) return false;
  const pending = e.allowlist_source === "PENDING_ALLOWLIST" || e.allowlist_source == null;
  const noRoot = !e.merkle_root;
  return pending || noRoot;
}

export function deploymentPermitted(cfg: LaunchConfig, r: LaunchReadiness): boolean {
  return computeBlockers(cfg, r).length === 0;
}

/** Throw Error("ABORT: ...") listing every unresolved blocker. */
export function assertDeployable(cfg: LaunchConfig, r: LaunchReadiness): void {
  const b = computeBlockers(cfg, r);
  if (b.length > 0)
    throw new Error(`ABORT: ${b.length} launch blocker(s) unresolved:\n` + b.map((x) => `  - ${x.code}: ${x.reason}`).join("\n"));
}
