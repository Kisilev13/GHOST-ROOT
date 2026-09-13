# GHOST//ROOT — social account setup runbook

Manual steps for a human authorized to represent the project. Nothing here
can be automated: platform signup requires an email/phone the platform can
verify and a person accepting that platform's Terms of Service.

## 0. Before creating anything

- [ ] Run a real availability/collision check on each target platform for
      the handle candidates below. `collection-brief.md` explicitly flags
      these as **unverified** — do not treat this list as a claim of
      availability or rights.
- [ ] Prefer a handle consistent across platforms. Candidates, in order of
      preference: `ghostroot`, `ghostrootarchive`, `ghostrootnet`.
      Slashes (`GHOST//ROOT`) belong in the wordmark and display name only —
      never in a handle or domain.
- [ ] Do not announce any handle publicly, in a README, or on the site until
      it is created and confirmed live (see `scripts/social-verify.ts`).

## 1. Platforms

Primary (already wired into `wp-admin → GHOST//ROOT → System`, see
`site/app/public/wp-content/plugins/ghost-root-core/src/Settings/Settings.php`):

- **X (Twitter)** — primary announcement channel.
- **Discord** — primary community channel.

Optional, only if the project decides to run them:

- Telegram, Instagram.

## 2. Account security (do this at creation time, not later)

- [ ] Register with a project-owned email address, not a personal one, so
      the account survives a personnel change.
- [ ] Enable 2FA (authenticator app or hardware key — not SMS-only) on the
      email and on the social account itself.
- [ ] Record recovery codes and the account's own recovery email/phone in
      the project's password manager / vault — never in this repo, `.env`,
      or WordPress. This repo's own settings sanitizer
      (`Settings.php::SECRET_RE`) actively rejects anything that looks like
      key material for the same reason: secrets do not belong in tracked
      config.
- [ ] Add a second admin/owner as backup so one person's account loss can't
      lock the project out. This satisfies the "Domain/social account
      recovery ... rehearsed" line in
      `collection/security/prelaunch-checklist.md`.
- [ ] Use the bio/description text from `bio-copy.md` verbatim or lightly
      adapted — it's already checked against the brand's banned-claims list.

## 3. After creation

- [ ] Run `npm --prefix social run social:verify -- --x <url> --discord <url>`
      (or set `X_URL` / `DISCORD_URL` env vars) to confirm the URL actually
      resolves and points at the right host.
- [ ] Paste the verified URL into `wp-admin → GHOST//ROOT → System` →
      "X URL" / "Discord URL" — that's the single source of truth the theme
      and OpenSea profile-sync (`opensea/src/types.ts`) read from.
- [ ] Do not set `contract_verified` or announce a canonical domain (see
      `collection-brief.md`) based on the social account alone — those are
      separate, still-unmet prelaunch gates.

## What never to post, per `collection-bible.md` §4

No fabricated partners, no anonymous-team claims that imply credentials, no
investment language, no follower-count flexing, no fake breach alerts, no
fake countdowns. Utility copy stays literal.
