# GHOST//ROOT — OPENSEA INTEGRATION REPORT

_Generated 2026-09-08 · OpenSea API v2 · Solana / Metaplex Core · secondary market only_

---

## EXECUTIVE SUMMARY

The complete, API-driven OpenSea integration is **built, tested, and in place**.
It runs today in **`AWAITING_SOLANA_COLLECTION_DEPLOYMENT`** mode because the
authoritative GHOST//ROOT collection **has not been deployed on Solana yet**
(`collection/deployment-addresses.md`: "No contracts have been deployed";
`release-manifest.json`: `DESIGN_ONLY_NOT_MINT_READY`, `rendered_identities: 0`).

Per the task's own instruction (section 8), **no substitute EVM / Base / SeaDrop
contract was created**, nothing was migrated off Solana, and no fake OpenSea
collection was claimed. Every downstream step is automated and waits on one
manual action: **deploy the Metaplex Core collection.**

The OpenSea slug `ghost-root` *does* exist — it is an **unrelated Ethereum
collection** ("GHOST_ROOT", 19 items, contract `0x3ad41f…`). The integration
detects this as a `MISMATCH` and will never touch it.

---

## STATUS TABLE

| Item | Status |
| --- | --- |
| **API STATUS** | ✅ Authenticated. `GET /chains` 200. Solana supported (`chain: solana`, SOL, Solscan). Rate limit 120/min, headers observed live. |
| **PROFILE STATUS** | ⏸ Target defined (`ghostroot` / bio / ghostroot.site). `opensea:profile-sync` dry-run ready. Apply needs a wallet PAT (human SIWX step) + a creator profile to exist. |
| **SOLANA COLLECTION ADDRESS** | ❌ Not deployed (`SOLANA_COLLECTION_ADDRESS` empty). |
| **CANDY MACHINE ADDRESS** | ❌ Not deployed. |
| **OPENSEA INDEX STATUS** | `NOT_INDEXED` — search for "ghost root"/"ghostroot" returns `[]`. Nothing to index until minted. |
| **OPENSEA COLLECTION SLUG** | Not resolved (correctly — no guessing). `ghost-root` flagged as a non-match. |
| **COLLECTION METADATA STATUS** | ⏸ Target defined (name / 3,333 / description / ghostroot.site). `opensea:collection-sync` = DISCOVER→VERIFY→DIFF→VALIDATE→APPLY→READ-BACK, aborts unless `VERIFIED`. |
| **PROFILE METADATA STATUS** | ⏸ As above. |
| **IMAGE STATUS** | ⏸ Upload flow identified (`upload_profile_image`, `upload_collection_image`). Assets to supply: GHOST//ROOT logomark + wide hero. No re-compression of source art. |
| **SAMPLE NFT INDEX STATUS** | ⏸ Validator built (`GHOST//NNNN` name, 13 trait types, null/dup detection). Runs on `opensea:verify` once indexed; sample IDs 1, 2, 31, 303, 1842, 3333. |
| **TRAIT STATUS** | ⏸ Audit built vs `collection/metadata/schema.json` (case variants, out-of-vocab types, missing categories). Runs post-index. |
| **ROYALTY STATUS** | `PENDING_DEPLOYMENT` — target 500 bps (5%). On-chain Metaplex Core config is authoritative; OpenSea royalty treated as informational, never written. No conflict possible yet. Full report: `royalty-verification.md`. |
| **MARKET DATA STATUS** | ⏸ Floor / volume / owners / listings / 24h / activity all wired through cached WP REST proxy. Shows `—` / "MARKET DATA // TEMPORARILY UNAVAILABLE", never a fake zero. |
| **WEBSITE INTEGRATION STATUS** | ✅ WP plugin module + REST proxy + `[ghost_root_marketplace]` + `[ghost_root_market_state]` + verify-page section + footer state + admin screen. |
| **VERIFY PAGE STATUS** | ✅ `/verify/` shows MARKETPLACE VERIFICATION with state (`AWAITING DEPLOYMENT` now) + per-field checks + notes. States: PENDING / NOT INDEXED / PARTIAL / VERIFIED / MISMATCH / ERROR. |
| **SECURITY STATUS** | ✅ Key server-side only (wp-config constant / env). Never in DB, JS, HTML, REST responses, logs, or screenshots. 22 PHP + 30 Node tests, semgrep clean, leak scan clean. |
| **RATE LIMIT / CACHE STATUS** | ✅ Live-header driven. 429 → `Retry-After`; 5xx/network → backoff+jitter (Node) / fail-to-cache + cron retry (WP). Per-type transient TTLs + stale-while-revalidate + stale-while-error. Cursor pagination; never 3,333 single-item calls. |
| **REMAINING BLOCKERS** | 1 — the Solana collection is not deployed. |
| **MANUAL ACTIONS REQUIRED** | See below. |

