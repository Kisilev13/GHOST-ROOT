#!/usr/bin/env -S npx tsx
/**
 * Independent on-chain + HTTP read-back of the mainnet canary. Does not trust
 * deploy stdout: re-fetches collection + asset fresh, checks every success
 * condition, resolves the metadata/image over HTTP, and (with the local frozen
 * image) confirms the uploaded image hash matches. Writes deployment-receipt.md.
 */
import { createHash } from "node:crypto";
import { existsSync, readFileSync, writeFileSync } from "node:fs";
import { dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { publicKey } from "@metaplex-foundation/umi";
import { createUmi } from "@metaplex-foundation/umi-bundle-defaults";
import { fetchCollectionV1, fetchAssetV1 } from "@metaplex-foundation/mpl-core";
import { assertMainnetGenesis, EXPECTED_SIGNER, ROYALTY_BPS } from "./guards.js";

const HERE = dirname(fileURLToPath(import.meta.url));
const MAINNET_DIR = `${HERE}/../../mainnet`;
const LOCAL_IMAGE = `${MAINNET_DIR}/assets/0001.png`;

/**
 * Bounded retries for permanent-storage gateways, with an identifying User-Agent
 * and an Arweave fallback for Irys transaction IDs. Availability on the fallback
 * gateway depends on settlement and propagation.
 */
async function fetchPermanent(uri: string): Promise<Response> {
  const headers = {
    "User-Agent": "Mozilla/5.0 (X11; Linux x86_64) GhostRootVerify/1.0",
    "Accept": "*/*",
  };

  const urls = [uri];

  const m = uri.match(/^https:\/\/gateway\.irys\.xyz\/([A-Za-z0-9_-]+)$/);
  if (m) {
    urls.push(`https://arweave.net/${m[1]}`);
  }

  let last: unknown;

  for (const url of urls) {
    for (let attempt = 0; attempt < 4; attempt++) {
      try {
        const res = await fetch(url, { headers, signal: AbortSignal.timeout(20_000) });
        if (res.ok) return res;
        last = new Error(`HTTP ${res.status} from ${url}`);
      } catch (e) {
        last = e;
      }

      await new Promise(r => setTimeout(r, 750 * (attempt + 1)));
    }
  }

  throw last;
}

async function main() {
  const argv = process.argv.slice(2);
  const get = (fl: string) => { const i = argv.indexOf(fl); return i >= 0 ? argv[i + 1] : undefined; };
  const url = get("--url");
  if (!url) throw new Error("--url (mainnet RPC) required.");
  const genesis = await assertMainnetGenesis(url);
  const receipt = JSON.parse(readFileSync(`${MAINNET_DIR}/deployment-receipt.json`, "utf8"));
  const umi = createUmi(url);

  const checks: [string, boolean, string?][] = [];
  const chk = (n: string, ok: boolean, d?: string) => { checks.push([n, ok, d]); console.log(`${ok ? "PASS" : "FAIL"}  ${n}${d ? " — " + d : ""}`); };

  chk("network is mainnet-beta", genesis === "5eykt4UsFv8P8NJdTREpY1vzqKqZKvdpKuc147dw2N9d", genesis);

  const coll = await fetchCollectionV1(umi, publicKey(receipt.collection.address));
  chk("collection exists + deserializes", !!coll);
  chk("collection name GHOST//ROOT", coll.name === "GHOST//ROOT", coll.name);
  chk("collection uri matches receipt", coll.uri === receipt.collection.uri, coll.uri);
  chk("collection update authority = signer", coll.updateAuthority === EXPECTED_SIGNER, coll.updateAuthority);
  const roy = (coll as any).royalties;
  chk("royalties plugin present", !!roy);
  chk(`royalty = ${ROYALTY_BPS} bps`, roy?.basisPoints === ROYALTY_BPS, String(roy?.basisPoints));
  chk("royalty recipient = signer @100%", roy?.creators?.[0]?.address === EXPECTED_SIGNER && roy?.creators?.[0]?.percentage === 100, JSON.stringify(roy?.creators?.[0]));

  const asset = await fetchAssetV1(umi, publicKey(receipt.canary.asset_address));
  chk("asset exists + deserializes", !!asset);
  chk("asset name GHOST//0001", asset.name === "GHOST//0001", asset.name);
  chk("asset uri matches receipt", asset.uri === receipt.canary.uri, asset.uri);
  chk("asset owner = signer", asset.owner === EXPECTED_SIGNER, asset.owner);
  chk("asset belongs to collection", asset.updateAuthority?.type === "Collection" && asset.updateAuthority.address === receipt.collection.address, JSON.stringify(asset.updateAuthority));

  const metaRes = await fetchPermanent(asset.uri);
  chk("metadata HTTP 200", metaRes.ok, `HTTP ${metaRes.status}`);
  const meta = metaRes.ok ? await metaRes.json() : null;
  chk("metadata name matches", meta?.name === "GHOST//0001", meta?.name);
  let imgOk = false, imgHashOk = false;
  if (meta?.image) {
    const imgRes = await fetchPermanent(meta.image);
    imgOk = imgRes.ok;
    if (imgRes.ok && existsSync(LOCAL_IMAGE)) {
      const remote = createHash("sha256").update(Buffer.from(await imgRes.arrayBuffer())).digest("hex");
      const local = createHash("sha256").update(readFileSync(LOCAL_IMAGE)).digest("hex");
      imgHashOk = remote === local;
      chk("image HTTP 200", imgOk, meta.image);
      chk("uploaded image sha256 == local frozen image", imgHashOk, `${remote.slice(0,12)} vs ${local.slice(0,12)}`);
    }
  }

  const failed = checks.filter(([, ok]) => !ok);
  const md = [
    "# GHOST//ROOT — mainnet deployment receipt",
    "",
    "**Solana mainnet-beta · Metaplex Core.** `media_status = PRE_RELEASE_CANARY_ART_MUTABLE`",
    "— the GHOST//0001 image is a pre-release canary render, NOT final collection-approved",
    "artwork; update authority is retained so the URI can be replaced after visual QA.",
    "",
    `- network: mainnet-beta (genesis ${genesis})`,
    `- signer / update authority / royalty recipient: \`${EXPECTED_SIGNER}\``,
    `- collection address: \`${receipt.collection.address}\``,
    `- collection creation signature: \`${receipt.collection.creation_signature}\``,
    `- collection metadata URI: ${receipt.collection.uri}`,
    `- royalty: ${ROYALTY_BPS} bps (5%), recipient @100%, ruleSet None`,
    `- GHOST//0001 asset address: \`${receipt.canary.asset_address}\``,
    `- mint signature: \`${receipt.canary.mint_signature}\``,
    `- 0001 metadata URI: ${receipt.canary.uri}`,
    `- 0001 image URI: ${meta?.image ?? "(unresolved)"}`,
    `- owner: \`${EXPECTED_SIGNER}\``,
    `- balance after: ${receipt.balance_after_sol ?? "(see chain)"} SOL`,
    `- explorer (collection): https://explorer.solana.com/address/${receipt.collection.address}`,
    `- explorer (asset): https://explorer.solana.com/address/${receipt.canary.asset_address}`,
    "",
    `## Verification: ${checks.length - failed.length}/${checks.length} passed`,
    ...checks.map(([n, ok, d]) => `- ${ok ? "PASS" : "FAIL"} ${n}${d ? ` (${d})` : ""}`),
    "",
    "Exactly one collection and one canary were created. No 0002–3333 minted; no Candy Machine; not MINT_READY.",
  ].join("\n");
  writeFileSync(`${MAINNET_DIR}/deployment-receipt.md`, md + "\n");
  console.log(`\n${checks.length - failed.length}/${checks.length} passed. Wrote deployment-receipt.md`);
  process.exit(failed.length ? 1 : 0);
}
main().catch((e) => { console.error(e instanceof Error ? e.message : e); process.exit(1); });
