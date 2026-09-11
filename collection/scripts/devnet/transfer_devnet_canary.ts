#!/usr/bin/env -S npx tsx
/**
 * GHOST//ROOT — optional devnet transfer test (Phase 13).
 *
 * Transfers the already-minted GHOST//0001 devnet canary to a second
 * devnet-only wallet exactly once, then independently re-fetches the asset
 * to confirm the new owner. Creates no new NFT. Same mainnet guards as
 * deploy_devnet_canary.ts.
 *
 * Usage:
 *   npx tsx transfer_devnet_canary.ts \
 *     --cluster local-devnet-clone --url http://127.0.0.1:8899 \
 *     --keypair /home/hacker/.config/solana/ghost-root-devnet.json \
 *     --new-owner DAYR8dzFKv8Q2epVmCqH2SVXHfTzjRQVfAix6bgZWZYP \
 *     --receipt ../../devnet/deployment-receipt.json
 */
import { readFileSync, writeFileSync } from "node:fs";
import { base58, createSignerFromKeypair, keypairIdentity, publicKey as toPublicKey } from "@metaplex-foundation/umi";
import { createUmi } from "@metaplex-foundation/umi-bundle-defaults";
import { fetchAssetV1, fetchCollectionV1, transfer } from "@metaplex-foundation/mpl-core";

const MAINNET_BETA_GENESIS_HASH = "5eykt4UsFv8P8NJdTREpY1vzqKqZKvdpKuc147dw2N9d";
const ALLOWED_CLUSTERS = new Set(["devnet", "local-devnet-clone"]);

function parseArgs() {
  const argv = process.argv.slice(2);
  const get = (flag: string) => {
    const i = argv.indexOf(flag);
    return i >= 0 ? argv[i + 1] : undefined;
  };
  const cluster = get("--cluster");
  const url = get("--url");
  const keypair = get("--keypair");
  const newOwner = get("--new-owner");
  const receiptPath = get("--receipt");
  if (!cluster || !url || !keypair || !newOwner || !receiptPath) {
    throw new Error("--cluster, --url, --keypair, --new-owner, and --receipt are all required.");
  }
  return { cluster, url, keypair, newOwner, receiptPath };
}

async function assertNotMainnet(url: string, cluster: string) {
  if (!ALLOWED_CLUSTERS.has(cluster)) throw new Error(`Refusing to run: --cluster '${cluster}' not allow-listed.`);
  if (/mainnet/i.test(url)) throw new Error(`Refusing to run: --url '${url}' looks like mainnet.`);
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ jsonrpc: "2.0", id: 1, method: "getGenesisHash" }),
  });
  const result = ((await res.json()) as { result?: string }).result;
  if (result === MAINNET_BETA_GENESIS_HASH) throw new Error("ABORT: mainnet-beta genesis hash detected.");
}

async function main() {
  const { cluster, url, keypair: keypairPath, newOwner, receiptPath } = parseArgs();
  await assertNotMainnet(url, cluster);

  const receipt = JSON.parse(readFileSync(receiptPath, "utf8"));
  const secretKey = Uint8Array.from(JSON.parse(readFileSync(keypairPath, "utf8")) as number[]);
  const umi = createUmi(url);
  const kp = umi.eddsa.createKeypairFromSecretKey(secretKey);
  const signer = createSignerFromKeypair(umi, kp);
  umi.use(keypairIdentity(signer));

  const assetAddress = toPublicKey(receipt.canary.asset_address);
  const collectionAddress = toPublicKey(receipt.collection.address);
  const asset = await fetchAssetV1(umi, assetAddress);
  const collection = await fetchCollectionV1(umi, collectionAddress);
  console.log(`Before transfer: owner = ${asset.owner}`);

  const tx = await transfer(umi, {
    asset,
    collection,
    newOwner: toPublicKey(newOwner),
  }).sendAndConfirm(umi, { confirm: { commitment: "finalized" } });
  if (tx.result.value.err) {
    throw new Error(`Transfer transaction failed on-chain: ${JSON.stringify(tx.result.value.err)}`);
  }
  const sig = base58.deserialize(tx.signature)[0];
  console.log(`Transfer tx: ${sig}`);

  const after = await fetchAssetV1(umi, assetAddress);
  console.log(`After transfer: owner = ${after.owner}`);
  if (after.owner !== newOwner) {
    throw new Error(`Verification failed: expected owner ${newOwner}, got ${after.owner}`);
  }
  console.log("PASS: new owner independently confirmed on-chain.");

  receipt.transfer_test = {
    label: "DEVNET ONLY — optional Phase 13 transfer rehearsal",
    from_owner: asset.owner,
    to_owner: after.owner,
    transaction_signature: sig,
    verified: after.owner === newOwner,
  };
  writeFileSync(receiptPath, JSON.stringify(receipt, null, 2) + "\n");
  console.log(`Updated ${receiptPath} with transfer_test.`);
}

main().catch((err) => {
  console.error("TRANSFER TEST FAILED:", err instanceof Error ? err.message : err);
  process.exit(1);
});
