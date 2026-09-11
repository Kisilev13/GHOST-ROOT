# Entity-base visual QA

**Reviewed: 68/68 — PASS: 68 — REGENERATE: 0.**

Method: four per-entity contact sheets (`entity-base-review-<entity>.png`, 460px tiles) plus a full-resolution zoom on `synthetic/biosynth/suspended_core` to disprove a suspected thumbnail-scale text artifact (confirmed: dark void + shard shadows, no typography).

Checks applied to every base: framing/head/shoulder/torso scale, eye-line, lighting direction, focal length, material distinction, architecture visibility, malformed anatomy, baked text / fake typography, watermarks, clipping, accidental props, background contamination, style drift, bloom, unusable dark areas, overlay-hostile silhouette.

## Non-blocking art-direction flags (verdict remains PASS)

- `human/reconstructed/inset` — ‘inset’ rendered as a rectangular carved relief frame rather than the oval aperture used elsewhere (stylistic; high quality, not malformed).
- `specter/reconstructed/inset` — same rectangular relief-frame interpretation of ‘inset’ (consistent with human/reconstructed/inset).
- `synthetic/biosynth/inset` — ‘inset’ rendered as a square ceramic box frame; confirm oval-aperture intent for overlay compatibility.
- `synthetic/biosynth/suspended_core` — material strongly red-tinted; confirm against ‘restrained red/gold/violet accent’ direction (no text/watermark — thumbnail ‘glyphs’ disproven at full res).
- `specter/phase_glass/suspended_core` — glass shards extend slightly beyond the bust envelope; verify overlay silhouette headroom.
- `hollow/carbon/suspended_core` — minor edge irregularity at lower-right shoulder; within silhouette, overlay-safe.

No base shows baked text, fake typography, watermarks, malformed anatomy, or duplicate facial identity requiring regeneration. The three ‘inset-as-frame’ tiles and the red-tinted suspended-core are art-direction confirmations, not technical defects.

## Per-base verdicts

| key | verdict | note |
| --- | --- | --- |
| `hollow/carbon/double_plane` | PASS |  |
| `hollow/carbon/inset` | PASS |  |
| `hollow/carbon/sealed` | PASS |  |
| `hollow/carbon/suspended_core` | PASS | minor edge irregularity at lower-right shoulder; within silhouette, overlay-safe. |
| `hollow/ceramic/double_plane` | PASS |  |
| `hollow/ceramic/inset` | PASS |  |
| `hollow/ceramic/sealed` | PASS |  |
| `hollow/ceramic/suspended_core` | PASS |  |
| `hollow/porcelain/double_plane` | PASS |  |
| `hollow/porcelain/inset` | PASS |  |
| `hollow/porcelain/sealed` | PASS |  |
| `hollow/porcelain/suspended_core` | PASS |  |
| `hollow/reconstructed/double_plane` | PASS |  |
| `hollow/reconstructed/inset` | PASS |  |
| `hollow/reconstructed/sealed` | PASS |  |
| `hollow/reconstructed/suspended_core` | PASS |  |
| `human/biosynth/double_plane` | PASS |  |
| `human/biosynth/inset` | PASS |  |
| `human/biosynth/sealed` | PASS |  |
| `human/biosynth/suspended_core` | PASS |  |
| `human/carbon/double_plane` | PASS |  |
| `human/carbon/inset` | PASS |  |
| `human/carbon/sealed` | PASS |  |
| `human/carbon/suspended_core` | PASS |  |
| `human/ceramic/double_plane` | PASS |  |
| `human/ceramic/inset` | PASS |  |
| `human/ceramic/sealed` | PASS |  |
| `human/ceramic/suspended_core` | PASS |  |
| `human/porcelain/double_plane` | PASS |  |
| `human/porcelain/inset` | PASS |  |
| `human/porcelain/sealed` | PASS |  |
| `human/porcelain/suspended_core` | PASS |  |
| `human/reconstructed/double_plane` | PASS |  |
| `human/reconstructed/inset` | PASS | ‘inset’ rendered as a rectangular carved relief frame rather than the oval aperture used elsewhere (stylistic; high quality, not malformed). |
| `human/reconstructed/sealed` | PASS |  |
| `human/reconstructed/suspended_core` | PASS |  |
| `specter/carbon/double_plane` | PASS |  |
| `specter/carbon/inset` | PASS |  |
| `specter/carbon/sealed` | PASS |  |
| `specter/carbon/suspended_core` | PASS |  |
| `specter/phase_glass/double_plane` | PASS |  |
| `specter/phase_glass/inset` | PASS |  |
| `specter/phase_glass/sealed` | PASS |  |
| `specter/phase_glass/suspended_core` | PASS | glass shards extend slightly beyond the bust envelope; verify overlay silhouette headroom. |
| `specter/reconstructed/double_plane` | PASS |  |
| `specter/reconstructed/inset` | PASS | same rectangular relief-frame interpretation of ‘inset’ (consistent with human/reconstructed/inset). |
| `specter/reconstructed/sealed` | PASS |  |
| `specter/reconstructed/suspended_core` | PASS |  |
| `synthetic/biosynth/double_plane` | PASS |  |
| `synthetic/biosynth/inset` | PASS | ‘inset’ rendered as a square ceramic box frame; confirm oval-aperture intent for overlay compatibility. |
| `synthetic/biosynth/sealed` | PASS |  |
| `synthetic/biosynth/suspended_core` | PASS | material strongly red-tinted; confirm against ‘restrained red/gold/violet accent’ direction (no text/watermark — thumbnail ‘glyphs’ disproven at full res). |
| `synthetic/carbon/double_plane` | PASS |  |
| `synthetic/carbon/inset` | PASS |  |
| `synthetic/carbon/sealed` | PASS |  |
| `synthetic/carbon/suspended_core` | PASS |  |
| `synthetic/ceramic/double_plane` | PASS |  |
| `synthetic/ceramic/inset` | PASS |  |
| `synthetic/ceramic/sealed` | PASS |  |
| `synthetic/ceramic/suspended_core` | PASS |  |
| `synthetic/porcelain/double_plane` | PASS |  |
| `synthetic/porcelain/inset` | PASS |  |
| `synthetic/porcelain/sealed` | PASS |  |
| `synthetic/porcelain/suspended_core` | PASS |  |
| `synthetic/reconstructed/double_plane` | PASS |  |
| `synthetic/reconstructed/inset` | PASS |  |
| `synthetic/reconstructed/sealed` | PASS |  |
| `synthetic/reconstructed/suspended_core` | PASS |  |