---

## WHAT WAS BUILT

### 1. Operator toolkit — `opensea/` (Node ≥22, TypeScript, zero runtime deps)

```
src/   config · logger · ratelimit · cache · client · auth · types
       collection · stats · activity · nfts · profile · discovery · verify · ui
scripts/  opensea-bootstrap · opensea-discover · opensea-verify
          opensea-profile-sync · opensea-collection-sync · opensea-healthcheck
test/  30 tests (mocked fetch): auth headers, secret redaction, 404/429/500/
       malformed-JSON/outage, rate-limit observance, request dedupe, pagination,
       cache SWR + stale-while-error, verification (VERIFIED/MISMATCH/PARTIAL/
       AWAITING/NOT_INDEXED), stats + trait + activity parsing
reports/  discovery.md · verification.md · health.md · api-discovery.md
          royalty-verification.md · not-indexed.md · this file
```

- Every write script defaults to `--dry-run`. A real mutation needs `--apply`,
  an `OPENSEA_SCOPED_TOKEN` (PAT from an interactive SIWX session — a human
  step), and, for collections, a `VERIFIED` identity check. The toolkit holds
  **no private key or seed phrase**.
- `npm run opensea:discover` today:
  ```
  OPENSEA INDEX      NOT FOUND
  IDENTITY CHECK     AWAITING_SOLANA_COLLECTION_DEPLOYMENT
  STATUS            AWAITING DEPLOYMENT
  candidates: ghost-root "GHOST_ROOT" chain=ethereum  [UNCONFIRMED — do not touch]
  ```
- `npm run opensea:health` today: 0 fail · 4 warn (all "awaiting"/"no slug") · 5 ok.

### 2. WordPress plugin module — `ghost-root-core/src/OpenSea/`

```
Config        key from wp-config constant / env ONLY (never the options table)
Client        wp_remote_request, x-api-key, redacted logging, rate-limit capture,
              429/5xx → WP_Error, self-throttle via the plugin RateLimiter
Cache         transient SWR: fresh → stale (+queued refresh) → stale-on-error → null
Collection    metadata fetch + sanitised public shaping
Stats         normalisation — missing figure = null, never 0
Activity      events + best-listings summary (floor from listings)
Verification  state machine; chain+address critical, name/supply/url informational
Rest          /ghost-root/v1/opensea/{status,collection,stats,activity,listings,
              verification}  (public_read, fail-open 200) + /refresh (manage_options
              + nonce, cache purge only — no OpenSea write from WP)
Admin         GHOST//ROOT → OpenSea: status board, identity checks, cache ages,
              rate-limit snapshot, purge button, operator command crib
Service       wiring + wp-cron warmer (every 5 min, only when a key is set) +
              [ghost_root_marketplace] + [ghost_root_market_state] shortcodes
```

### 3. Theme integration — `themes/ghost-root/`

- `page-verify.php` — MARKETPLACE VERIFICATION block + `[ghost_root_marketplace]`.
- `single-ghost_identity.php` — MARKET STATE panel per identity.
- `footer.php` — "SECONDARY MARKET / <state>" + OpenSea link when tradable.
- `assets/css/site.css` + plugin `assets/components.css` — native GHOST//ROOT
  styling (monochrome, forensic, mono type). `assets/market.js` — hydrates from
  the local proxy, fails silent to the server-rendered fallback.

### 4. Config

- `/.env.example` (root) + `opensea/.env.example` — every var documented, no
  secret values.
- wp-config: `define( 'GHOST_ROOT_OPENSEA_API_KEY', '…' );` (or server env).
- `wp-admin → GHOST//ROOT → System → OpenSea collection slug` (set only after
  VERIFIED).

---

## SECURITY AUDIT (task sections 12, 16, 28, 35, 39)

| Check | Result |
| --- | --- |
| API key in git history | ❌ none — `.secrets/` gitignored, `.env` gitignored, `.env.example` blank |
| API key in JS bundles / HTML / page source | ❌ none — grep of theme + client assets clean; key only in `Client.php` (server) |
| API key in REST responses | ❌ `/opensea/status` strips `key_source`; test asserts the key string never appears |
| API key in logs | ❌ `Log::line` + `Client::redact` — `8b1…88(32)` form only; test asserts |
| Browser → OpenSea direct calls | ❌ none — Browser → WP REST proxy → OpenSea only |
| PAT in `Authorization` header | ❌ exchanged for a short-lived JWT first (`auth.ts`) |
| Private key / seed phrase stored or requested | ❌ never — wallet signing is a human step |
| Admin mutation without `manage_options` + nonce | ❌ `/opensea/refresh` requires both; test asserts 401/403 |
| Public endpoints leak admin controls | ❌ read-only; `key_source` removed from public payload |
| Stack traces / secrets in errors | ❌ `sanitize_text_field` on OpenSea error messages, generic codes |
| Page breaks when OpenSea is down | ❌ fail-open 200 + SWR stale + "TEMPORARILY UNAVAILABLE" copy |
| SeaDrop / EVM contract created | ❌ none |
| Primary mint still Solana + Metaplex Core | ✅ untouched (`Config::mint_config()` unchanged) |

