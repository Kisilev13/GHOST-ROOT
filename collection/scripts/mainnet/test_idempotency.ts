#!/usr/bin/env -S npx tsx
/**
 * Recovery/unit tests for the crash-safe idempotency decision logic (plan.ts).
 * No chain access. Covers the scenarios the task requires.
 */
import { decidePlan, type CollAccount, type AssetAccount, type Expected } from "./plan.js";

const WALLET = "CcgTzAmyog1h3tUnR7zqtQ2bZzQSYL9L9xZHDBvtD94R";
const COLL_ADDR = "CoLLxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";
const E: Expected = {
  collectionName: "GHOST//ROOT", collectionUri: "https://arweave.net/COLLxxx",
  assetName: "GHOST//0001", assetUri: "https://arweave.net/ASSETxxx",
  wallet: WALLET, bps: 500, proposedCollectionAddress: COLL_ADDR,
};
const goodColl: CollAccount = { name: "GHOST//ROOT", uri: E.collectionUri, updateAuthority: WALLET,
  royalties: { basisPoints: 500, creators: [{ address: WALLET, percentage: 100 }] } };
const goodAsset: AssetAccount = { name: "GHOST//0001", uri: E.assetUri, owner: WALLET,
  updateAuthority: { type: "Collection", address: COLL_ADDR } };

let pass = 0, fail = 0;
function expect(name: string, fn: () => void) {
  try { fn(); console.log(`PASS  ${name}`); pass++; }
  catch (e) { console.log(`FAIL  ${name} — ${e instanceof Error ? e.message : e}`); fail++; }
}
const aborts = (fn: () => unknown, re: RegExp) => {
  try { fn(); throw new Error("expected ABORT, got none"); }
  catch (e) { const m = e instanceof Error ? e.message : String(e); if (!re.test(m)) throw new Error(`wrong abort: ${m}`); }
};
const eq = (a: unknown, b: unknown, msg: string) => { if (JSON.stringify(a) !== JSON.stringify(b)) throw new Error(`${msg}: ${JSON.stringify(a)} != ${JSON.stringify(b)}`); };

// 1. First run / dry run — nothing on chain → create both.
expect("first run: create collection + mint asset", () => {
  const p = decidePlan(null, null, E); eq([p.createCollection, p.mintAsset], [true, true], "plan");
});
// 2. Collection exists (receipt missing is irrelevant — we only look at chain) → reuse, still mint.
expect("collection exists, asset absent: reuse collection, mint asset", () => {
  const p = decidePlan(goodColl, null, E); eq([p.createCollection, p.mintAsset], [false, true], "plan");
});
// 3. Both exist → do nothing.
expect("both exist: no create, no mint", () => {
  const p = decidePlan(goodColl, goodAsset, E); eq([p.createCollection, p.mintAsset], [false, false], "plan");
});
// 4. Both exist but receipt missing → chain is source of truth → still no-op (same as #3).
expect("both exist w/ no receipt: chain-truth no-op", () => {
  const p = decidePlan(goodColl, goodAsset, E); eq([p.createCollection, p.mintAsset], [false, false], "plan");
});
// 5. Stale receipt but account absent → chain-truth says create (null account → create).
expect("stale receipt, account absent: create", () => {
  const p = decidePlan(null, null, E); eq([p.createCollection, p.mintAsset], [true, true], "plan");
});
// 6. URI mismatch → ABORT.
expect("collection URI mismatch aborts", () => aborts(() => decidePlan({ ...goodColl, uri: "https://arweave.net/EVIL" }, null, E), /collection URI mismatch/));
expect("asset URI mismatch aborts", () => aborts(() => decidePlan(goodColl, { ...goodAsset, uri: "https://arweave.net/EVIL" }, E), /asset URI mismatch/));
// 7. update-authority mismatch → ABORT.
expect("collection update-authority mismatch aborts", () => aborts(() => decidePlan({ ...goodColl, updateAuthority: "HACKER" }, null, E), /update authority/));
// extra: royalty bps / recipient guards
expect("royalty bps mismatch aborts", () => aborts(() => decidePlan({ ...goodColl, royalties: { basisPoints: 5000, creators: [{ address: WALLET, percentage: 100 }] } }, null, E), /royalty 5000/));
expect("royalty recipient mismatch aborts", () => aborts(() => decidePlan({ ...goodColl, royalties: { basisPoints: 500, creators: [{ address: "OTHER", percentage: 100 }] } }, null, E), /royalty recipient/));
// extra: asset exists but collection absent → inconsistent → ABORT
expect("asset exists but collection absent aborts", () => aborts(() => decidePlan(null, goodAsset, E), /inconsistent chain state/));
// extra: asset membership points elsewhere → ABORT
expect("asset membership mismatch aborts", () => aborts(() => decidePlan(goodColl, { ...goodAsset, updateAuthority: { type: "Collection", address: "OTHERCOLL" } }, E), /membership mismatch/));
// extra: asset owner mismatch → ABORT
expect("asset owner mismatch aborts", () => aborts(() => decidePlan(goodColl, { ...goodAsset, owner: "SOMEONE" }, E), /asset owner/));

console.log(`\n${pass}/${pass + fail} passed`);
process.exit(fail ? 1 : 0);
