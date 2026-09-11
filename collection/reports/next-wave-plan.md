# GHOST//ROOT next-wave generation plan (PLAN ONLY — nothing generated)

Full production requirement (resolver): **226 required / 131 present / 95 missing**. entity_base is complete (68/68).

Filesystem + current resolver are authoritative; do NOT regenerate an asset an older report calls missing if it now resolves.

| # | slot | expected | present | missing | type | method | dependencies | est. Gemini gen |
| ---: | --- | ---: | ---: | ---: | --- | --- | --- | ---: |
| 1 | architecture | 16 | 9 | 7 | reusable layer | build_geometric_layers.py / build_attachment_layers.py; gemini for organic | entity_base framing | ~7 |
| 2 | background | 7 | 4 | 3 | reusable layer | build_environment_layers.py (procedural) or gen_gemini env plate | none — independent | ~3 |
| 3 | eyes | 7 | 3 | 4 | reusable layer | rebuild_thermal_eyes.py / gen_gemini delta | entity_base eye-line | ~4 |
| 4 | mantle | 21 | 8 | 13 | reusable overlay | build_fitted_via_gemini.py (img2img delta), fit to shoulder anchors | entity_base shoulders | ~13 |
| 5 | implant_front | 20 | 9 | 11 | reusable overlay | build_fitted_via_gemini.py + fit_authored_layer.py | entity_base face anchors | ~11 |
| 6 | rear_anatomy | 20 | 9 | 11 | reusable overlay | build_fitted_via_gemini.py (behind-head) | entity_base head/shoulder | ~11 |
| 7 | interface | 15 | 7 | 8 | reusable overlay | build_fitted_via_gemini.py (lower-face) | entity_base face anchors | ~8 |
| 8 | corruption | 18 | 6 | 12 | reusable overlay | build_fitted_via_gemini.py / extract_delta_layer.py | entity_base surface | ~12 |
| 9 | authored_scene | 33 | 7 | 26 | FULL SCENE (expensive) | compose_authored_scenes.py + native 2048 gemini authored render | final art direction; genesis/legendary lore | ~52 |

**Estimated Gemini generations for the full remaining set: ~121** (reusable overlays ≈ one img2img pass each; authored scenes ≈ 1–2 each).

## Representative QA tokens per category (from resolver)

- **architecture** (7 missing): tokens [6, 113, 115, 168, 183, 193]
- **background** (3 missing): tokens [7, 12, 75]
- **eyes** (4 missing): tokens [6, 21, 62, 96]
- **mantle** (13 missing): tokens [5, 7, 11, 16, 19, 20]
- **implant_front** (11 missing): tokens [16, 17, 19, 20, 21, 129]
- **rear_anatomy** (11 missing): tokens [16, 17, 19, 20, 21, 129]
- **interface** (8 missing): tokens [6, 13, 14, 31, 38, 62]
- **corruption** (12 missing): tokens [6, 24, 26, 32, 38, 41]
- **authored_scene** (26 missing): tokens [403, 503, 603, 703, 903, 1003]

## Recommended wave order
1. **architecture / background / eyes** (18 missing) — cheap reusable layers, unblock the most tokens.
2. **mantle** (13) — reusable shoulder overlays fitted from existing bases.
3. **implant_front / rear_anatomy / interface / corruption** (42) — reusable face/head overlays via the fitted-Gemini delta pipeline.
4. **authored_scene** (26) — expensive native 2048 authored scenes (genesis/legendary); do last, after art-direction sign-off.

Prioritise reusable assets before authored scenes. Final order should follow actual resolver dependencies and the outstanding art-direction confirmations from the entity-base visual QA (inset-as-frame, red biosynth tint).
