# GHOST//ROOT — royalty verification

_Task section 14. Compiled 2026-09-08._

## 1. Where royalties are authoritative

For a **Solana / Metaplex Core** collection, royalties are enforced by the
**on-chain collection plugin configuration** (`Royalties` plugin on the Core
collection asset: `basisPoints` + `creators[]` + a `RuleSet` for enforcement).
That configuration is the single source of truth.

OpenSea's royalty field for a Solana collection is **informational / display**,
derived from on-chain data and the collection's OpenSea settings. It does not
override the chain. Setting a conflicting value on OpenSea would only create a
presentation mismatch.

## 2. Project target

| Source | Value |
| --- | --- |
| `collection/collection.yaml` → `royalty_bps` | **500** |
| `ghost-root-nft-builder` skill default | 5% |
| Task section 14 default | 5% / 500 bps |
| **Adopted target** | **500 bps (5%)** to the GHOST//ROOT treasury creator address |

## 3. Current on-chain state

`SOLANA_COLLECTION_ADDRESS` is **not set** — the Metaplex Core collection has not
been deployed (`collection/deployment-addresses.md`: "No contracts have been
deployed"). There is no on-chain royalty configuration to read yet.

```
ON-CHAIN ROYALTY CONFIG      NOT DEPLOYED
OPENSEA ROYALTY DISPLAY      N/A (collection not indexed)
CONFLICT                     none possible yet
```

## 4. What the integration does about royalties

- `opensea:verify` records the royalty percentage OpenSea would display (from the
  collection `fees[]`, excluding OpenSea's own protocol fee) as an **informational,
  non-blocking** check. A royalty difference never changes the VERIFIED/MISMATCH
  verdict — only chain + contract address do.
- The WordPress `/verify/` page and marketplace module display royalty only if
  OpenSea reports it; they never assert a number the chain hasn't confirmed.
- No script sets, patches, or "blindly enables" a royalty field on OpenSea.

## 5. Action required at deployment (manual, one-time)

1. Deploy the Core collection with `Royalties` plugin: `basisPoints: 500`,
   `creators: [{ address: <treasury>, percentage: 100 }]`, plus the chosen
   `RuleSet` for marketplace enforcement.
2. Record the config hash in `collection/deployment-addresses.md` and set
   `ROYALTY_BPS` / `SOLANA_TREASURY` in the environment.
3. Run `npm run opensea:verify` once indexed and confirm the `royalty_pct` line
   reads `5% / 5%`.
4. In OpenSea's collection settings, confirm creator earnings show 5% and the
   payout address matches the treasury — do **not** add a second, different
   royalty on OpenSea.

**Status: `PENDING_DEPLOYMENT` — no royalty conflict exists or can exist yet.**
