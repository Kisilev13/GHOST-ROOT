# GHOST//ROOT — OpenSea Discovery

Checked 2026-09-11T12:55:06.784Z using read-only OpenSea API v2 requests.

- Collection: `ECfe4r3Xp66qe6dAGgdnc4QAMikdDHHvrGfWPDqkrE1p`
- Canary: `GUhpsP3cHvtJ5JJzKDnfcr83tvHJPn3kA2ib17MtM9B4`
- State: **AWAITING_OPENSEA_INDEXING** (no verified identity match in these queries)
- Resolved slug: none

| Query | HTTP | Result |
| --- | --- | --- |
| Canary by Solana asset address | 404 | No result |
| Collection address as Solana NFT | 404 | No result |
| Owned wallet Solana NFTs | 200 | 0 NFTs; no next page |
| GHOST//ROOT name search, Solana | 200 | 0 results; no next page |

These queries do not establish why the collection is absent or whether OpenSea supports indexing this particular asset. No indexing delay or automatic future availability is assumed. A name match alone would not establish ownership or collection identity.

The canary verifier separately passed **17/17** checks, including collection/asset ownership and association, 500-bps royalties, metadata resolution and remote/local image SHA-256 equality. Finalized wallet balance: **0.195783509 SOL**.

Evidence: `evidence/canary-resume/20260911T125317Z/discovery-run/summary.json` and `verified-run/verifier.txt`.

Next: repeat these bounded read-only queries after indexing status changes; verify chain and collection address before recording a slug or preparing collection sync. Do not use the unrelated Ethereum `ghost-root` slug. No sync, mint or metadata update was performed.
