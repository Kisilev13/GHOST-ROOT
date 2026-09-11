/**
 * REAL production transaction BUILDERS for the two-tier launch, using the installed
 * mpl-core-candy-machine SDK. These construct umi TransactionBuilders (createCandyMachine
 * + createCandyGuard/wrap, addConfigLines, updateCandyGuard) that WOULD deploy the launch.
 *
 * HARD EXECUTION GATE — this module NEVER signs or sends:
 *   - No function here calls `.sendAndConfirm(...)`, `.send(...)`, or `.buildAndSign(...)`.
 *   - Builders that need account rent (`create`) require a LIVE rpc and are therefore
 *     invoked ONLY at real deploy time behind dual authorization — NOT in the offline
 *     dry-run or tests (which run with no rpc under offline_guard.cjs).
 *   - Callers must keep state PREPARED_NOT_DEPLOYED until every launch blocker clears and
 *     explicit mainnet authorization is given; only then may the returned builders be
 *     signed and sent by a separate, human-run step.
 *
 * Importing this module executes no SDK calls; it is safe under the offline guard.
 */
import {
  some,
  none,
  dateTime,
  lamports,
  publicKey,
  type Umi,
  type Signer,
  type PublicKey,
  type TransactionBuilder,
} from "@metaplex-foundation/umi";
import {
  create,
  addConfigLines,
  updateCandyGuard,
  DEFAULT_CONFIG_LINE_SETTINGS,
  type DefaultGuardSetArgs,
  type ConfigLineArgs,
} from "@metaplex-foundation/mpl-core-candy-machine";
import { type LaunchConfig } from "./launch_config.js";

/** Context after `umi.use(mplCoreCandyMachine())` — carries the coreGuards repository. */
type GuardUmi = Parameters<typeof create>[0];

/** Convert a 64-char hex merkle root to a 32-byte array (allowList guard input). */
export function hexToBytes32(hex: string): Uint8Array {
  const h = hex.startsWith("0x") ? hex.slice(2) : hex;
  if (!/^[0-9a-fA-F]{64}$/.test(h)) throw new Error("ABORT: merkle root must be 64 hex chars (32 bytes)");
  const out = new Uint8Array(32);
  for (let i = 0; i < 32; i++) out[i] = parseInt(h.slice(i * 2, i * 2 + 2), 16);
  return out;
}

export interface GuardData {
  guards: Partial<DefaultGuardSetArgs>;
  groups: Array<{ label: string; guards: Partial<DefaultGuardSetArgs> }>;
}

/**
 * Build the SDK guard arguments from validated config: the shared lifetime mintLimit lives
 * ONLY in the default set (both groups inherit it); each group carries its own solPayment
 * and — when a real value exists — its startDate / allowList. Dates and roots that are null
 * are OMITTED, never invented.
 */
export function buildGuardData(cfg: LaunchConfig): GuardData {
  const guards: Partial<DefaultGuardSetArgs> = {
    mintLimit: some({ id: cfg.default_guard.mint_limit.id, limit: cfg.default_guard.mint_limit.limit }),
  };

  const early: Partial<DefaultGuardSetArgs> = {
    solPayment: some({
      lamports: lamports(BigInt(cfg.pricing.early.price_lamports)),
      destination: publicKey(cfg.pricing.early.destination),
    }),
  };
  if (cfg.pricing.early.start_date) early.startDate = some({ date: dateTime(cfg.pricing.early.start_date) });
  if (cfg.pricing.early.end_date) early.endDate = some({ date: dateTime(cfg.pricing.early.end_date) });
  if (cfg.pricing.early.merkle_root) early.allowList = some({ merkleRoot: hexToBytes32(cfg.pricing.early.merkle_root) });

  const pub: Partial<DefaultGuardSetArgs> = {
    solPayment: some({
      lamports: lamports(BigInt(cfg.pricing.public.price_lamports)),
      destination: publicKey(cfg.pricing.public.destination),
    }),
  };
  if (cfg.pricing.public.start_date) pub.startDate = some({ date: dateTime(cfg.pricing.public.start_date) });
  if (cfg.pricing.public.end_date) pub.endDate = some({ date: dateTime(cfg.pricing.public.end_date) });

  return { guards, groups: [{ label: "early", guards: early }, { label: "public", guards: pub }] };
}

export interface CreateLaunchParams {
  candyMachine: Signer;              // fresh signer for the CM account
  collection: PublicKey;            // existing GHOST//ROOT Core collection
  collectionUpdateAuthority: Signer; // signs to approve the CM delegate
  itemsAvailable: number;           // 3332 (IDs 0002-3333)
  cfg: LaunchConfig;
}

/**
 * Steps 1-5: create the Core Candy Machine, create + wrap the Candy Guard, and configure
 * the default mintLimit + early/public groups — in ONE TransactionBuilder via `create`.
 * ASYNC + needs LIVE rpc (rent lookup): call ONLY at real deploy time. Never sends.
 */
export async function buildCreateMachineAndGuardTx(umi: GuardUmi, p: CreateLaunchParams): Promise<TransactionBuilder> {
  const { guards, groups } = buildGuardData(p.cfg);
  return create(umi, {
    candyMachine: p.candyMachine,
    collection: p.collection,
    collectionUpdateAuthority: p.collectionUpdateAuthority,
    itemsAvailable: BigInt(p.itemsAvailable),
    isMutable: true,
    configLineSettings: some(DEFAULT_CONFIG_LINE_SETTINGS),
    hiddenSettings: none(),
    guards,
    groups,
  });
  // NOTE: no .sendAndConfirm — the returned builder is inspected/authorized separately.
}

export interface ConfigLineChunk { index: number; lines: ConfigLineArgs[]; }

/** Split ordered config lines into resumable chunks for addConfigLines (default 10/tx). */
export function chunkConfigLines(lines: ConfigLineArgs[], startIndex = 0, linesPerTx = 10): ConfigLineChunk[] {
  if (linesPerTx <= 0) throw new Error("ABORT: linesPerTx must be > 0");
  const chunks: ConfigLineChunk[] = [];
  for (let i = 0; i < lines.length; i += linesPerTx)
    chunks.push({ index: startIndex + i, lines: lines.slice(i, i + linesPerTx) });
  return chunks;
}

/**
 * Step 6: build one addConfigLines TransactionBuilder per chunk (resumable by index).
 * Pure builder construction — no rpc, no send. The caller sends each in order at deploy time.
 */
export function buildAddConfigLinesTxs(
  umi: GuardUmi,
  candyMachine: PublicKey,
  chunks: ConfigLineChunk[],
): TransactionBuilder[] {
  return chunks.map((c) =>
    addConfigLines(umi, { candyMachine, index: c.index, configLines: c.lines }),
  );
}

/**
 * Step 7: build the updateCandyGuard TransactionBuilder that sets the (previously null)
 * launch dates once they exist in config. Never sends. Returns null if there is nothing to
 * update (dates still null), so the caller cannot accidentally push an empty guard update.
 */
export function buildUpdateGuardDatesTx(
  umi: GuardUmi,
  candyGuard: PublicKey,
  cfg: LaunchConfig,
): TransactionBuilder | null {
  if (cfg.pricing.early.start_date == null && cfg.pricing.public.start_date == null) return null;
  const { guards, groups } = buildGuardData(cfg);
  return updateCandyGuard(umi, { candyGuard, guards, groups });
  // NOTE: no .sendAndConfirm.
}
