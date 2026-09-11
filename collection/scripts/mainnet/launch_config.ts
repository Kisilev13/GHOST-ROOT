/**
 * Typed loader + validator for the two-tier GHOST//ROOT launch config.
 *
 * Pure: no chain access, no umi, no candy-machine SDK. This module is the single
 * source of truth for the launch structure and is safe to import from tests and
 * from the browser mint helper. The on-chain price is NEVER trusted from here —
 * this is the *intended* config; the mint helper reconciles it against chain.
 */
import { readFileSync } from "node:fs";
import { dirname } from "node:path";
import { fileURLToPath } from "node:url";

export const TREASURY = "CcgTzAmyog1h3tUnR7zqtQ2bZzQSYL9L9xZHDBvtD94R";
export const AUTHORITY = "CcgTzAmyog1h3tUnR7zqtQ2bZzQSYL9L9xZHDBvtD94R";
export const COLLECTION_ADDRESS = "ECfe4r3Xp66qe6dAGgdnc4QAMikdDHHvrGfWPDqkrE1p";
export const GHOST_0001_ASSET = "GUhpsP3cHvtJ5JJzKDnfcr83tvHJPn3kA2ib17MtM9B4";
export const ROYALTY_BPS = 500;

export const TOTAL_SUPPLY = 3333;
export const CANDY_MACHINE_SUPPLY = 3332;
export const ID_FIRST = 2;
export const ID_LAST = 3333;

export const EARLY_LAMPORTS = 100_000_000n;
export const PUBLIC_LAMPORTS = 150_000_000n;
export const MAX_PER_WALLET = 5; // per phase — independent counters (5 early + 5 public)
export const EARLY_MINT_LIMIT_ID = 1;
export const PUBLIC_MINT_LIMIT_ID = 2;
export const LAMPORTS_PER_SOL = 1_000_000_000n;

export type GroupLabel = "early" | "public";

export interface GroupPricing {
  label: GroupLabel;
  description: string;
  price_sol: number;
  price_lamports: number;
  max_per_wallet: number;
  mint_limit_id: number;
  wallet_limit_scope: "independent";
  destination: string;
  start_date: string | null;
  end_date: string | null;
  status: string;
  allowlist_required?: boolean;
  allowlist_source?: string;
  merkle_root?: string | null;
}

export interface LaunchConfig {
  schema_version: string;
  wallet_limit: { per_phase_max: number; scope: string; early_counter_id: number; public_counter_id: number; combined_max: number; enforcement_status: string };
  collection: string;
  state: string;
  addresses: {
    collection: string;
    authority: string;
    treasury: string;
    genesis_asset_ghost_0001: string;
    candy_machine: string | null;
    candy_guard: string | null;
  };
  supply: {
    total_collection: number;
    candy_machine: number;
    id_range: { first: number; last: number; format: string };
    excluded_ghost_0001: boolean;
    reserved_pool: unknown;
  };
  royalty: { basis_points: number; percent: number; recipient: string };
  buyer_pays: {
    minter: string;
    transaction_payer: string;
    core_asset_rent_payer: string;
    network_fee_payer: string;
    creator_sponsors_buyer_costs: boolean;
  };
  pricing: { early: GroupPricing; public: GroupPricing };
  default_guard: {
    policy: string;
    strategy: string;
    address_gate_to: string | null;
    sol_payment: unknown;
    mint_limit: { id: number; limit: number } | null;
  };
  bot_tax: { enabled: boolean };
  candy_guard: {
    program?: string;
    program_id?: string;
    sdk_version?: string;
    mint_limit_defined_in: "each_group";
    groups_inherit_default_mint_limit: boolean;
    mint_limit_ids: { early: number; public: number };
    groups: string[];
  };
}

const HERE = dirname(fileURLToPath(import.meta.url));
export const CONFIG_PATH = `${HERE}/../../launch/launch-config.json`;

/**
 * Load + structurally validate the launch config. Throws Error("ABORT: ...") on any
 * drift from the fixed production invariants (addresses, lamports, caps, supply).
 * This is intentionally strict: a config that has been edited into an unsafe shape
 * must fail loudly rather than silently deploy the wrong economics.
 */
