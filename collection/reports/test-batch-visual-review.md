# GHOST//ROOT — test-batch visual QA (Phase 12)

_2026-09-10. This gate cannot be evaluated yet, and this report says so rather than fabricating
a verdict._

## Why

Phase 12 asks for review of: visual consistency, silhouette diversity, trait readability, layer
collisions, clipping, halos, unintended transparency, color conflicts, weak differentiation,
overly similar identities, broken trait combinations, whether legendary/Genesis identities look
sufficiently distinct, generative artifacts, text rendering errors.

**Every one of these is a judgment about pixels. Zero of the 20 test-batch images exist to
judge** — `generate_production.py --report-only` correctly resolved 0/20, because 0 of the 105
layer assets `ASSET-MANIFEST.md` lists exist yet (`production-art-audit.md`, Finding 2). This is
not a rendering bug; it is the honest, correct output of a generator with nothing to composite.

## What was actually checked instead (mechanical, not visual)

These are the checks that don't require pixels, and they did run — see
`test-batch-validation.md`/`.json` for the full detail:

- The compositor's mechanics are correct: `selftest_generator.py` proves z-order compositing,
  canvas-size enforcement, determinism, and rejection of malformed layers, using synthetic
  (non-art) temporary assets.
- The 20-identity trait vectors, metadata, and rarity-band assignment are internally consistent
  and match `design-witness.csv` exactly — no trait mismatches, no duplicate/null/unexpected
  categories, no schema violations beyond the one expected pre-upload `image` field, legendary
  reservations respected.

None of that establishes "silhouette diversity" or "Genesis identities look appropriately
special" — those are exactly the properties the v2 AI-portrait review (`visual-tests-v2/reports/
visual-review.md`) found lacking, and this pass used a completely different pipeline (deterministic
layer compositing per the art bible, not per-portrait AI generation), so that review's verdict
doesn't carry over either way.

## Verdict

**GATE NOT EVALUATED — BLOCKED ON MISSING VISUAL ASSETS, not rejected or approved.**

Neither `APPROVED_FOR_PRODUCTION_RENDER` nor `REPEAT_VISUAL_PROTOTYPE_STAGE` applies: both
presuppose images to look at. The correct manifest status is `NO_ASSETS_TO_RENDER` (recorded in
`release-manifest.json`).

## What unblocks this gate

Produce real layer assets for the 105 required paths in `ASSET-MANIFEST.md` (or enough of them
to composite this specific 20-token batch — cross-reference the selection's trait values against
the manifest to find the minimum set). That is an illustration/3D art production task, not
something this pass can originate. Once assets exist:

1. `generate_production.py --ids <the 20> --dest collection/assets/test-batch` — real renders.
2. Regenerate `test-batch-contact-sheet-status.md` as a real image contact sheet.
3. Re-run `validate_test_batch.py` (artwork checks — dimensions, duplicates — will then be live).
4. Only then can this file's actual QA checklist be performed, and a real
   `APPROVED_FOR_PRODUCTION_RENDER` / `REPEAT_VISUAL_PROTOTYPE_STAGE` verdict be issued.
