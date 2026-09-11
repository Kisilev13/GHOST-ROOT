/**
 * Shared mainnet safety guards for the GHOST//ROOT production canary.
 *
 * These are deliberately strict and duplicated across every entry point. There is
 * NO fallback signer, NO default keypair, NO devnet reuse. A production script
 * that cannot positively confirm mainnet-beta + the exact expected signer ABORTS.
 */
import { readFileSync } from "node:fs";
import {
  createSignerFromKeypair,
  keypairIdentity,
  type Umi,
} from "@metaplex-foundation/umi";
import { createUmi } from "@metaplex-foundation/umi-bundle-defaults";

export const MAINNET_BETA_GENESIS_HASH =
  "5eykt4UsFv8P8NJdTREpY1vzqKqZKvdpKuc147dw2N9d";
export const EXPECTED_SIGNER =
  "CcgTzAmyog1h3tUnR7zqtQ2bZzQSYL9L9xZHDBvtD94R";
export const ROYALTY_BPS = 500; // 5% — never 5000
export const COLLECTION_NAME = "GHOST//ROOT";
export const SYMBOL = "GHRT";

export interface CommonArgs {
  cluster: string;
  url: string;
  keypair: string;
  confirmMainnet: boolean;
}

export function parseCommon(argv: string[]): CommonArgs {
  const get = (f: string) => {
    const i = argv.indexOf(f);
    return i >= 0 ? argv[i + 1] : undefined;
  };
  const cluster = get("--cluster");
  const url = get("--url");
  const keypair = get("--keypair");
  const confirmMainnet = argv.includes("--confirm-mainnet");
  if (cluster !== "mainnet-beta")
    throw new Error("ABORT: --cluster must be exactly 'mainnet-beta'.");
  if (!url) throw new Error("ABORT: --url (explicit mainnet RPC) is required.");
  if (/devnet|testnet|127\.0\.0\.1|localhost/i.test(url))
    throw new Error(`ABORT: --url '${url}' is not a mainnet RPC.`);
  if (!keypair)
    throw new Error("ABORT: --keypair (explicit path) is required. No default signer.");
  return { cluster, url, keypair, confirmMainnet };
}

/** Independently confirm the RPC really is mainnet-beta by its genesis hash. */
export async function assertMainnetGenesis(url: string): Promise<string> {
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ jsonrpc: "2.0", id: 1, method: "getGenesisHash" }),
  });
  const hash = ((await res.json()) as { result?: string }).result;
  if (!hash)
    throw new Error(`ABORT: could not read genesis hash from '${url}'.`);
  if (hash !== MAINNET_BETA_GENESIS_HASH)
    throw new Error(
      `ABORT: RPC genesis hash ${hash} != mainnet-beta ${MAINNET_BETA_GENESIS_HASH}.`
    );
  return hash;
}

/** Load the signer, assert it is EXACTLY the expected production wallet. Never logs bytes. */
export function loadAndAssertSigner(umi: Umi, keypairPath: string) {
  const secret = Uint8Array.from(
    JSON.parse(readFileSync(keypairPath, "utf8")) as number[]
  );
  const kp = umi.eddsa.createKeypairFromSecretKey(secret);
  const signer = createSignerFromKeypair(umi, kp);
  if (signer.publicKey !== EXPECTED_SIGNER)
    throw new Error(
      `ABORT: signer ${signer.publicKey} != expected ${EXPECTED_SIGNER}. No fallback signer.`
    );
  umi.use(keypairIdentity(signer));
  return signer;
}

/** Build a guarded Umi bound to mainnet + the verified signer. Confirms genesis + signer. */
export async function guardedUmi(args: CommonArgs): Promise<{ umi: Umi; signerPk: string; genesis: string }> {
  const genesis = await assertMainnetGenesis(args.url);
  const umi = createUmi(args.url);
  const signer = loadAndAssertSigner(umi, args.keypair);
  return { umi, signerPk: signer.publicKey, genesis };
}
