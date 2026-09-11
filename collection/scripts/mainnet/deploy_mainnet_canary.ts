#!/usr/bin/env -S npx tsx
/**
 * Stage D — guarded production Metaplex Core deployment: ONE GHOST//ROOT
 * collection + ONE GHOST//0001 canary on Solana mainnet-beta. Nothing else.
 *
 * Crash-safe idempotency: the collection + asset account signers are generated
 * ONCE and persisted (0600, gitignored) BEFORE any broadcast (signer_state.ts).
 * Every run reconstructs the SAME two addresses and queries the chain for both;
 * the ON-CHAIN state — not the receipt — decides what to create. A crash after
 * broadcast but before receipt-write cannot cause a duplicate: the rerun sees
 * the account already exists at the persisted address and reuses it.
 *
 * Default (no --confirm-mainnet): DRY-RUN. Prints the plan + proposed addresses,
 * signs NOTHING. --confirm-mainnet: signs + broadcasts (real SOL). USER runs that.
 *
 * Royalty is collection-level ONLY (500 bps, creator = production wallet @100%,
 * ruleSet None). Update authority retained (mutable). No immutability step exists.
 */
import { existsSync, readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { base58 } from "@metaplex-foundation/umi";
import { create, createCollection, fetchCollectionV1, safeFetchCollectionV1, safeFetchAssetV1 } from "@metaplex-foundation/mpl-core";
import { parseCommon, guardedUmi, EXPECTED_SIGNER, ROYALTY_BPS, COLLECTION_NAME } from "./guards.js";
import { loadOrCreateDeploySigners } from "./signer_state.js";
import { decidePlan, type CollAccount, type AssetAccount } from "./plan.js";

const HERE = dirname(fileURLToPath(import.meta.url));
const MAINNET_DIR = `${HERE}/../../mainnet`;
const URIS = `${MAINNET_DIR}/uploaded-uris.json`;
const RECEIPT = `${MAINNET_DIR}/deployment-receipt.json`;
const f = (bp: bigint) => (Number(bp) / 1e9).toFixed(9);

function toCollAccount(c: Awaited<ReturnType<typeof safeFetchCollectionV1>>): CollAccount | null {
  if (!c) return null;
  const r = (c as any).royalties;
  return { name: c.name, uri: c.uri, updateAuthority: c.updateAuthority,
    royalties: r ? { basisPoints: r.basisPoints, creators: r.creators } : undefined };
}
function toAssetAccount(a: Awaited<ReturnType<typeof safeFetchAssetV1>>): AssetAccount | null {
  if (!a) return null;
  return { name: a.name, uri: a.uri, owner: a.owner,
    updateAuthority: a.updateAuthority ? { type: a.updateAuthority.type, address: (a.updateAuthority as any).address } : undefined };
}

async function main() {
  const common = parseCommon(process.argv.slice(2));
  const { umi, signerPk, genesis } = await guardedUmi(common);

  // Persisted signers BEFORE any broadcast → deterministic proposed addresses.
  // Done first so a pre-upload dry-run can still report the exact proposed addresses.
  const { collection: collSigner, asset: assetSigner, firstRun } = loadOrCreateDeploySigners(umi);
  const bal0 = await umi.rpc.getBalance(umi.identity.publicKey);

  const hasUris = existsSync(URIS);
  if (!hasUris && common.confirmMainnet)
    throw new Error(`ABORT: ${URIS} missing. Upload (Stage C) + verify URIs first.`);
  if (!hasUris) {
    console.log("\n=== MAINNET DEPLOY DRY-RUN (URIs pending) ===");
    console.log(`network              mainnet-beta (genesis ${genesis})`);
    console.log(`signer/authority     ${signerPk}`);
    console.log(`balance before       ${f(bal0.basisPoints)} SOL`);
    console.log(`signer-state         ${firstRun ? "generated + persisted this run (0600)" : "loaded from mainnet/.private/"}`);
    console.log(`proposed collection  ${collSigner.publicKey}  (on-chain: not queried — pre-upload)`);
    console.log(`proposed GHOST//0001 ${assetSigner.publicKey}  (on-chain: not queried — pre-upload)`);
    console.log(`royalty              ${ROYALTY_BPS} bps (5%)  recipient ${EXPECTED_SIGNER} @100%  ruleSet None`);
    console.log(`update authority     ${EXPECTED_SIGNER} (RETAINED — mutable)`);
    console.log(`metadata URIs        PENDING UPLOAD — run Stage C (upload_mainnet_canary.ts --confirm-upload)`);
    console.log("\nDRY-RUN: signed nothing. Re-run after upload for the full verified plan.");
    return;
  }
  const uris = JSON.parse(readFileSync(URIS, "utf8"));
  const collectionUri = uris.PRODUCTION_COLLECTION_METADATA_URI;
  const assetUri = uris.PRODUCTION_0001_METADATA_URI;
  for (const [k, v] of Object.entries({ collectionUri, assetUri }))
    if (typeof v !== "string" || !/^https:\/\/(arweave\.net|gateway\.irys\.xyz|.*\.arweave\.)/i.test(v))
      throw new Error(`ABORT: ${k} is not a permanent Arweave/Irys URI: ${v}`);

  // On-chain truth for BOTH addresses.
  const collOnChain = await safeFetchCollectionV1(umi, collSigner.publicKey);
  const assetOnChain = await safeFetchAssetV1(umi, assetSigner.publicKey);
  const expected = {
    collectionName: COLLECTION_NAME, collectionUri, assetName: "GHOST//0001", assetUri,
    wallet: EXPECTED_SIGNER, bps: ROYALTY_BPS, proposedCollectionAddress: collSigner.publicKey,
  };
  const plan = decidePlan(toCollAccount(collOnChain), toAssetAccount(assetOnChain), expected); // throws on any mismatch

  const bal = await umi.rpc.getBalance(umi.identity.publicKey);
  console.log("\n=== MAINNET DEPLOY PLAN ===");
  console.log(`network              mainnet-beta (genesis ${genesis})`);
  console.log(`signer/authority     ${signerPk}`);
  console.log(`balance before       ${f(bal.basisPoints)} SOL`);
  console.log(`signer-state         ${firstRun ? "generated + persisted this run (0600)" : "loaded from mainnet/.private/"}`);
  console.log(`proposed collection  ${collSigner.publicKey}  (on-chain: ${collOnChain ? "EXISTS" : "absent"})`);
  console.log(`proposed GHOST//0001 ${assetSigner.publicKey}  (on-chain: ${assetOnChain ? "EXISTS" : "absent"})`);
  console.log(`collection name      ${COLLECTION_NAME}`);
  console.log(`collection URI       ${collectionUri}`);
  console.log(`canary URI           ${assetUri}`);
  console.log(`royalty              ${ROYALTY_BPS} bps (5%)  recipient ${EXPECTED_SIGNER} @100%  ruleSet None`);
  console.log(`update authority     ${EXPECTED_SIGNER} (RETAINED — mutable, not immutable)`);
  console.log(`plan                 createCollection=${plan.createCollection}  mintAsset=${plan.mintAsset}`);
  plan.notes.forEach((n) => console.log(`  - ${n}`));
  console.log(`max NFTs this run    1`);

  if (!plan.createCollection && !plan.mintAsset) { console.log("\nNothing to do — both exist and verified. STOP."); return; }
  if (!common.confirmMainnet) { console.log("\nDRY-RUN (no --confirm-mainnet): signed nothing."); return; }

  mkdirSync(MAINNET_DIR, { recursive: true });
  let receipt: any = existsSync(RECEIPT) ? JSON.parse(readFileSync(RECEIPT, "utf8")) : {};
  receipt = { ...receipt, network: "mainnet-beta", genesis, signer: signerPk, media_status: "PRE_RELEASE_CANARY_ART_MUTABLE" };

  if (plan.createCollection) {
    const tx = await createCollection(umi, {
      collection: collSigner, name: COLLECTION_NAME, uri: collectionUri,
      plugins: [{ type: "Royalties", basisPoints: ROYALTY_BPS, creators: [{ address: umi.identity.publicKey, percentage: 100 }], ruleSet: { type: "None" } }],
    }).sendAndConfirm(umi, { confirm: { commitment: "finalized" } });
    if (tx.result.value.err) throw new Error(`Collection tx failed: ${JSON.stringify(tx.result.value.err)}`);
    receipt.collection = { address: collSigner.publicKey, name: COLLECTION_NAME, uri: collectionUri,
      update_authority: signerPk, royalty_basis_points: ROYALTY_BPS, royalty_recipient: EXPECTED_SIGNER,
      creation_signature: base58.deserialize(tx.signature)[0],
      explorer: `https://explorer.solana.com/address/${collSigner.publicKey}` };
    writeFileSync(RECEIPT, JSON.stringify(receipt, null, 2) + "\n");
    console.log(`Collection created: ${collSigner.publicKey}`);
  } else {
    receipt.collection = receipt.collection ?? { address: collSigner.publicKey, name: COLLECTION_NAME, uri: collectionUri, update_authority: signerPk, royalty_basis_points: ROYALTY_BPS, royalty_recipient: EXPECTED_SIGNER, reused: true };
  }

  if (plan.mintAsset) {
    const collectionAccount = await fetchCollectionV1(umi, collSigner.publicKey);
    const tx = await create(umi, { asset: assetSigner, collection: collectionAccount, name: "GHOST//0001", uri: assetUri, owner: umi.identity.publicKey })
      .sendAndConfirm(umi, { confirm: { commitment: "finalized" } });
    if (tx.result.value.err) throw new Error(`Mint tx failed: ${JSON.stringify(tx.result.value.err)}`);
    const balAfter = await umi.rpc.getBalance(umi.identity.publicKey);
    receipt.canary = { asset_address: assetSigner.publicKey, name: "GHOST//0001", uri: assetUri, owner: EXPECTED_SIGNER,
      collection_address: collSigner.publicKey, mint_signature: base58.deserialize(tx.signature)[0],
      explorer: `https://explorer.solana.com/address/${assetSigner.publicKey}` };
    receipt.balance_after_sol = f(balAfter.basisPoints);
    writeFileSync(RECEIPT, JSON.stringify(receipt, null, 2) + "\n");
    console.log(`Canary minted: ${assetSigner.publicKey}`);
  }
  console.log(`\nDone. Receipt: ${RECEIPT}. Run verify_mainnet_canary.ts next.`);
}
main().catch((e) => { console.error(e instanceof Error ? e.message : e); process.exit(1); });
