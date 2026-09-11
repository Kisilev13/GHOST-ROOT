# GHOST//ROOT — OpenSea Discovery

_Updated 2026-09-11 · read-only · post-mainnet-deployment_

| Field | Value |
| --- | --- |
| OpenSea API | authenticated (v2) |
| Target chain | solana |
| Chain supported by OpenSea | yes |
| **Authoritative Solana collection address** | `ECfe4r3Xp66qe6dAGgdnc4QAMikdDHHvrGfWPDqkrE1p` (Metaplex Core, mainnet-beta) |
| **GHOST//0001 asset (canary)** | `GUhpsP3cHvtJ5JJzKDnfcr83tvHJPn3kA2ib17MtM9B4` |
| Owner / update authority | `CcgTzAmyog1h3tUnR7zqtQ2bZzQSYL9L9xZHDBvtD94R` |
| Candy Machine address | _none (single canary; not deployed)_ |
| Resolved OpenSea slug | **_none — not indexed yet_** |
| Resolution method | address + asset + owner lookups (not name) |
| Verification state | **AWAITING_OPENSEA_INDEXING** |
| media_status | PRE_RELEASE_CANARY_ART_MUTABLE |

## Queries run (read-only, 2026-09-11)

| Query | Endpoint | Result |
| --- | --- | --- |
| Asset by mint | `GET /api/v2/chain/solana/nft/GUhpsP3c…M9B4` | **404 NOT_FOUND** |
| Collection addr as nft | `GET /api/v2/chain/solana/nft/ECfe4r3X…rE1p` | **404 NOT_FOUND** |
| Owner's Solana NFTs | `GET /api/v2/chain/solana/account/CcgTz…D94R/nfts` | **empty (`[]`)** |
| Name search | `GET /api/v2/search?query=GHOST//ROOT&chains=solana…` | 1 result, **unrelated** (`drip-shared-collection` / "Room with ghost girl") — 0 references to our addresses |
| Contract listing | `GET /api/v2/chain/solana/contract/ECfe4r3X…/nfts` | 400 — "not supported on solana; use collection/account endpoints" |

## Identity gate

**No OpenSea result can be tied to `ECfe4r3Xp66qe6dAGgdnc4QAMikdDHHvrGfWPDqkrE1p` or
`GUhpsP3cHvtJ5JJzKDnfcr83tvHJPn3kA2ib17MtM9B4`.** OpenSea has not yet indexed the
freshly-minted Metaplex Core collection/asset (minted 2026-09-11, minutes before this
query). Solana Core indexing on OpenSea can lag hours–days and may require the collection
to be opened/imported in the OpenSea UI by the owner wallet.

Therefore **no slug is resolved and none is guessed.** Per the gate, a candidate is the
GHOST//ROOT collection **only** if `chain === solana` AND it maps to the collection
address above — name matches are ignored.

## Do NOT

- Do **not** use the unrelated Ethereum `ghost-root` slug (chain `ethereum`) — it is a
  different project and must never be configured as this collection.
- Do **not** set `SOLANA_COLLECTION_ADDRESS` in production OpenSea config to anything but
  `ECfe4r3Xp66qe6dAGgdnc4QAMikdDHHvrGfWPDqkrE1p`, and do **not** run
  `opensea:collection-sync --apply` until OpenSea has indexed the collection and its
  identity is verified against the on-chain address.

## Next (owner action, then re-run discovery)

1. Owner wallet opens the collection on OpenSea (Solana Core collections often need the
   owner to view/import the asset once to trigger indexing), or simply wait for the
   indexer.
2. Re-run the address/asset/owner lookups above until one returns 200 and its
   `contract`/collection maps to `ECfe4r3Xp66qe6dAGgdnc4QAMikdDHHvrGfWPDqkrE1p`.
3. Only then record the verified slug and proceed to profile/collection sync.
