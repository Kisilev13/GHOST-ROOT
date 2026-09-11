#!/usr/bin/env -S npx tsx
/**
 * GHOST//ROOT — independent on-chain read-back of the devnet canary rehearsal.
 *
 * Does not trust deploy_devnet_canary.ts's own stdout: re-fetches the
 * collection and asset accounts fresh from the RPC and checks every field
 * the task's success condition cares about. Exits non-zero on any mismatch.
 *
 * Usage:
 *   npx tsx verify_devnet_canary.ts --url <rpc> --receipt ../../devnet/deployment-receipt.json
 */
import { readFileSync } from "node:fs";
import { createUmi } from "@metaplex-foundation/umi-bundle-defaults";
import { fetchCollectionV1, fetchAssetV1 } from "@metaplex-foundation/mpl-core";
import { publicKey as toPublicKey } from "@metaplex-foundation/umi";

function parseArgs() {
  const argv = process.argv.slice(2);
  const get = (flag: string) => {
    const i = argv.indexOf(flag);
    return i >= 0 ? argv[i + 1] : undefined;
  };
  const url = get("--url");
  const receiptPath = get("--receipt");
  if (!url || !receiptPath) throw new Error("--url and --receipt are both required.");
  return { url, receiptPath };
}

async function main() {
  const { url, receiptPath } = parseArgs();
  const receipt = JSON.parse(readFileSync(receiptPath, "utf8"));
  const umi = createUmi(url);

  const checks: { name: string; ok: boolean; detail?: string }[] = [];
  const check = (name: string, ok: boolean, detail?: string) => {
    checks.push({ name, ok, detail });
    console.log(`${ok ? "PASS" : "FAIL"}  ${name}${detail ? " — " + detail : ""}`);
  };

  const genesisRes = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ jsonrpc: "2.0", id: 1, method: "getGenesisHash" }),
  });
  const genesis = ((await genesisRes.json()) as { result?: string }).result;
  check(
    "network is not mainnet-beta",
    genesis !== "5eykt4UsFv8P8NJdTREpY1vzqKqZKvdpKuc147dw2N9d",
    `genesis hash ${genesis}`
  );

  const collection = await fetchCollectionV1(umi, toPublicKey(receipt.collection.address));
  check("collection account exists and deserializes", !!collection);
  check("collection name is correct", collection.name === "GHOST//ROOT DEVNET", collection.name);
  check("collection uri matches receipt", collection.uri === receipt.collection.uri, collection.uri);
  check(
    "collection update authority matches devnet signer",
    collection.updateAuthority === receipt.devnet_signer_public_key,
    collection.updateAuthority
  );
  const royalties = collection.royalties;
  check("collection has a Royalties plugin", !!royalties);
  check(
    "royalty basis points = 500",
    royalties?.basisPoints === 500,
    String(royalties?.basisPoints)
  );
  check(
    "royalty recipient matches devnet signer",
    royalties?.creators?.[0]?.address === receipt.devnet_signer_public_key,
    royalties?.creators?.[0]?.address
  );

  const asset = await fetchAssetV1(umi, toPublicKey(receipt.canary.asset_address));
  check("canary asset account exists and deserializes", !!asset);
  check("canary name is 'GHOST//0001'", asset.name === "GHOST//0001", asset.name);
  check("canary uri matches receipt", asset.uri === receipt.canary.uri, asset.uri);
  check(
    "canary belongs to the expected collection",
    asset.updateAuthority?.type === "Collection" &&
      asset.updateAuthority.address === receipt.collection.address,
    JSON.stringify(asset.updateAuthority)
  );
  check("canary owner matches devnet signer", asset.owner === receipt.devnet_signer_public_key, asset.owner);

  // Fetch the metadata JSON and image URI to confirm they actually resolve
  // (not just that the on-chain uri string is well-formed).
  const metaRes = await fetch(asset.uri);
  check("canary metadata URI resolves (HTTP 200)", metaRes.ok, `HTTP ${metaRes.status}`);
  const metaJson = metaRes.ok ? await metaRes.json() : null;
  check("canary metadata name matches on-chain name", metaJson?.name === asset.name, metaJson?.name);
  if (metaJson?.image) {
    const imgRes = await fetch(metaJson.image, { method: "HEAD" });
    check("canary image URI resolves", imgRes.ok, `HTTP ${imgRes.status}`);
  } else {
    check("canary metadata has an image field", false);
  }

  const failed = checks.filter((c) => !c.ok);
  console.log(`\n${checks.length - failed.length}/${checks.length} checks passed`);
  process.exit(failed.length ? 1 : 0);
}

main().catch((err) => {
  console.error("VERIFICATION ERROR:", err instanceof Error ? err.message : err);
  process.exit(1);
});
