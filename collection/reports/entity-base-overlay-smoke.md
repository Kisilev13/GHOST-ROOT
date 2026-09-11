# Entity-base overlay compatibility smoke test

**Verdict: PASS — all approved overlay families register correctly on the 68 new entity bases; no refit required.**

Two methods were used:

1. **Token composites (sanctioned 20-ID test batch)** via `generate_production.py` — 20/20 rendered. These exercised `implant_front` (11 tokens) and `corruption` (7 tokens) over the new bases across all four entities (human, hollow, specter, synthetic). Implants sit on the face/head/shoulder anchors, collars align to the neck, corruption maps to the surface. No floating hardware; no systematic misregistration.

2. **Direct full-stack composites** (one overlay per slot, correct z-order) for each entity, to also exercise the slots the fixed test-batch tokens do not use. z-order verified: `20_rear_anatomy → 30_mantle → 40_entity_base → 60_interface → 60_implant_front → 80_corruption`.

## Overlay availability (current resolver + filesystem)

| slot | dir | present |
| --- | --- | ---: |
| rear_anatomy | `20_rear_anatomy/` | 9 |
| mantle | `30_mantle/` | 8 |
| entity_base | `40_entity_base/` | 68 |
| interface | `60_interface/` | 7 |
| implant_front | `60_implant_front/` | 9 |
| corruption | `80_corruption/` | 6 |

All five overlay families are implemented and were registration-tested. (An earlier note that rear_anatomy/mantle/interface were "not implemented" was a wrong-directory lookup and is corrected here.)

## Registration checks

- **Neural cable / antenna registration** — rear_anatomy antenna rods and neural cables rise behind the head/shoulders at the correct height and anchor to temple/chest connectors. OK.
- **Rear-layer placement** — rear_anatomy renders behind the entity base; mantle seats on the shoulders behind the bust; not floating. OK.
- **Interface alignment** — forensic plates / respirators align to the lower face and jawline across entities. OK.
- **Head/face masks** — implant_front respirators/clamps register to the mouth/cheek anchors. OK.
- **Collar/mantle intersections** — mantle collars intersect the neck/shoulders cleanly. OK.
- **No floating hardware / no wrong-layer burial / no silhouette clipping that breaks the figure.** OK.

## Minor watch (non-blocking)

- `synthetic` jaw/actuator implant extends toward the frame edge (anchored at the jaw; acceptable, verify at full crop).
- A few rear antenna/cables sit close to the shoulder edge; within envelope.

No entity family is systematically misregistered, so overlays are **not** refit. Program-level and full-render validation remain future work.
