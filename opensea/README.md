# GHOST//ROOT — OpenSea integration toolkit

Server-side OpenSea **API v2** integration for the GHOST//ROOT collection
(**Solana / Metaplex Core**). This directory is the *operator* side: discovery,
identity verification, profile/collection sync, health checks. The *public*
side — the marketplace data shown on ghostroot.site — lives in the WordPress
plugin at `../site/app/public/wp-content/plugins/ghost-root-core/src/OpenSea/`
and never calls OpenSea from the browser.

```
Browser ─▶ ghostroot.site (WP REST /ghost-root/v1/opensea/*) ─▶ OpenSea API
                                    ▲
                    this toolkit ───┘  (operator CLI, separate credentials)
```

No EVM contract. No SeaDrop. No migration off Solana. If the Solana collection
is not deployed yet, every script degrades to
`OPENSEA_STATUS=AWAITING_SOLANA_COLLECTION_DEPLOYMENT` and does only what is safe.

## Requirements

- Node.js ≥ 22.6 (uses native TypeScript type-stripping — **zero runtime deps**)
- An OpenSea API key. This project already has one in `../.secrets/opensea.env`.

## Setup

```bash
cd opensea
npm install            # dev-only: typescript + @types/node (for `npm run typecheck`)
npm run opensea:bootstrap
```

`bootstrap` copies `.env.example` → `.env` (no secrets), proves the key works,
confirms Solana is a supported chain, and prints the rate-limit ceiling.
If you have no key: `npm run opensea:bootstrap -- --create-key` uses the instant
free-tier endpoint and tells you where to store the result (it is never written
to disk for you).

## Commands

| Command | What it does | Writes? |
| --- | --- | --- |
| `npm run opensea:bootstrap` | key check, chain check, env scaffold, expiry warning | no |
| `npm run opensea:discover` | resolve the OpenSea slug from the **on-chain address** (never by name); `reports/discovery.md` | no |
| `npm run opensea:verify` | full identity + sample-NFT + trait verification; `reports/verification.md` | no |
| `npm run opensea:profile-sync [-- --account <a>] [--apply]` | safe diff of the creator profile vs the GHOST//ROOT target | only with `--apply` + PAT |
| `npm run opensea:collection-sync [-- --apply]` | DISCOVER→VERIFY→DIFF→VALIDATE→APPLY→READ-BACK; aborts on any failed stage | only with `--apply` + PAT + `VERIFIED` |
| `npm run opensea:health` | the 13-point health checklist; `reports/health.md` | no |
| `npm test` | 30 unit/integration tests (mocked fetch, no network) | no |
| `npm run typecheck` | `tsc --noEmit` | no |

**All write-capable scripts default to dry-run.** A real mutation needs
`--apply`, an `OPENSEA_SCOPED_TOKEN` (a Personal Access Token minted through an
interactive SIWX session on opensea.io — a human step), and, for collections, a
`VERIFIED` identity check. This toolkit holds **no private key or seed phrase**
and will not sign on your behalf.

## Configuration

See `.env.example` for the full list. The important ones:

| Var | Meaning |
| --- | --- |
| `OPENSEA_API_KEY` | public reads + collection metadata PATCH (`x-api-key`) |
| `OPENSEA_API_KEY_EXPIRES_AT` | ISO date; health check warns ≤ 7 days out. **Never store the key here — only its expiry.** |
| `OPENSEA_SCOPED_TOKEN` | PAT for wallet-scoped writes; exchanged for a short-lived JWT at call time |
| `SOLANA_COLLECTION_ADDRESS` | **authoritative** Metaplex Core collection address — the verification anchor |
| `OPENSEA_COLLECTION_SLUG` | filled in *after* `opensea:verify` returns `VERIFIED`; never guessed |
| `OPENSEA_CACHE_TTL_*` | per-data-type cache seconds |

Load order: `../.secrets/opensea.env` → `opensea/.env` → `process.env`
(an empty value never clobbers a set one).

## Security invariants (enforced + tested)

- `x-api-key` on every request; the key is only ever logged via `redact()`
  (`3 chars…2 chars(len)`).
- The PAT is never placed in an `Authorization` header — only the exchanged JWT.
- Structured logs contain endpoint / status / duration / rate-limit-remaining /
  public addresses only. `sanitizeForLog()` redacts secret-shaped keys; there is
  a test asserting a key never reaches a log line.
- 429 → honour `Retry-After` / `x-ratelimit-reset`; 5xx / network → exponential
  backoff + jitter; 4xx → surface immediately, no retry storm.
- Cursor pagination everywhere; **never** one request per token across 3,333 NFTs.
- Stale-while-error cache: a transient OpenSea outage serves the last good copy,
  never a fabricated zero.

## Current status (2026-09-08)

`opensea:discover` →

```
OPENSEA INDEX      NOT FOUND
STATUS             AWAITING DEPLOYMENT
```

The Solana / Metaplex Core collection has not been deployed, so OpenSea has
nothing to index. The slug `ghost-root` on OpenSea is an **unrelated Ethereum
collection** ("GHOST_ROOT", 19 items) — flagged as a non-match; do not touch it.
Full write-up: `reports/` and `../opensea/reports/` + the integration report.
