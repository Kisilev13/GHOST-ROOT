# GHOST//ROOT — OpenSea indexing packet

_Task section 26. Compiled 2026-09-08. Use this when requesting OpenSea indexing / support review after the Solana collection is minted._

## Current state

```
OPENSEA_INDEX_STATUS      NOT_INDEXED
OPENSEA_STATUS            AWAITING_SOLANA_COLLECTION_DEPLOYMENT
```

`GET /api/v2/search?query=ghost root` and `query=ghostroot` → `{ "results": [] }`.
No Solana collection for GHOST//ROOT exists on-chain, so OpenSea has nothing to
index. No fabricated OpenSea collection, no EVM replacement, no duplicate NFTs
have been created.

## Name-collision warning

The slug **`ghost-root`** already resolves on OpenSea to an **unrelated
collection**:

| Field | `ghost-root` (NOT ours) |
| --- | --- |
| Name | `GHOST_ROOT` (underscore) |
| Description | "2000 generative on-chain terminal NFTs" |
| Chain | ethereum |
| Contract | `0x3ad41f5055926ff40d499196fc59f34e6f4230fb` |
| Supply | 19 minted |
| Created | 2026-06-13 |
| project_url | none |

The integration treats this as `MISMATCH` and will never mutate it. When
GHOST//ROOT is deployed, expect its OpenSea slug to be something like
`ghost-root-solana`, `ghostroot`, or an OpenSea-assigned variant — resolve it
from the **contract address**, never by claiming this slug.

## Collection facts to submit

| Field | Value |
| --- | --- |
| Collection name | GHOST//ROOT |
| Chain | Solana |
| Standard | Metaplex Core |
| Supply | 3,333 (token names `GHOST//0001` … `GHOST//3333`) |
| Project URL | https://ghostroot.site |
| Verification page | https://ghostroot.site/verify/ |
| Royalty | 5% (500 bps) — on-chain authoritative |
| Collection address (Core) | _fill from deployment receipt_ |
| Candy Machine address | _fill from deployment receipt_ |
| Update authority | _fill from deployment receipt_ |
| Treasury | _fill from deployment receipt_ |
| Metadata root (Arweave/IPFS) | _fill_ |
| Artwork root (Arweave/IPFS) | _fill_ |
| Deployment date | _fill_ |
| Deployment hash | _fill_ |
| Sample mint addresses | _fill: 3–5 mint pubkeys, incl. Genesis 0001–0003_ |

## Procedure once minted

1. `set -a; . .secrets/opensea.env; set +a`, then put the real
   `SOLANA_COLLECTION_ADDRESS` (and CM / authority / treasury) in `opensea/.env`.
2. `npm run opensea:discover` — resolves the slug from the on-chain address.
3. If `OPENSEA INDEX = NOT FOUND` after ~24–48h of on-chain activity: OpenSea
   auto-indexes most Solana collections from marketplace activity; trigger it by
   listing one item, or use OpenSea's collection support / "Get your collection
   verified" flow (Help Center → "My collection isn't showing up"). Metaplex Core
   collections with a valid on-chain collection asset are picked up automatically.
4. `npm run opensea:verify` — must return `VERIFIED` before any sync.
5. Save the slug: `wp-admin → GHOST//ROOT → System → OpenSea collection slug`,
   and `OPENSEA_COLLECTION_SLUG` in the toolkit `.env`.
6. `npm run opensea:collection-sync -- --dry-run` then, with a PAT, `--apply`.
7. `npm run opensea:profile-sync -- --account <creator wallet>` (dry-run) then
   `--apply` with a `write:profile` PAT.

## Remaining manual step

**Deploy the GHOST//ROOT Metaplex Core collection on Solana.** Everything
downstream is automated and waiting on that single action.
