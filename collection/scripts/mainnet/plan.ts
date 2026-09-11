/**
 * Pure idempotency decision logic — no chain access, no I/O — so it can be unit
 * tested against every recovery scenario. Source of truth is the ON-CHAIN state
 * of the two persisted signer addresses, never the receipt file.
 */
export interface CollAccount {
  name: string;
  uri: string;
  updateAuthority: string;
  royalties?: { basisPoints: number; creators?: { address: string; percentage: number }[] };
}
export interface AssetAccount {
  name: string;
  uri: string;
  owner: string;
  updateAuthority?: { type: string; address?: string };
}
export interface Expected {
  collectionName: string;
  collectionUri: string;
  assetName: string;
  assetUri: string;
  wallet: string;
  bps: number;
  proposedCollectionAddress: string;
}
export interface Plan { createCollection: boolean; mintAsset: boolean; notes: string[]; }

/** Throws Error("ABORT: ...") on any mismatch. Returns the actions to take otherwise. */
export function decidePlan(coll: CollAccount | null, asset: AssetAccount | null, e: Expected): Plan {
  const notes: string[] = [];
  let createCollection: boolean;

  if (coll) {
    if (coll.name !== e.collectionName) throw new Error(`ABORT: on-chain collection name ${coll.name} != ${e.collectionName}`);
    if (coll.uri !== e.collectionUri) throw new Error(`ABORT: on-chain collection URI mismatch (${coll.uri} != ${e.collectionUri})`);
    if (coll.updateAuthority !== e.wallet) throw new Error(`ABORT: collection update authority ${coll.updateAuthority} != ${e.wallet}`);
    if (!coll.royalties) throw new Error("ABORT: existing collection has no royalties plugin");
    if (coll.royalties.basisPoints !== e.bps) throw new Error(`ABORT: collection royalty ${coll.royalties.basisPoints} != ${e.bps} bps`);
    const c0 = coll.royalties.creators?.[0];
    if (!c0 || c0.address !== e.wallet || c0.percentage !== 100)
      throw new Error(`ABORT: royalty recipient must be ${e.wallet} @100% (got ${JSON.stringify(c0)})`);
    createCollection = false;
    notes.push("collection already exists on-chain and verified — reuse, do NOT recreate");
  } else {
    createCollection = true;
    notes.push("collection not on-chain — will create with persisted signer");
  }

  let mintAsset: boolean;
  if (asset) {
    if (createCollection)
      throw new Error("ABORT: canary asset exists but its collection does not — inconsistent chain state");
    if (asset.name !== e.assetName) throw new Error(`ABORT: on-chain asset name ${asset.name} != ${e.assetName}`);
    if (asset.uri !== e.assetUri) throw new Error(`ABORT: on-chain asset URI mismatch (${asset.uri} != ${e.assetUri})`);
    if (asset.owner !== e.wallet) throw new Error(`ABORT: asset owner ${asset.owner} != ${e.wallet}`);
    if (asset.updateAuthority?.type !== "Collection" || asset.updateAuthority.address !== e.proposedCollectionAddress)
      throw new Error(`ABORT: asset collection membership mismatch (${JSON.stringify(asset.updateAuthority)} != ${e.proposedCollectionAddress})`);
    mintAsset = false;
    notes.push("canary already minted on-chain and verified — reuse, do NOT re-mint");
  } else {
    mintAsset = true;
    notes.push("canary not on-chain — will mint with persisted signer");
  }

  return { createCollection, mintAsset, notes };
}
