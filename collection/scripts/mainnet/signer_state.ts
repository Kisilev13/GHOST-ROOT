/**
 * Persistent deployment-signer state for crash-safe idempotency.
 *
 * The collection Core account signer and the GHOST//0001 asset Core account
 * signer are generated ONCE and persisted to disk BEFORE any transaction is
 * broadcast. On every rerun the SAME two signers (and therefore the same two
 * on-chain addresses) are reconstructed, so the script can query the chain and
 * refuse to create a second collection or mint a second canary.
 *
 * These are throwaway *account* signers (they own nothing after creation — the
 * update authority + owner are the production wallet). They are NOT the
 * production wallet keypair. Still: stored 0600 in a 0700 dir, gitignored,
 * never printed, never committed.
 */
import { chmodSync, existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { createSignerFromKeypair, type KeypairSigner, type Umi } from "@metaplex-foundation/umi";

const HERE = dirname(fileURLToPath(import.meta.url));
export const PRIVATE_DIR = `${HERE}/../../mainnet/.private`;
const STATE_FILE = `${PRIVATE_DIR}/deploy-signers.json`;

interface StoredKp { publicKey: string; secretKey: number[]; }
interface StoredState { created_at_utc: string; collection: StoredKp; asset: StoredKp; }

function reconstruct(umi: Umi, s: StoredKp): KeypairSigner {
  const kp = umi.eddsa.createKeypairFromSecretKey(Uint8Array.from(s.secretKey));
  const signer = createSignerFromKeypair(umi, kp);
  if (signer.publicKey !== s.publicKey)
    throw new Error(`ABORT: persisted signer pubkey mismatch (${signer.publicKey} != ${s.publicKey}).`);
  return signer;
}

/**
 * Load the persisted collection + asset signers, generating and persisting them
 * on first call. Idempotent: same addresses forever once written.
 */
export function loadOrCreateDeploySigners(umi: Umi): { collection: KeypairSigner; asset: KeypairSigner; firstRun: boolean } {
  if (existsSync(STATE_FILE)) {
    const st = JSON.parse(readFileSync(STATE_FILE, "utf8")) as StoredState;
    return { collection: reconstruct(umi, st.collection), asset: reconstruct(umi, st.asset), firstRun: false };
  }
  mkdirSync(PRIVATE_DIR, { recursive: true, mode: 0o700 });
  try { chmodSync(PRIVATE_DIR, 0o700); } catch { /* best effort */ }
  const collKp = umi.eddsa.generateKeypair();
  const assetKp = umi.eddsa.generateKeypair();
  const state: StoredState = {
    created_at_utc: new Date().toISOString(),
    collection: { publicKey: collKp.publicKey, secretKey: Array.from(collKp.secretKey) },
    asset: { publicKey: assetKp.publicKey, secretKey: Array.from(assetKp.secretKey) },
  };
  writeFileSync(STATE_FILE, JSON.stringify(state, null, 2) + "\n", { mode: 0o600 });
  try { chmodSync(STATE_FILE, 0o600); } catch { /* best effort */ }
  return {
    collection: createSignerFromKeypair(umi, collKp),
    asset: createSignerFromKeypair(umi, assetKp),
    firstRun: true,
  };
}
