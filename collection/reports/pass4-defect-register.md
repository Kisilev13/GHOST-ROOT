# Pass 4 defect register

2026-09-11. Status reflects **inspected pixels of the re-rendered 20**, never code
completion. Sheets: `reports/test-batch-contact-sheet.png`,
`reports/test-batch-detail-sheet.png`.

Statuses: `OPEN` · `IN PROGRESS` · `FIXED` · `ACCEPTED INTENTIONAL`.

| ID / Asset | Issue | Severity | Root Cause | Proposed / Applied Fix | Status |
| --- | --- | --- | --- | --- | --- |
| 0010 collar | Ragged gray collar floated over torso, dirty extract | Major | Pixel-diff delta of a dirty source | Re-authored `ceramic_mantle__entity__hollow` as a clean fitted break collar; 0010 re-rendered | **FIXED** |
| mantle `cable_shroud__human` | Byte-identical to `archive_coat__human` | Major | Duplicated source file | Authored distinct cable-shroud (black conduit shoulder rig + clamps) | **FIXED** |
| mantle `ceramic_mantle__hollow` | Byte-identical to `archive_coat__hollow` | Major | Duplicated source file | Authored distinct ceramic break collar | **FIXED** |
| 0303 Legendary | Generic ruff on ordinary bust | Major | Class-master reuse | Authored severed-halo scene: cracked archive halo arc + signal-crown spikes + respirator + cabling | **FIXED** |
| 0803 Legendary | Bowl/ring around ordinary bust | Major | Class-master reuse | Authored broken-porcelain scene: shattered shell body + mesh mask + halo arm | **FIXED** |
| 1503 Legendary | Reads as a plain void-hollow bust with a broken ceramic collar; no scene, no halo hardware | Major | No authored severed-halo scene for this hollow ID (generic scene file only) | Author a distinct hollow legendary scene (structural halo, negative-space composition) | **OPEN** |
| 2803 Legendary | Bare void-hollow mannequin — **no collar, no hardware; plainer than ordinary hollow tokens (0097/0192)** | Critical | No authored scene; scene file is effectively an empty hollow bust | Author a distinct hollow legendary scene from scratch | **OPEN** |
| 166 vs 1842 (dist 0) | Same double-head silhouette | Major | Shared base; distinguishing hardware omitted | 1842 now carries the authored cable-shroud shoulder rig; silhouettes now clearly differ | **FIXED** (near-dup detector no longer flags) |
| 4 vs 3333 (dist 0) | Identical porcelain busts | Major | Shared base; distinguishing hardware omitted | 3333 now carries the authored cable-shroud shoulder rig | **FIXED** (no longer flagged) |
| 10 vs 97 (dist 2) | Near-identical hollow busts | Major | Shared hollow base | 0010 re-authored ceramic collar; pair no longer flagged | **FIXED** |
| 49 vs 366 (dist 1) | Near-identical specter faces | Major | **Only differentiators (implant.neural_cable, cable_shroud nuance) are omitted by the compositor** | Blocked on fitted-hardware authoring (see fitted rows). Cannot honestly accept a dist-1 pair whose real差异 is invisible | **OPEN** |
| 49 vs 836 (dist 4) | Similar specter contours | Minor | Shared reconstructed/phase-glass face; 0836 adds suspended-core glass shards | 0836's suspended-core shard planes give a genuinely different silhouette/negative space | **ACCEPTED INTENTIONAL** (cohesive family, visibly distinct architecture) |
| fitted implant overlays | Not shown on ordinary tokens; procedural versions read as wireframe HUD | Major | `implant_front` in `UNREGISTERED_OVERLAY_SLOTS`; only art-quality option is authored hardware, which exists for mantles but not implants | Author fitted implant hardware (like the cable-shroud mantles) and register the slot | **OPEN** |
| fitted interface overlays | `interface` trait (respirator/null_mask/forensic_plate/skeletal/neural_veil) invisible on all ordinary tokens | Major | `interface` in `UNREGISTERED_OVERLAY_SLOTS`; procedural plates read as stickers | Author fitted interface plates; register the slot | **OPEN** |
| rear anatomy | Omitted | Major | `rear_anatomy` unregistered | Author occluded rear mounting, add masks, register | **OPEN** |
| corruption | Omitted | Major | `corruption` unregistered | Author material-interaction corruption (edge erosion, not overlay); register | **OPEN** |
| witness / seam | Omitted | Major | `witness_seam` unregistered; procedural line lacks surface depth | Author an interrupted physical seam; register | **OPEN** |
| ordinary token integration | Interface/implant traits invisible → several mid-rarity tokens read as under-dressed vs their metadata | Major | Same omitted-overlay root cause | Resolve fitted-hardware authoring above | **OPEN** |
| eyes VOID / FRACTURED | Read as deliberate now (recessed black cavities / broken optics), NOT missing art | — | Pass 2/3 socket rebuild + authored bases | Verified on detail sheet (0112 void, 0166 thermal, 0049 fractured) | **ACCEPTED / OK** |
| Genesis 0001–0003 | Individually authored (glowing biometric iris, cable-nest void, split-shell veil) | — | Authored scenes | Verified finished on contact/detail sheets | **ACCEPTED / OK** |

## Summary

Pass 4 genuinely resolved: both duplicate mantles, the 0010 collar, Legendary 0303
and 0803, and all three previously-flagged distance-0/2 near-duplicate pairs.

Pass 4 did **not** resolve, and these block approval:
1. **Legendary 1503 and 2803** are unauthored void-hollow busts (2803 is the plainest
   image in the batch). Two of four legendaries in the roster do not justify rarity.
2. **Fitted hardware (implant / interface / rear / corruption / seam) is still omitted**
   from the compositor, so those traits are invisible on ordinary tokens and the
   remaining `(49, 366)` near-duplicate exists precisely because the only difference
   between the two tokens is an unrendered trait.

Both remaining blockers require authored illustration assets (of the same kind that
successfully fixed the mantles and legendaries 0303/0803) that do not yet exist. They
are honestly flagged `NEEDS ART AUTHORING`, not worked around.
