# GHOST//ROOT — OpenSea Discovery

_Generated 2026-09-08T14:38:34Z · read-only_

| Field | Value |
| --- | --- |
| OpenSea API | authenticated |
| Target chain | solana |
| Chain supported by OpenSea | yes |
| Authoritative Solana collection address | _not deployed_ |
| Candy Machine address | _not deployed_ |
| Update authority | _not deployed_ |
| Resolved OpenSea slug | _none_ |
| Resolution method | search |
| Verification state | **AWAITING_SOLANA_COLLECTION_DEPLOYMENT** |

## Candidates considered

- `ghost-root` — "GHOST_ROOT" — chain `ethereum` — name-candidate slug exists

> A candidate is the GHOST//ROOT collection **only** if `chain === solana`
> **and** its contract address equals `SOLANA_COLLECTION_ADDRESS`. Name matches
> are ignored.

## Verification checks

_none run_

## Notes

- SOLANA_COLLECTION_ADDRESS is not set — the collection has not been deployed, so OpenSea cannot have indexed it. Status: AWAITING_SOLANA_COLLECTION_DEPLOYMENT.
- Name-based candidates found but NOT confirmed. A candidate is only the GHOST//ROOT collection if chain === solana AND contract address === SOLANA_COLLECTION_ADDRESS. Do not mutate any of these.
- SOLANA_COLLECTION_ADDRESS is empty. The Metaplex Core collection has not been deployed, so there is nothing for OpenSea to index yet.
- All API scaffolding is in place; verification will run automatically once the address is set and the collection is live.
