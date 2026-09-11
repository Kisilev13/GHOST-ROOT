#!/usr/bin/env -S npx tsx
/**
 * GHOST//ROOT — guarded Solana devnet canary rehearsal.
 *
 * Creates ONE disposable Metaplex Core collection ("GHOST//ROOT DEVNET") with
 * a 500 bps royalties plugin, and mints exactly ONE canary asset
 * ("GHOST//0001") attached to it, using the real validated trait vector and a
 * real reachable metadata/image URI (never `pending://`).
 *
 * This script REFUSES to run against mainnet. It is not a general-purpose
 * deployer: production deployment is a separate, future, explicitly-reviewed
 * workflow — this file must never be pointed at mainnet by "just changing a
 * URL". Every guard below is redundant with the others on purpose.
 *
 * Usage:
 *   npx tsx deploy_devnet_canary.ts \
 *     --cluster devnet \
 *     --url https://api.devnet.solana.com \
 *     --keypair /home/hacker/.config/solana/ghost-root-devnet.json
 *
 * The devnet public faucet was confirmed dry at rehearsal time (direct RPC
 * error: "the airdrop faucet has run dry", not merely a per-IP limit), and
 * the official faucet's own AI-agent guidance names a local validator as the
 * third sanctioned fallback. So this rehearsal's --cluster is normally
 * "local-devnet-clone" pointed at a `solana-test-validator` that CLONED the
 * real mpl-core program live from public devnet (same program, same
 * behavior, zero mainnet exposure) — never a silent, unlabeled substitution.
 */
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { dirname } from "node:path";
import { fileURLToPath } from "node:url";
import {
  base58,
  createSignerFromKeypair,
  generateSigner,
  keypairIdentity,
  sol,
} from "@metaplex-foundation/umi";
import { createUmi } from "@metaplex-foundation/umi-bundle-defaults";
import { create, createCollection, fetchCollectionV1, fetchAssetV1 } from "@metaplex-foundation/mpl-core";

// Solana mainnet-beta's genesis hash never changes. If a target RPC reports
// this hash, it IS mainnet, no matter what --cluster or --url claim — this
// check cannot be fooled by a typo'd or spoofed URL string.
const MAINNET_BETA_GENESIS_HASH = "5eykt4UsFv8P8NJdTREpY1vzqKqZKvdpKuc147dw2N9d";
const ALLOWED_CLUSTERS = new Set(["devnet", "local-devnet-clone"]);

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = new URL("../../../", `file://${HERE}/`).pathname;

type Args = { cluster: string; url: string; keypair: string };

function parseArgs(): Args {
  const argv = process.argv.slice(2);
  const get = (flag: string): string | undefined => {
    const i = argv.indexOf(flag);
    return i >= 0 ? argv[i + 1] : undefined;
  };
  const cluster = get("--cluster");
  const url = get("--url");
  const keypair = get("--keypair") ?? process.env.DEVNET_KEYPAIR_PATH;
  if (!cluster) throw new Error("--cluster is required (devnet | local-devnet-clone). Refusing to guess.");
  if (!url) throw new Error("--url is required (the RPC endpoint). Refusing to default to any built-in URL.");
  if (!keypair) throw new Error("--keypair (or DEVNET_KEYPAIR_PATH) is required. Refusing to fall back to any default signer.");
  return { cluster, url, keypair };
}

async function assertNotMainnet(url: string, cluster: string): Promise<void> {
  if (!ALLOWED_CLUSTERS.has(cluster)) {
    throw new Error(`Refusing to run: --cluster '${cluster}' is not in the allow-list [${[...ALLOWED_CLUSTERS].join(", ")}].`);
  }
  if (/mainnet/i.test(url)) {
    throw new Error(`Refusing to run: --url '${url}' looks like mainnet by name alone. This script never deploys to mainnet.`);
  }
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ jsonrpc: "2.0", id: 1, method: "getGenesisHash" }),
  });
  const body = (await res.json()) as { result?: string };
  if (!body.result) {
    throw new Error(`Refusing to run: could not read a genesis hash from '${url}' to confirm it isn't mainnet.`);
  }
  if (body.result === MAINNET_BETA_GENESIS_HASH) {
    throw new Error(`ABORT: RPC '${url}' reports mainnet-beta's genesis hash. This script refuses to touch mainnet, full stop.`);
  }
  console.log(`Guard passed: genesis hash ${body.result} != mainnet-beta (${MAINNET_BETA_GENESIS_HASH}).`);
}

async function fetchWithRetry<T>(fn: () => Promise<T>, attempts = 6, delayMs = 500): Promise<T> {
  let lastErr: unknown;
  for (let i = 0; i < attempts; i++) {
    try {
      return await fn();
    } catch (err) {
      lastErr = err;
      await new Promise((r) => setTimeout(r, delayMs));
    }
  }
  throw lastErr;
}

