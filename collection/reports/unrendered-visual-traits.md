# GHOST//ROOT — unrendered visual traits (Prototype Pass 3, Phase 2)

_Generated 2026-09-11 by resolving every LAYER_STACK slot against the 13
composited test-batch tokens via `asset_manifest.resolve()` + `skip_reason()`._
7 tokens (0001–0003 Genesis, 0303/0803/1503/2803 legendary) use complete
authored scenes and are covered at the bottom.

## Compositor registration state (before Pass 3)

`generate_production.py` `UNREGISTERED_OVERLAY_SLOTS` omits, in order:
`rear_anatomy, architecture, corruption, witness_seam, implant_front,
interface`. Everything else in LAYER_STACK pastes when it resolves and is
not skipped by `skip_reason()` (`interface.none`, `implant.none`,
`corruption.intact`, `eyes.void`, `eyes.fractured` are empty by design).

## Per-category table (13 composited tokens)

| Category | In witness | Has asset (batch combos) | Registered in compositor | Visible in output | Action (Pass 3) |
| -------- | ---------- | ------------------------ | ------------------------ | ----------------- | --------------- |
| entity | yes (4 classes) | yes — encoded in `entity_base` filename | yes (via entity_base) | yes, always | keep; measure true anchors per base |
| material | yes (5 used) | yes — encoded in `entity_base` | yes (via entity_base) | yes, always | keep |
| architecture | yes (4 classes in batch) | yes — 13/13 resolve in `70_architecture` | NO (omitted) | only baked into base | redraw fitted overlays at true anchors, register |
| background | yes (4 plates) | yes 13/13 | yes | yes, always | keep; add contact shadow (integration) |
| mantle | yes (5 used) | yes 13/13 | yes | yes, always | keep; 0010 collar still NEEDS ART |
| eyes (biometric/thermal/optical) | yes | yes 4/4 | yes | yes where trait present | keep; thermal re-authored Pass 2 |
| eyes (void/fractured) | yes (9 tokens) | files exist on disk | NO (skipped by design) | empty sockets on base | deliberate VOID treatment; FRACTURED TBD (see below) |
| interface | yes (5 used + none) | yes 10/10 non-none resolve | NO (omitted) | never | redraw fitted at true anchors, register; box types restrained |
| implant | yes (5 used + none) | yes 11/11 non-none resolve | NO (omitted) | never | redraw fitted line hardware, register |
| rear_anatomy | derived (entity × implant) | yes 11/11 non-none resolve | NO (omitted) | never | register (paints behind bust; safe) |
| corruption | yes (4 used + intact) | yes 7/7 non-intact resolve | NO (omitted) | never | redraw bounded body-masked stencils, register |
| witness_seam | universal | yes (shared file) | NO (omitted) | never | redraw fitted slash, register |
| access | yes | n/a — metadata-only per schema decision | n/a | indirect (hardware/topology) | none |
| signal | yes | n/a — metadata-only per schema decision | n/a | none | none |
| environmental_depth | derived (background) | no files; baked into plates by design | n/a (not required) | baked | none |
| grain | universal | no files; baked into sources by design | n/a (not required) | baked | none |

## Key findings

1. **Zero missing files for the batch.** Every omitted slot resolves for every
   composited token that needs it. Registration is pure compositor + overlay
   quality work; no new art files are required to turn the slots on.
2. **Eyes are the only slot where "has asset" ≠ "should paste".**
   `eyes__void__*` are dark socket fills (deliberate treatment candidates).
   `eyes__fractured__*` are oversized glass-shard overlays that cover the
   whole head at composite scale — pasting them as-is would be a sticker
   regression. They need rescaling to socket bounds or a redraw.
3. **Geometric overlays (`70_architecture`, `80_corruption`, `90_witness_seam`,
   `60_*`) are drawn to spec anchors (eyes y=737) but the approved bases sit
   ~160 px higher (true sockets y≈576 on 0166).** Turning them on unmodified
   floats hardware off the face. All overlays must be redrawn to measured
   per-entity anchors first (see `attachment-anchors.json`).
4. **No Genesis- or Legendary-specific overlay layers exist** (by design —
   complete authored scenes). Differentiation for 0001–0003/0303/0803/1503/
   2803 happens inside the scene PNGs, not the compositor.
5. **Duplicate content:** `mantle__archive_coat__entity__hollow` ≡
   `mantle__ceramic_mantle__entity__hollow` (byte-identical);
   `mantle__cable_shroud__entity__human` ≡
   `mantle__archive_coat__entity__human`. Two traits, one file.
6. **`access` / `signal` never get paint** — locked schema decision, not an
   omission. No silent disappearances: every art-visible category is now
   accounted for above.
