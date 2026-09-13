# GHOST//ROOT — social presence toolkit

This directory is the *operator* side of GHOST//ROOT's social accounts (X,
Discord, and optionally Telegram/Instagram): setup runbook, on-brand copy
drafts, and a read-only link verifier. It does not and cannot create or log
into accounts on any platform — account creation is a human action (email
ownership, phone/CAPTCHA verification, accepting a platform's Terms of
Service) that has to be done by a person authorized to represent the
project.

```
you (human)  ──▶  create account by hand, using SETUP.md + bio-copy.md
                          │
                          ▼
        social/scripts/social-verify.ts  (confirms the URL is live)
                          │
                          ▼
        paste the verified URL into wp-admin → GHOST//ROOT → System
        (Settings.php: `x_url`, `discord_url` — already wired into the
        theme's `og:` tags and the OpenSea profile-sync types)
```

## Why this isn't "just create the account"

`collection/collection-brief.md` and `collection/collection-bible.md` are
explicit: trademark/name clearance, domain, X handles, Discord server names,
and marketplace names must be checked **before public identity lock**, and
"[a]vailability and rights are unverified." Locking in a handle prematurely
(before clearance, or under a name someone else can contest) is a launch
risk, not a shortcut. `collection/security/prelaunch-checklist.md` lists
"Domain/social account recovery ... rehearsed" as an unmet operations gate.

So this toolkit stops at the boundary the rest of the repo already respects
(see `opensea/README.md` for the same pattern applied to OpenSea): prepare
everything a human needs to do the account creation correctly and securely,
verify the result once it exists, never fabricate or guess a handle.

## Files

| File | Purpose |
| --- | --- |
| `SETUP.md` | Step-by-step manual runbook: handle candidates, account security, what to avoid |
| `bio-copy.md` | Ready-to-paste bios, descriptions, and a pinned/announcement draft, in brand voice |
| `scripts/social-verify.ts` | Zero-dependency script that checks configured URLs resolve and match the expected platform host; writes `reports/verify.md` |

## Current status

No account has been created or verified by this toolkit. `x_url` and
`discord_url` in wp-admin are blank, matching the prelaunch checklist's
"NOT READY TO MINT" state. Do not announce a handle publicly until it is
created, secured (see `SETUP.md`), and confirmed live.
