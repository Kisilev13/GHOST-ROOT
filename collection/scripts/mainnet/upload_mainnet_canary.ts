#!/usr/bin/env -S npx tsx
/**
 * Stage C — Irys → Arweave uploader for the GHOST//ROOT production canary ONLY.
 *
 * Uploads exactly three things: the GHOST//0001 image, the GHOST//0001 metadata,
 * and the collection metadata. Never the other 3,332. Never a collection image
 * (none is finalized — do not invent one).
 *
 *   --quote-only  : read-only. Prints byte sizes, Irys price, wallet + funded
 *                   balances, estimated SOL, expected remaining. Signs NOTHING.
 *   --confirm-upload : actually funds Irys (if needed) and uploads. Spends REAL SOL.
 *                      This is the command the USER runs; the agent never runs it.
 *
 * Guards: mainnet-beta genesis hash + exact expected signer, or ABORT.
 */
import { readFileSync, writeFileSync } from "node:fs";
import { dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { createGenericFile, lamports } from "@metaplex-foundation/umi";
import { irysUploader, isIrysUploader } from "@metaplex-foundation/umi-uploader-irys";
import { parseCommon, guardedUmi, EXPECTED_SIGNER } from "./guards.js";
import { buildCollectionMetadata, buildAssetMetadata } from "./build_metadata.js";

const HERE = dirname(fileURLToPath(import.meta.url));
const COLLECTION_DIR = `${HERE}/../..`;
const IMAGE_PATH = `${COLLECTION_DIR}/mainnet/assets/0001.png`;
const URIS_OUT = `${COLLECTION_DIR}/mainnet/uploaded-uris.json`;
const DUMMY_ARWEAVE = "https://arweave.net/" + "x".repeat(43); // 63 chars, realistic size

async function main() {
  const argv = process.argv.slice(2);
  const common = parseCommon(argv);
  const quoteOnly = argv.includes("--quote-only");
  const confirmUpload = argv.includes("--confirm-upload");

  const { umi, signerPk, genesis } = await guardedUmi(common);
  umi.use(irysUploader());
  console.log(`Guard OK — mainnet genesis ${genesis}, signer ${signerPk}`);

  const imageBytes = readFileSync(IMAGE_PATH);
  const assetMdStr = JSON.stringify(buildAssetMetadata(DUMMY_ARWEAVE), null, 2);
  const collMdStr = JSON.stringify(buildCollectionMetadata(), null, 2);
  const sizes = {
    image: imageBytes.length,
    asset_metadata: Buffer.byteLength(assetMdStr),
    collection_metadata: Buffer.byteLength(collMdStr),
  };

  if (!isIrysUploader(umi.uploader)) throw new Error("ABORT: uploader is not Irys.");
  const irys = umi.uploader;

  const priceImage = await irys.getUploadPriceFromBytes(sizes.image);
  const priceAsset = await irys.getUploadPriceFromBytes(sizes.asset_metadata);
  const priceColl = await irys.getUploadPriceFromBytes(sizes.collection_metadata);
  const totalLamports = priceImage.basisPoints + priceAsset.basisPoints + priceColl.basisPoints;
  const funded = await irys.getBalance();
  const walletBal = await umi.rpc.getBalance(umi.identity.publicKey);
  const needLamports = totalLamports > funded.basisPoints ? totalLamports - funded.basisPoints : 0n;

  const f = (bp: bigint) => (Number(bp) / 1e9).toFixed(9);
  console.log("\n=== IRYS UPLOAD QUOTE (GHOST//0001 canary only) ===");
  console.log(`files:`);
  console.log(`  0001 image           ${sizes.image} bytes   price ${f(priceImage.basisPoints)} SOL`);
  console.log(`  0001 metadata        ${sizes.asset_metadata} bytes   price ${f(priceAsset.basisPoints)} SOL`);
  console.log(`  collection metadata  ${sizes.collection_metadata} bytes   price ${f(priceColl.basisPoints)} SOL`);
  console.log(`  TOTAL upload price    ${f(totalLamports)} SOL`);
  console.log(`irys funded balance     ${f(funded.basisPoints)} SOL`);
  console.log(`wallet balance          ${f(walletBal.basisPoints)} SOL`);
  console.log(`SOL to fund on Irys     ${f(needLamports)} SOL (+ tx fee)`);
  console.log(`expected wallet after   ~${f(walletBal.basisPoints - needLamports)} SOL (excl. tx fees)`);

  if (quoteOnly) {
    console.log("\n--quote-only: nothing funded or uploaded. Signs nothing.");
    return;
  }
  if (!confirmUpload) {
    throw new Error("Refusing to upload without --confirm-upload (real SOL). Use --quote-only to price.");
  }

  // === real upload path (USER runs this) ===
  if (needLamports > 0n) {
    console.log(`\nFunding Irys with ${f(needLamports)} SOL ...`);
    await irys.fund(lamports(needLamports), false);
  }
  console.log("Uploading 0001 image ...");
  const [imageUri] = await umi.uploader.upload([
    createGenericFile(new Uint8Array(imageBytes), "0001.png", { contentType: "image/png" }),
  ]);
  console.log("  image ->", imageUri);

  console.log("Uploading 0001 metadata ...");
  const assetMd = JSON.stringify(buildAssetMetadata(imageUri), null, 2);
  const [metadataUri] = await umi.uploader.upload([
    createGenericFile(assetMd, "0001.json", { contentType: "application/json" }),
  ]);
  console.log("  metadata ->", metadataUri);

  console.log("Uploading collection metadata ...");
  const collMd = JSON.stringify(buildCollectionMetadata(), null, 2);
  const [collectionUri] = await umi.uploader.upload([
    createGenericFile(collMd, "collection.json", { contentType: "application/json" }),
  ]);
  console.log("  collection ->", collectionUri);

  const out = {
    generated_at_utc: new Date().toISOString(),
    media_status: "PRE_RELEASE_CANARY_ART_MUTABLE",
    signer: EXPECTED_SIGNER,
    PRODUCTION_0001_IMAGE_URI: imageUri,
    PRODUCTION_0001_METADATA_URI: metadataUri,
    PRODUCTION_COLLECTION_METADATA_URI: collectionUri,
    image_sha256_local: "364fcba479fb0494dc4d1a19d66b9c045696c6e543b7a414c1c8f5b7bacd211f",
  };
  writeFileSync(URIS_OUT, JSON.stringify(out, null, 2) + "\n");
  console.log(`\nWrote ${URIS_OUT}. Verify each URI over HTTP before deploying.`);
}

main().catch((e) => {
  console.error(e instanceof Error ? e.message : e);
  process.exit(1);
});
