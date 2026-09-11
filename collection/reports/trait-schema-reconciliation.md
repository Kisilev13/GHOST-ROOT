# GHOST//ROOT — trait-schema reconciliation

_2026-09-11. Phase 1-2 of the trait-schema-conflict task. Read in full:
`trait-architecture.md`, `traits.yaml`, `art-bible.md`, `design-witness.csv`,
`production-art-audit.md`, `ASSET-MANIFEST.md`, and
`site/app/public/wp-content/plugins/ghost-root-core/src/Support/Vocab.php`
(the live WordPress trait system's single source of truth, confirmed against
`Frontend/Shortcodes.php`'s meta-key usage: `entity, face, eyes, mask,
implant, corruption, access, background, signal, state, rarity_band,
fingerprint, archetype`)._

## Decision

**`collection/`'s 11-category, Architecture-driven, 6-band schema
(`design-witness.csv`) is the canonical production schema.** The live
WordPress plugin's 9-category, 5-band schema (`Vocab::TRAITS`,
`Vocab::RARITY_BANDS`) is **superseded for production** and needs migrating
to match — that migration is **not done in this task** (see "What this
means for WordPress" below).

## Reasoning (documented, not assumed)

1. **`design-witness.csv` is the more rigorous, more recently validated
   design.** It is a complete, quota-exact, 3,333-row assignment
   (`validate_design.py` → `PASS_DESIGN_CONSTRAINTS_ONLY`, zero duplicate
   trait vectors, cryptographically hash-witnessed in
   `release-manifest.json`). `Vocab.php`'s vocabulary has no comparable
   validation artifact — no witness file, no quota proof, no duplicate-check
   — it reads as a website/narrative-layer vocabulary built during early
   site development, not a formally solved collection design.
2. **It's structurally more complete.** `collection/` has an `Architecture`
   category that *drives* the rarity band (SEALED→COMMON …
   SINGULAR→1/1, an explicit joint table with Access —
   `trait-architecture.md`). WordPress's five rarity bands
   (STANDARD/CORRUPTED/ADMIN/ROOT/GENESIS, counts 2700/500/100/30/3) have no
   equivalent structural driver in `Vocab.php` — they read as hand-picked
   labels, not the output of a category/quota system.
3. **This task, and the one before it, both explicitly instruct treating
   `design-witness.csv` as authoritative** unless a fatal design flaw is
   found. None was found — the conflict is a genuine pre-existing mismatch
   between two design passes, not a flaw in either taken alone.
4. **Regenerating `design-witness.csv` to fit WordPress's vocabulary would
   destroy validated, quota-exact, hash-witnessed work** (3,333 unique
   trait vectors, exact target counts for 63 trait values) to match a
   simpler, unvalidated system — a large loss for no design gain. Adapting
   WordPress the other direction preserves both: the rigorous design stays
   intact, and the live site gains a more complete, validated trait model.

## Category mapping

| Production (`collection/`, canonical) | WordPress (`Vocab.php`) | Mapping | Art-visible? | Decision |
| --- | --- | --- | --- | --- |
| **Entity** (4: HUMAN/SYNTHETIC/HOLLOW/SPECTER) | Entity (4: same) | 1:1 identical | yes | Keep — no change needed either side |
| **Access** (4: USER/ADMIN/SYSTEM/ROOT) | Access (5: USER/**OPERATOR**/ADMIN/SYSTEM/ROOT) | Production is a strict subset; WP has an extra value never used in any design-witness row | metadata-only (importance 1) | WP's `OPERATOR` becomes a deprecated alias — no token will ever have it once migrated |
| **Architecture** (6: SEALED/INSET/DOUBLE PLANE/SUSPENDED CORE/SEVERED HALO/SINGULAR) | *(no category)* | No mapping exists | yes — drives Z70 layer + the entire rarity band | **New field WordPress must add.** This is the structural core of the conflict |
| **Face Material** (6: BIO-SYNTH/PORCELAIN/CARBON/CERAMIC/RECONSTRUCTED/**PHASE GLASS**) | Face (6: PORCELAIN/CARBON/**CHROME**/RECONSTRUCTED/CERAMIC/BIO-SYNTH) | 5/6 overlap; WP's `CHROME` has no production equivalent, production's `PHASE GLASS` has no WP equivalent | yes | `CHROME` → deprecated alias; `PHASE GLASS` is new |
| **Eyes** (6: BIOMETRIC/VOID/THERMAL/FRACTURED/OPTICAL ARRAY/SIGNAL-BURN) | Eyes (9: same 6 + **NULL APERTURE, REVOCATION LENS, WITNESS ARRAY**) | Production is a strict subset (`traits.yaml` only ever defines these 6 — no design-witness row can contain the other 3) | yes | The 3 WP-only values become deprecated aliases |
| **Interface** (6: NONE/FORENSIC PLATE/RESPIRATOR/NEURAL VEIL/NULL MASK/SKELETAL INTERFACE) | **Mask** (same 6 values) | 1:1 identical values, different **category name** | yes | Rename WP's `Mask`/`mask` meta key to `Interface`/`interface`; keep `mask` as a deprecated alias for read compatibility during migration |
| **Implant** (7: NONE/NEURAL CABLE/SPINAL BUS/ANTENNA/ROOT PORT/**MEMORY SPINDLE**/SIGNAL CROWN) | Implant (6: NEURAL CABLE/ANTENNA/**OPTICAL ARRAY**/SPINAL BUS/SIGNAL CROWN/ROOT PORT) | Overlap on 5; WP is missing `NONE` and `MEMORY SPINDLE`; WP's `OPTICAL ARRAY` as an implant is a category-confusion artifact (it's an Eyes value in production) | yes | WP needs `NONE` + `MEMORY SPINDLE` added; WP's implant-side `OPTICAL ARRAY` becomes a deprecated alias |
| **Mantle** (6: ARCHIVE COAT/CERAMIC MANTLE/CABLE SHROUD/FIELD COLLAR/SPLIT SHELL/UNSUPPORTED FRAME) | *(no category)* | No mapping exists | yes | **New field WordPress must add** |
| **Background** (7: EVIDENCE VOID/COLD STORAGE/RACK SHADOW/RELAY WELL/SERVICE TUNNEL/ANECHOIC ROOM/UPLINK BLACK) | Background (7: SERVER/BLACKSITE/SATELLITE/VOID/ARCHIVE/COLD STORAGE/SIGNAL CHAMBER) | Only `COLD STORAGE` overlaps; otherwise disjoint vocabularies | yes | Full replacement; every WP value becomes a deprecated alias |
| **Signal** (5: LOCKED/INTERMITTENT/CARRIER ECHO/SILENT/UNCLOCKED) | Signal (6: STABLE/DEGRADED/INTERMITTENT/CORRUPTED/NULL/UNKNOWN) | Only `INTERMITTENT` overlaps | metadata-only (importance 1) | Full replacement; note `Vocab::TRAITS['Signal']` is defined twice in the PHP array literal (harmless — same value both times, but worth cleaning up when this migrates) |
| **Origin Corruption** (6: INTACT/SCAN SHEAR/CHECKSUM BURN/PACKET GHOSTING/MEMORY BLEED/ABSENCE) | Corruption (7: GLITCH/BURN/SIGNAL LOSS/FRAGMENTATION/PACKET GHOSTING/MEMORY BLEED/NONE) | Partial: `PACKET GHOSTING`/`MEMORY BLEED` match; `INTACT`≈`NONE` (rename); rest differ | yes | Remap; `GLITCH`/`SIGNAL LOSS`/`FRAGMENTATION` → deprecated aliases, `SCAN SHEAR`/`CHECKSUM BURN`/`ABSENCE` are new |
| **State** (4: DORMANT/ACTIVE/COMPROMISED/ROOTED) | State / `GHOST_STATES` (identical 4) | 1:1 identical | metadata (dynamic narrative, not a generation-time trait in either system) | Keep — no change needed either side |
| **Rarity Band** (6: COMMON/UNCOMMON/RARE/EPIC/LEGENDARY/1-1; **derived from Architecture**, counts 1800/900/420/180/30/3) | Rarity Band (5: STANDARD/CORRUPTED/ADMIN/ROOT/GENESIS; independent field, counts 2700/500/100/30/3) | **No mapping — different band count, different names, different quotas, different derivation.** This is the core of the conflict, not a rename | yes — drives site-wide filtering/display and the mint narrative | See "Rarity band migration" below |
| *(none)* | **Archetype** (12 lore names, e.g. "THE OBSERVER") | Production only names 10 archetypes, and only for the 30 legendary slots (`legendary-reservations.yaml`'s `archetype` field) — not a per-token field for all 3,333 | metadata/lore-only, never drives art or rarity in either system | **Out of scope for this reconciliation.** Keep as a WordPress-only display/lore field; it doesn't conflict with the production trait system because it was never part of it |

## Rarity-band migration (the hard part)

WordPress's `STANDARD`/`CORRUPTED`/`ADMIN`/`ROOT`/`GENESIS` bands are used
throughout the live site (`Vocab::RARITY_BANDS`, archive filters, identity
card badges, the front-page narrative) with counts baked into copy and
possibly display logic. Production's `COMMON`/`UNCOMMON`/`RARE`/`EPIC`/
`LEGENDARY`/`1-1` bands, derived from `Architecture`, have **different
counts and one more band**. There is no lossless 1:1 rename — this needs an
explicit decision by whoever owns the site's narrative copy (do "STANDARD"
identities become "COMMON", does "GENESIS" become "1-1" or stay a distinct
top label over the `SINGULAR` architecture, etc.), not something this audit
invents. Recorded as an open decision in `canonical-trait-schema.json`'s
`deprecated_wordpress_aliases.rarity_band` block, not resolved here.

## What this means for WordPress (explicitly NOT done in this task)

This reconciliation is documentation and a locked target schema
(`canonical-trait-schema.json`) — it does not touch
`site/app/public/wp-content/plugins/ghost-root-core/`. Migrating the live
plugin to the canonical schema is real, separate, live-site-risk-bearing
engineering work: `Vocab.php` (rewrite `TRAITS`/`RARITY_BANDS`), `Support/`
validation, `PostTypes/`/`Metadata/IdentityMeta.php` (new `architecture` and
`mantle` meta keys), `Frontend/Shortcodes.php` + theme templates (every
place a trait label or rarity band renders), and a content-migration pass
for whatever identity posts already exist. That is out of scope here and
should be its own planned, backed-up, reviewed change — matching how the
OpenSea integration work earlier in this project treated live-site changes.

## Files changed for this reconciliation

- `collection/reports/trait-schema-reconciliation.md` (this file)
- `collection/manifests/canonical-trait-schema.json` (the locked schema)

No file under `site/` was modified. No file in `collection/` outside the two
above was modified by this phase.
