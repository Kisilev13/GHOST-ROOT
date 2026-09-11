# Pass 5 — Implant hardware inventory (20-batch)

2026-09-11. Every Implant value used by the representative 20, the layer file the
deterministic resolver expects, its current state, and the fitted anchor it must
register to. **All listed front assets currently exist only as schematic/wireframe
placeholders** (`build_fitted_overlays.py` / `build_attachment_layers.py` output) and
are held out of the compositor via `UNREGISTERED_OVERLAY_SLOTS`. Production requires
each to be re-authored as integrated hardware (brief in `pass5-authored-art-briefs.md`).

Anchors are from `assets/attachment-anchors.json` (verified correct in Pass 3,
`reports/anchor-verification.png`). Front implants mount at z60; rear anatomy at z20
(behind the entity base at z40, so it is occluded by the head/shoulders).

| Trait | Tokens | Front asset (z60 `60_implant_front/`) | Rear asset (z20 `20_rear_anatomy/`) | Mount anchor | State | Visible now? |
| --- | --- | --- | --- | --- | --- | --- |
| antenna | 4, 97 | `implant__antenna__entity__{human,hollow}__v001.png` | `entity__{human,hollow}__implant__antenna__v001.png` | crown (14–172 y) + temple | SCHEMATIC | no |
| memory_spindle | 1, 112 | `implant__memory_spindle__entity__synthetic__v001.png` | `entity__synthetic__implant__memory_spindle__v001.png` | temple→jaw right side | SCHEMATIC (1 = Genesis authored scene) | no (112) |
| neural_cable | 366, 449, 1842, 3333 | `implant__neural_cable__entity__{human,specter}__v001.png` | `entity__{human,specter}__implant__neural_cable__v001.png` | temple socket eye_l−side → neck | SCHEMATIC | no |
| none | 49, 166 | — (skip) | — (skip) | — | n/a | n/a |
| root_port | 2, 192, 836 | `implant__root_port__entity__{hollow,specter}__v001.png` | `entity__{hollow,specter}__implant__root_port__v001.png` | crown/occiput port | SCHEMATIC (2 = Genesis authored) | no (192, 836) |
| signal_crown | 3, 303, 803, 1503, 2803 | — (all Genesis/Legendary authored scenes) | — | crown ring of spikes | authored in scenes (303/803 done; 1503/2803 pending) | via scene |
| spinal_bus | 10, 493 | `implant__spinal_bus__entity__{hollow,specter}__v001.png` | `entity__{hollow,specter}__implant__spinal_bus__v001.png` | nape → between shoulders | SCHEMATIC | no |

## Consequence for approval

- `neural_cable` invisibility is the sole reason `(0049, 0366)` still reads as a
  near-duplicate: those two specter tokens differ **only** in implant
  (49 = none, 366 = neural_cable), so with the implant unrendered they collapse to
  the same image. Authoring `implant__neural_cable__entity__specter` + its rear
  routing, then registering the slot, is what resolves that pair.
- 5 of the 7 non-`none` implant families (antenna, memory_spindle, neural_cable,
  root_port, spinal_bus) drive ordinary tokens and are all currently invisible —
  the primary "ordinary tokens feel under-dressed" finding.

## Anchor reference (batch base combos)

| base combo | eye_l | eye_r | crown y | neck y | shoulder y |
| --- | --- | --- | --- | --- | --- |
| hollow carbon suspended_core | 876,736 | 1181,736 | 172 | 1120 | 1992 |
| hollow porcelain sealed | 817,705 | 1073,705 | 172 | 1114 | 1986 |
| human biosynth double_plane | 808,576 | 1116,576 | 96 | 1208 | 1528 |
| human porcelain sealed | 735,585 | 1010,580 | 14 | 1182 | 1556 |
| specter phase_glass inset | 855,575 | 1100,575 | 82 | 1208 | 1570 |
| specter phase_glass sealed | 730,570 | 1050,575 | 14 | 1172 | 1584 |
| specter phase_glass suspended_core | 845,570 | 1070,570 | 0 | 1170 | 1452 |
| specter reconstructed double_plane | 730,585 | 990,580 | 104 | 1096 | 1536 |
| synthetic reconstructed inset | 846,714 | 1160,715 | 170 | 1230 | 1906 |