export function loadLaunchConfig(path: string = CONFIG_PATH): LaunchConfig {
  const cfg = JSON.parse(readFileSync(path, "utf8")) as LaunchConfig;
  assertConfig(cfg);
  return cfg;
}

export function assertConfig(cfg: LaunchConfig): void {
  const a = cfg.addresses;
  if (a.collection !== COLLECTION_ADDRESS)
    throw new Error(`ABORT: config collection ${a.collection} != ${COLLECTION_ADDRESS}`);
  if (a.treasury !== TREASURY) throw new Error(`ABORT: config treasury ${a.treasury} != ${TREASURY}`);
  if (a.authority !== AUTHORITY) throw new Error(`ABORT: config authority ${a.authority} != ${AUTHORITY}`);

  if (cfg.royalty.basis_points !== ROYALTY_BPS)
    throw new Error(`ABORT: royalty ${cfg.royalty.basis_points} != ${ROYALTY_BPS} bps`);

  if (cfg.supply.total_collection !== TOTAL_SUPPLY)
    throw new Error(`ABORT: total supply ${cfg.supply.total_collection} != ${TOTAL_SUPPLY}`);
  if (cfg.supply.candy_machine !== CANDY_MACHINE_SUPPLY)
    throw new Error(`ABORT: candy machine supply ${cfg.supply.candy_machine} != ${CANDY_MACHINE_SUPPLY}`);
  if (cfg.supply.id_range.first !== ID_FIRST || cfg.supply.id_range.last !== ID_LAST)
    throw new Error(`ABORT: id range ${cfg.supply.id_range.first}-${cfg.supply.id_range.last} != ${ID_FIRST}-${ID_LAST}`);
  if (cfg.supply.excluded_ghost_0001 !== true)
    throw new Error("ABORT: config must exclude GHOST//0001");
  if (cfg.supply.reserved_pool != null)
    throw new Error("ABORT: no separate reserved supply pool is permitted unless explicitly requested");
  // The two groups must not silently expand supply.
  if (ID_LAST - ID_FIRST + 1 !== CANDY_MACHINE_SUPPLY)
    throw new Error(`ABORT: id range width ${ID_LAST - ID_FIRST + 1} != candy machine supply ${CANDY_MACHINE_SUPPLY}`);

  // Buyer-pays invariant.
  if (cfg.buyer_pays.creator_sponsors_buyer_costs !== false)
    throw new Error("ABORT: creator must NOT sponsor ordinary buyer mint costs");

  assertGroup(cfg.pricing.early, "early", EARLY_LAMPORTS, EARLY_MINT_LIMIT_ID);
  assertGroup(cfg.pricing.public, "public", PUBLIC_LAMPORTS, PUBLIC_MINT_LIMIT_ID);

  if (cfg.pricing.early.price_lamports === cfg.pricing.public.price_lamports)
    throw new Error("ABORT: early and public prices must differ (two-tier)");
  if (BigInt(cfg.pricing.public.price_lamports) <= BigInt(cfg.pricing.early.price_lamports))
    throw new Error("ABORT: public price must be strictly greater than early price");

  // The default guard set must be EMPTY. Any guard placed in the default is inherited by
  // every group: a default mintLimit would collapse the two phases onto ONE shared counter
  // (destroying the independent 5+5 caps), a default solPayment would create a second
  // ungrouped price, and a default addressGate would block ordinary buyers. Each group
  // instead carries its OWN mintLimit with a distinct id (early 1, public 2).
  if (cfg.default_guard.strategy !== "empty")
    throw new Error("ABORT: default guard strategy must be 'empty' (each group carries its own mintLimit id)");
  if (cfg.default_guard.mint_limit != null)
    throw new Error("ABORT: default guard must NOT carry a mintLimit — it would be inherited and collapse early+public onto one shared counter");
  if (cfg.default_guard.address_gate_to != null)
    throw new Error("ABORT: default guard must NOT carry an addressGate — it is inherited by every group and would block public minting");
  if (cfg.default_guard.sol_payment != null)
    throw new Error("ABORT: default guard must NOT carry a solPayment (it would be inherited as an ungrouped price)");

  if (cfg.bot_tax.enabled !== false)
    throw new Error("ABORT: bot tax must not be enabled yet");

  if (cfg.wallet_limit?.per_phase_max !== MAX_PER_WALLET ||
      cfg.wallet_limit.scope !== "per_phase_independent" ||
      cfg.wallet_limit.early_counter_id !== EARLY_MINT_LIMIT_ID ||
      cfg.wallet_limit.public_counter_id !== PUBLIC_MINT_LIMIT_ID ||
      cfg.wallet_limit.combined_max !== MAX_PER_WALLET * 2)
    throw new Error("ABORT: wallet policy must be 5 per phase, independent counters (early id 1, public id 2, combined 10)");
  // Each group defines its OWN mintLimit; the two phases must NOT inherit a shared default limiter.
  if (cfg.candy_guard.mint_limit_defined_in !== "each_group")
    throw new Error("ABORT: each group must define its own mintLimit (not a shared default one)");
  if (cfg.candy_guard.groups_inherit_default_mint_limit !== false)
    throw new Error("ABORT: groups must NOT inherit a default mintLimit (independent caps required)");
  // Distinctness first (before the two equalities narrow the literal types), then pin the
  // exact ids. Distinct ids => distinct MintCounter PDAs => independent per-phase counters.
  if (cfg.candy_guard.mint_limit_ids.early === cfg.candy_guard.mint_limit_ids.public)
    throw new Error("ABORT: early/public mintLimit ids must differ (independent counters)");
  if (cfg.candy_guard.mint_limit_ids.early !== EARLY_MINT_LIMIT_ID ||
      cfg.candy_guard.mint_limit_ids.public !== PUBLIC_MINT_LIMIT_ID)
    throw new Error("ABORT: mint_limit_ids must be early 1, public 2");
  if (cfg.candy_guard.groups.join(",") !== "early,public")
    throw new Error("ABORT: unexpected mint group");
  if (cfg.state !== "PREPARED_NOT_DEPLOYED" || cfg.wallet_limit.enforcement_status !== "PDA_VERIFIED_PROGRAM_UNTESTED")
    throw new Error("ABORT: program enforcement is untested offline; keep prepared state");
}

