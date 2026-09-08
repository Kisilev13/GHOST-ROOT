# GHOST//ROOT — OpenSea API discovery

_Compiled 2026-09-08 against the live API (`https://api.opensea.io/api/v2`) with the project key. Task section 2._

The implementation is built against the **currently published** OpenSea API v2
schema (docs index: `https://docs.opensea.io/llms.txt`), cross-checked with live
calls. It does not rely on hard-coded assumptions about limits or paths.

## Authentication

| Tier | Credential | Header | Used for |
| --- | --- | --- | --- |
| Public reads | API key | `x-api-key: <key>` | everything GHOST//ROOT needs: discovery, collection, stats, activity, listings, holders, NFT/trait validation, `/chains`, `/search` |
| Wallet-scoped writes | PAT → wallet JWT | `Authorization: Bearer <jwt>` | creator profile edits, collection "about" page edits |
| PAT management | SIWX session cookies | — | minting a PAT (interactive, on opensea.io) |

- Instant free-tier key: `POST /api/v2/auth/keys` (the toolkit's `opensea:bootstrap --create-key` wraps it; the key is never written to disk automatically).
- Scope catalogue is discoverable at `GET /api/v2/auth/scopes` — the toolkit does **not** hard-code scope strings.
- **PAT is never sent in `Authorization`** — it is exchanged for a short-lived JWT first (`auth.ts`).

## Rate limits (observed live, not assumed)

```
x-ratelimit-limit: 120
x-ratelimit-remaining: <n>
x-ratelimit-reset: <unix seconds>
```

Both the Node client and the WordPress client read these headers on every
response. 429 carries `Retry-After`; the clients honour it (Node: backoff +
jitter + retry; WP: fail to cache, cron retries).

## Chains

`GET /api/v2/chains` → 29 chains. **Solana is supported**:

```json
{ "chain": "solana", "name": "Solana", "symbol": "SOL",
  "supports_swaps": true, "block_explorer": "Solscan",
  "block_explorer_url": "https://solscan.io/" }
```

## Endpoints wired into this integration

| Purpose | Method + path | Verified |
| --- | --- | --- |
| Supported chains | `GET /chains` | live ✅ |
| Search | `GET /search?query=` | live ✅ (empty for "ghost root" / "ghostroot") |
| Collection metadata | `GET /collections/{slug}` | live ✅ |
| Collection stats | `GET /collections/{slug}/stats` → `{ total, intervals[] }` | live ✅ |
| Collection holders | `GET /collections/{slug}/holders?limit=&next=` | live ✅ (cursor) |
| Collection traits | `GET /collections/{slug}/traits` | schema |
| Floor price history | `GET /collections/{slug}/floor_prices` | schema |
| NFTs by collection | `GET /collection/{slug}/nfts?limit=&next=` | live ✅ (note singular `collection`) |
| Single NFT | `GET /chain/{chain}/nfts/{identifier}` | schema |
| Contract → slug | `GET /chain/{chain}/contract/{address}` | schema (used for address-based discovery) |
| Collection events / activity | `GET /events/collection/{slug}?event_type=&limit=` → `{ asset_events[], next }` | live ✅ |
| Best listings | `GET /listings/collection/{slug}/best?limit=` → `{ listings[] }` | live ✅ |
| All listings | `GET /listings/collection/{slug}/all` | schema |
| Account / profile | `GET /accounts/{address_or_username}` | schema |
| Profile settings write | `PATCH /accounts/profile/settings` (scope `write:profile`) | schema |
| Profile image upload | `POST /accounts/profile/image` (`upload_profile_image`) | schema |
| Collection metadata write | `PATCH /collections/{slug}/metadata` (API key OR scope `write:collections`) | schema |
| Collection image upload | `POST` `upload_collection_image` | schema |

## Media / image upload flow

The current API exposes dedicated upload endpoints rather than a presigned-URL
dance: `upload_profile_image`, `upload_collection_image`, `upload_drop_item_media`.
The toolkit's sync scripts stop at the diff and identify the asset that must be
supplied — actual upload is a deliberate, authenticated step performed with the
official asset files (the GHOST//ROOT logomark + wide hero artwork), not source
artwork re-compressed on every run.

## Not used (deliberately)

Drops / SeaDrop / `deploy_drop_contract` / `build_drop_mint_transaction` — the
primary mint stays on Solana + Metaplex Core. OpenSea is secondary-market only.
