# GHOST//ROOT — Solana devnet canary rehearsal

Everything under `collection/devnet/` is a disposable devnet-only rehearsal, not
production data. It proves the Metaplex Core deployment path (collection
creation, 500 bps royalty plugin, single canary mint, collection-membership
verification) works end to end, independent of the still-open visual-QA
blocker documented in `collection/reports/test-batch-visual-review.md`
(verdict remains `REPEAT_VISUAL_PROTOTYPE_STAGE`, not approved).

- `assets/0001.png` — byte-identical copy of the already-validated
  `collection/assets/test-batch/0001.png`, hosted here (and referenced via
  its `raw.githubusercontent.com` URL, pinned to a specific commit) purely so
  the devnet canary's on-chain metadata has a real, reachable image URI
  instead of the production placeholder `pending://...`.
- `metadata/0001.json` — devnet-labeled copy of
  `collection/metadata/test-batch/0001.json` with a real image URI, used only
  to mint the devnet canary. Production metadata is never overwritten with a
  devnet URL.
- `deployment-receipt.json` / `.md` — written after the devnet deployment
  runs; see there for the actual collection/asset addresses, transaction
  signatures, and explorer links. All addresses in this directory are
  **DEVNET ONLY** and hold no value.

No production/mainnet config is read or written from this directory. See
`collection/scripts/deploy_devnet_canary.ts` for the guarded deployment
script (refuses to run against anything but a devnet-class cluster).