function assertGroup(g: GroupPricing, label: GroupLabel, expectedLamports: bigint, expectedMintLimitId: number): void {
  if (g.label !== label) throw new Error(`ABORT: group label ${g.label} != ${label}`);
  if (BigInt(g.price_lamports) !== expectedLamports)
    throw new Error(`ABORT: ${label} price ${g.price_lamports} != ${expectedLamports} lamports`);
  // SOL/lamports must agree exactly.
  if (BigInt(Math.round(g.price_sol * 1e9)) !== BigInt(g.price_lamports))
    throw new Error(`ABORT: ${label} price_sol ${g.price_sol} != ${g.price_lamports} lamports`);
  if (g.destination !== TREASURY) throw new Error(`ABORT: ${label} destination ${g.destination} != treasury`);
  if (g.wallet_limit_scope !== "independent")
    throw new Error(`ABORT: ${label} wallet_limit_scope must be 'independent' (per-phase cap)`);
  if (g.mint_limit_id !== expectedMintLimitId)
    throw new Error(`ABORT: ${label} mint_limit_id ${g.mint_limit_id} != ${expectedMintLimitId}`);
  if (g.max_per_wallet !== MAX_PER_WALLET)
    throw new Error(`ABORT: ${label} max_per_wallet ${g.max_per_wallet} != ${MAX_PER_WALLET}`);
}

export const lamportsToSol = (l: bigint): string => {
  const whole = l / LAMPORTS_PER_SOL;
  const frac = (l % LAMPORTS_PER_SOL).toString().padStart(9, "0").replace(/0+$/, "");
  return frac ? `${whole}.${frac}` : `${whole}`;
};