Tooling: `tsc --noEmit` clean · 30 Node tests pass · 22 PHP unit tests pass ·
`semgrep --config=auto` clean on all new/changed files.

**Pre-existing housekeeping (not introduced here, but worth fixing):**
`API.txt` at the repo root and `site/Ghost/API.txt` (a nested clone) contain the
raw OpenSea key + scoped token in plaintext. They are untracked, not part of this
integration, and predate it — but they should be deleted now that the same values
live in `.secrets/opensea.env` (gitignored). Add `API.txt` to `.gitignore` or
remove it.

---

## MANUAL ACTIONS REQUIRED

### Blocker (everything waits on this)

1. **Deploy the GHOST//ROOT Metaplex Core collection on Solana** (name, 3,333
   supply, `Royalties` plugin at 500 bps → treasury). Record the collection
   address, Candy Machine, update authority, treasury, metadata/artwork roots
   and deployment hash in `collection/deployment-addresses.md`.

### After deployment (then it's automated)

2. Put the real addresses in the environment: `SOLANA_COLLECTION_ADDRESS` etc.
   in `opensea/.env`; `define( 'GHOST_ROOT_OPENSEA_API_KEY', … )` in `wp-config.php`
   (the key is currently only in `.secrets/opensea.env` for the toolkit).
3. `cd opensea && npm run opensea:discover` — resolves the slug from the address.
   If not auto-indexed within ~48h, follow `reports/not-indexed.md` (list one
   item / OpenSea collection-support flow).
4. `npm run opensea:verify` — must print `VERIFIED`.
5. Save the slug: `wp-admin → GHOST//ROOT → System`, and `OPENSEA_COLLECTION_SLUG`
   in `opensea/.env`.
6. `npm run opensea:collection-sync -- --dry-run`, review, then `--apply` with a
   `write:collections` PAT.
7. `npm run opensea:profile-sync -- --account <creator wallet>` dry-run, then
   `--apply` with a `write:profile` PAT. Upload the logomark + hero via the
   documented image endpoints.
8. Confirm royalty shows 5% to the treasury on OpenSea (do not add a second one).
9. Deploy the WP changes to ghostroot.site (theme + `ghost-root-core` plugin) and
   run the launch QA in `reports/not-indexed.md` §"FINAL QA" on desktop / tablet /
   mobile; confirm in DevTools Network that no request from the browser carries
   `x-api-key`.

### Optional / housekeeping

- If the OpenSea key is an instant free-tier key, set
  `OPENSEA_API_KEY_EXPIRES_AT` so `opensea:health` warns before it lapses.
- Consider requesting the OpenSea slug `ghost-root-solana` / `ghostroot` at mint
  time; never attempt to claim the existing `ghost-root` (different project).

---

## SUCCESS CRITERIA CHECKLIST

| Criterion | State |
| --- | --- |
| OpenSea API authentication works | ✅ |
| No API secrets exposed client-side | ✅ |
| GHOST//ROOT Solana address is the authority | ✅ (mechanism; address not yet deployed) |
| OpenSea collection lookup automated | ✅ (`opensea:discover`, address-based) |
| Collection identity address-verified | ✅ (chain + contract critical; MISMATCH disables writes) |
| Slug discovered, not guessed | ✅ |
| Creator profile configured where API permits | ⏸ dry-run ready; needs profile + PAT |
| Collection metadata configured where permitted | ⏸ dry-run ready; needs VERIFIED + PAT |
| Images uploaded via supported APIs | ⏸ flow identified; needs assets + PAT |
| Sample NFTs validated | ⏸ validator ready; runs post-index |
| Traits validated | ⏸ audit ready; runs post-index |
| Market stats available through backend | ✅ (`/ghost-root/v1/opensea/*`) |
| `/verify/` reports OpenSea status | ✅ |
| Identity pages can show real marketplace data | ✅ (`[ghost_root_market_state]`) |
| Caching enabled | ✅ (Node FS cache + WP transient SWR) |
| 429 handling | ✅ |
| Outage handling | ✅ (fail-open + stale) |
| Dry-run for writes | ✅ (default) |
| No EVM / SeaDrop contract created | ✅ |
| Primary mint remains Metaplex Core on Solana | ✅ |
| Final security audit passes | ✅ |

**Everything that can be done without the deployed collection and without a
human wallet signature is done. The one remaining blocker is the Solana
deployment itself.**