async function main() {
  const { cluster, url, keypair: keypairPath } = parseArgs();
  await assertNotMainnet(url, cluster);

  // Load the signer. The private key bytes are read into memory to sign
  // transactions and are never logged, printed, or written anywhere.
  const secretKeyJson = JSON.parse(readFileSync(keypairPath, "utf8")) as number[];
  const secretKey = Uint8Array.from(secretKeyJson);

  const umi = createUmi(url);
  const kp = umi.eddsa.createKeypairFromSecretKey(secretKey);
  const signer = createSignerFromKeypair(umi, kp);
  umi.use(keypairIdentity(signer));

  const balance = await umi.rpc.getBalance(signer.publicKey);
  console.log(`Cluster: ${cluster}`);
  console.log(`RPC: ${url}`);
  console.log(`Signer (devnet-only, public): ${signer.publicKey}`);
  console.log(`Signer balance: ${Number(balance.basisPoints) / 1e9} SOL`);
  if (balance.basisPoints < sol(0.01).basisPoints) {
    throw new Error("Refusing to proceed: signer holds under 0.01 SOL, not enough for two Core transactions.");
  }

  const COLLECTION_URI =
    "https://raw.githubusercontent.com/Kisilev13/GHOST-ROOT/b4e4258fcad3b1ede358448c39a9eb7dbb29c854/collection/devnet/metadata/collection.json";
  const CANARY_URI =
    "https://raw.githubusercontent.com/Kisilev13/GHOST-ROOT/a60303f6829977d3a90ca13a234b202d9a6aa271/collection/devnet/metadata/0001.json";

  // --- Step 1: create the devnet-only Core collection, 500 bps royalty plugin ---
  const collectionSigner = generateSigner(umi);
  const royaltyBasisPoints = 500;
  const collectionTx = await createCollection(umi, {
    collection: collectionSigner,
    name: "GHOST//ROOT DEVNET",
    uri: COLLECTION_URI,
    plugins: [
      {
        type: "Royalties",
        basisPoints: royaltyBasisPoints,
        creators: [{ address: signer.publicKey, percentage: 100 }],
        ruleSet: { type: "None" },
      },
    ],
  }).sendAndConfirm(umi, { confirm: { commitment: "finalized" } });
  if (collectionTx.result.value.err) {
    throw new Error(`Collection creation transaction failed on-chain: ${JSON.stringify(collectionTx.result.value.err)}`);
  }
  const collectionSig = base58.deserialize(collectionTx.signature)[0];
  console.log(`Collection created: ${collectionSigner.publicKey} (tx ${collectionSig})`);

  // --- Step 2: mint exactly one canary asset attached to that collection ---
  // A freshly finalized account can still lag by a beat on some RPCs; retry briefly
  // rather than assume a single immediate read reflects the confirmed state.
  const collectionAccount = await fetchWithRetry(() => fetchCollectionV1(umi, collectionSigner.publicKey));
  const assetSigner = generateSigner(umi);
  const mintTx = await create(umi, {
    asset: assetSigner,
    collection: collectionAccount,
    name: "GHOST//0001",
    uri: CANARY_URI,
  }).sendAndConfirm(umi, { confirm: { commitment: "finalized" } });
  if (mintTx.result.value.err) {
    throw new Error(`Canary mint transaction failed on-chain: ${JSON.stringify(mintTx.result.value.err)}`);
  }
  const mintSig = base58.deserialize(mintTx.signature)[0];
  console.log(`Canary minted: ${assetSigner.publicKey} (tx ${mintSig})`);

  // --- Receipt ---
  const explorerCluster = cluster === "devnet" ? "?cluster=devnet" : "?cluster=custom&customUrl=" + encodeURIComponent(url);
  const receipt = {
    label: "DEVNET ONLY — holds no value, disposable rehearsal",
    generated_at_utc: new Date().toISOString(),
    cluster,
    rpc_url: url,
    explorer_note:
      cluster === "devnet"
        ? "Real public Solana devnet — standard explorer.solana.com/?cluster=devnet links resolve."
        : "Local solana-test-validator that cloned the real mpl-core program live from public devnet (public devnet's own faucet was confirmed dry at rehearsal time). Same program, same behavior, but this ledger is private to this machine — explorer.solana.com cannot resolve these addresses. Use the local RPC (http://127.0.0.1:8899) with a Solana Explorer instance pointed at a custom RPC, or `solana confirm -v <sig> --url http://127.0.0.1:8899`, to inspect it.",
    devnet_signer_public_key: signer.publicKey,
    collection: {
      address: collectionSigner.publicKey,
      name: "GHOST//ROOT DEVNET",
      uri: COLLECTION_URI,
      update_authority: signer.publicKey,
      royalty_basis_points: royaltyBasisPoints,
      royalty_recipient: signer.publicKey,
      creation_transaction_signature: collectionSig,
      explorer_address_url: `https://explorer.solana.com/address/${collectionSigner.publicKey}${explorerCluster}`,
      explorer_tx_url: `https://explorer.solana.com/tx/${collectionSig}${explorerCluster}`,
    },
    canary: {
      asset_address: assetSigner.publicKey,
      name: "GHOST//0001",
      uri: CANARY_URI,
      owner: signer.publicKey,
      collection_address: collectionSigner.publicKey,
      mint_transaction_signature: mintSig,
      explorer_address_url: `https://explorer.solana.com/address/${assetSigner.publicKey}${explorerCluster}`,
      explorer_tx_url: `https://explorer.solana.com/tx/${mintSig}${explorerCluster}`,
    },
  };
  const outDir = `${REPO_ROOT}collection/devnet`;
  mkdirSync(outDir, { recursive: true });
  writeFileSync(`${outDir}/deployment-receipt.json`, JSON.stringify(receipt, null, 2) + "\n");
  console.log(`Wrote ${outDir}/deployment-receipt.json`);
}

main().catch((err) => {
  console.error("DEPLOYMENT ABORTED:", err instanceof Error ? err.message : err);
  process.exit(1);
});
