# GHOST//ROOT — production art pipeline status

_2026-09-11 (Codex session), updated 2026-09-10 Pass 3 (Claude) on
`feat/production-art-pipeline`._

## Canonical schema

`collection/` 11-category / 6-band schema is locked.
WordPress 9-category / 5-band vocab is `SUPERSEDED_FOR_PRODUCTION` and is
**not** migrated in this task. See `trait-schema-reconciliation.md`.
`build_canonical_schema.py --check` PASSes as of Pass 3 (was STALE; source
drift from `asset_manifest.py` was regenerated in, not reverted).

## Counts

```text
LEGACY GENERIC INVENTORY: 105 (SUPERSEDED_FOR_PRODUCTION)
TOTAL COLLECTION REQUIRED EXPORT PATHS: 226
UNIQUE ASSETS REQUIRED FOR 20-BATCH: 72
PRESENT: 72
MISSING: 0
TEST IMAGES RENDERED: 20/20
EXACT DUPLICATES: none (exact-image dedupe; 2 pairs of *source layer files*
  are byte-identical across trait names — see visual review Pass 3 item 4)
VISUAL QA: REPEAT_VISUAL_PROTOTYPE_STAGE (Pass 3 — anchors for fitted
  overlays are now measured/correct, but the overlay art itself is still
  schematic, so it stays disabled; collars/legendary scenes unchanged)
```

## What this pass created

- Canonical schema lock + witness-driven asset plan
- Compositor: no generic anatomical fallback; missing non-empty layers fail;
  Genesis/legendary complete scenes; blank/size/PNG checks
- Validator: 20/20 images required (no “0 expected” bypass)
- 4 procedural 2048 archive backgrounds
- 4 entity masters (HUMAN / SYNTHETIC / HOLLOW / SPECTER)
- Sculptural entity bases, isolated eye/mantle cutouts
- Geometric architecture / corruption / witness-seam / attachment overlays
- 7 authored scenes (3 Genesis directed, 4 legendary class-master composites)
- Pass 3: measured true per-base attachment anchors (`measure_anchors.py`,
  `attachment-anchors.json`, verified in `anchor-verification.png`) and
  regenerated the overlay layers at those anchors (`build_fitted_overlays.py`)
  — evaluated and kept disabled; see visual review for why.

## Honest blockers before 3,333

Fitted overlay art (implant/interface/rear/corruption/seam) is still
schematic/wireframe, not integrated hardware — anchors are now correct, the
art is not. Legendary scenes (0303, 0803 at least) are not yet art-directed
beyond a generic class motif. The 0010 collar needs re-authoring. Two mantle
trait pairs share one source file. All four require real illustration this
session has no tool to produce. See `test-batch-visual-review.md` Pass 3.

Do not IPFS, deploy, mint, or spend SOL.
