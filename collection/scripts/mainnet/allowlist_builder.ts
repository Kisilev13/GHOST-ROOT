/**
 * Deterministic allowlist builder for the EARLY group.
 *
 * Responsibilities (all reproducible from signed source records):
 *   - read wallet addresses from a CSV
 *   - validate each is a real Solana ed25519 public key (base58, 32 bytes)
 *   - deduplicate (stable, sorted) so the same source always yields the same set
 *   - record a source hash (sha256 of the canonical sorted wallet list)
 *   - produce the merkle root/proof data ONLY when real wallets exist
 *
 * It NEVER fabricates wallets and NEVER invents a merkle root. With an empty or
 * missing CSV it returns state PENDING_ALLOWLIST and a null root, which keeps the
 * early group structurally ready while blocking its activation.
 *
 * Merkle construction, when wallets exist, is delegated to the official
 * @metaplex-foundation/mpl-core-candy-machine helpers (loaded lazily) rather than
 * inventing a hashing scheme that could mismatch the on-chain allowList guard.
 */
import { createHash } from "node:crypto";
import { existsSync, readFileSync } from "node:fs";

const BASE58_ALPHABET = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";

/** Pure base58 decode. Returns null if any char is invalid. */
export function base58Decode(str: string): Uint8Array | null {
  if (str.length === 0) return null;
  const bytes: number[] = [0];
  for (const ch of str) {
    const value = BASE58_ALPHABET.indexOf(ch);
    if (value === -1) return null;
    let carry = value;
    for (let j = 0; j < bytes.length; j++) {
      carry += bytes[j] * 58;
      bytes[j] = carry & 0xff;
      carry >>= 8;
    }
    while (carry > 0) { bytes.push(carry & 0xff); carry >>= 8; }
  }
  // Leading '1's are leading zero bytes.
  for (let k = 0; k < str.length && str[k] === "1"; k++) bytes.push(0);
  return Uint8Array.from(bytes.reverse());
}

/** A valid Solana public key is base58 that decodes to exactly 32 bytes. */
export function isValidSolanaPubkey(s: string): boolean {
  const t = s.trim();
  if (!/^[1-9A-HJ-NP-Za-km-z]{32,44}$/.test(t)) return false;
  const decoded = base58Decode(t);
  return decoded != null && decoded.length === 32;
}

export interface AllowlistResult {
  status: "PENDING_ALLOWLIST" | "READY";
  wallets: string[];
  count: number;
  invalid: string[];
  duplicatesRemoved: number;
  sourceHash: string | null;
  merkleRoot: string | null;
  notes: string[];
}

/** Parse a CSV of wallet addresses. Accepts one address per line or a `wallet` column. */
export function parseWalletCsv(text: string): string[] {
  const lines = text.split(/\r?\n/).map((l) => l.trim()).filter((l) => l.length > 0);
  if (lines.length === 0) return [];
  const out: string[] = [];
  // Detect + skip a header row.
  const header = lines[0].toLowerCase();
  const hasHeader = header.includes("wallet") || header.includes("address") || header.includes("pubkey");
  for (let i = hasHeader ? 1 : 0; i < lines.length; i++) {
    const first = lines[i].split(",")[0].trim();
    if (first && !first.startsWith("#")) out.push(first);
  }
  return out;
}

/** Canonical source hash: sha256 over the newline-joined sorted unique wallet list. */
export function sourceHash(sortedUnique: string[]): string {
  return createHash("sha256").update(sortedUnique.join("\n"), "utf8").digest("hex");
}

/**
 * Build the allowlist result from raw wallet strings. Pure except for the optional
 * lazy merkle step. `buildMerkle` defaults false so callers can dedup/validate
 * without pulling the candy-machine SDK; the deploy path opts in only when wallets
 * actually exist.
 */
export async function buildAllowlist(raw: string[], opts: { buildMerkle?: boolean } = {}): Promise<AllowlistResult> {
  const notes: string[] = [];
  const invalid: string[] = [];
  const seen = new Set<string>();
  let duplicatesRemoved = 0;

  for (const r of raw) {
    const w = r.trim();
    if (!w) continue;
    if (!isValidSolanaPubkey(w)) { invalid.push(w); continue; }
    if (seen.has(w)) { duplicatesRemoved++; continue; }
    seen.add(w);
  }

  const wallets = [...seen].sort(); // stable, deterministic order

  if (invalid.length > 0)
    throw new Error(`ABORT: ${invalid.length} invalid Solana public key(s) in allowlist source: ${invalid.slice(0, 5).join(", ")}${invalid.length > 5 ? " …" : ""}`);

  if (wallets.length === 0) {
    notes.push("No wallets present — allowlist stays PENDING_ALLOWLIST. No merkle root produced.");
    return { status: "PENDING_ALLOWLIST", wallets: [], count: 0, invalid: [], duplicatesRemoved, sourceHash: null, merkleRoot: null, notes };
  }

  const hash = sourceHash(wallets);
  notes.push(`${wallets.length} unique wallet(s); ${duplicatesRemoved} duplicate(s) removed; source sha256 ${hash}.`);

  let merkleRoot: string | null = null;
  if (opts.buildMerkle) {
    // Delegate to the audited metaplex helper — never invent the hashing scheme.
    // Declared in package.json; installed via `npm install` before real deployment.
    // @ts-ignore lazy optional dependency, not resolved in the prepared (uninstalled) state
    const { getMerkleRoot } = await import("@metaplex-foundation/mpl-core-candy-machine");
    const rootBytes = getMerkleRoot(wallets) as Uint8Array;
    merkleRoot = Buffer.from(rootBytes).toString("hex");
    notes.push(`merkle root ${merkleRoot} (via mpl-core-candy-machine getMerkleRoot).`);
  } else {
    notes.push("Merkle root not built (buildMerkle=false); run with --build-merkle once wallets are frozen.");
  }

  return { status: "READY", wallets, count: wallets.length, invalid: [], duplicatesRemoved, sourceHash: hash, merkleRoot, notes };
}

/** Load + build directly from a CSV path. Missing file => PENDING_ALLOWLIST. */
export async function buildAllowlistFromCsv(path: string, opts: { buildMerkle?: boolean } = {}): Promise<AllowlistResult> {
  if (!existsSync(path)) {
    return { status: "PENDING_ALLOWLIST", wallets: [], count: 0, invalid: [], duplicatesRemoved: 0, sourceHash: null, merkleRoot: null,
      notes: [`No allowlist CSV at ${path} — PENDING_ALLOWLIST.`] };
  }
  const raw = parseWalletCsv(readFileSync(path, "utf8"));
  return buildAllowlist(raw, opts);
}
