# GHOST//ROOT — OpenSea Verification

_Generated 2026-09-08T12:52:37Z · read-only_

**State: `AWAITING_SOLANA_COLLECTION_DEPLOYMENT`** · slug `none` · chain `solana`

## Identity checks

| Field | Expected | Actual | OK | Critical |
| --- | --- | --- | --- | --- |
| _none_ | | | | |

## Sample identity validation

_skipped — collection not indexed_

## Trait audit vs collection/metadata/schema.json

_skipped — collection not indexed_

## Notes

- SOLANA_COLLECTION_ADDRESS is empty. The Metaplex Core collection has not been deployed, so there is nothing for OpenSea to index yet.
- All API scaffolding is in place; verification will run automatically once the address is set and the collection is live.

---

Royalty is verified separately (Solana on-chain config is authoritative):
see [royalty-verification.md](./royalty-verification.md). Target: 500 bps.
