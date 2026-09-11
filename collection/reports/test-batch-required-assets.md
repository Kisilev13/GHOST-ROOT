# Test-batch required assets

The historical **105** is a generic inventory row count, not a valid production minimum.

```text
LEGACY COLLECTION INVENTORY: 105 (SUPERSEDED_FOR_PRODUCTION)
TOTAL COLLECTION REQUIRED EXPORT PATHS: 226
UNIQUE ASSETS REQUIRED FOR 20-BATCH: 72
PRESENT: 72
MISSING: 0
```

Exact mandatory export paths for current resolver. Includes authored complete scenes. Optional depth/grain may be baked into background/source layers. Editable masters, depth masks and collision verification remain additional art-production deliverables.

Counts use the actual selected witness rows and the same path resolver as the compositor. No edition rendering occurred.

| Slot | Unique exports |
| --- | ---: |
| background | 4 |
| rear_anatomy | 9 |
| mantle | 8 |
| entity_base | 9 |
| eyes | 3 |
| implant_front | 9 |
| interface | 7 |
| architecture | 9 |
| corruption | 6 |
| witness_seam | 1 |
| authored_scene | 7 |

| Exact asset path | Trait IDs | Used by token IDs | Status |
| --- | --- | --- | --- |
| `collection/assets/layers/00_background/background__cold_storage__v001.png` | background.cold_storage | 49, 192, 366, 836 | CREATED_UNREVIEWED |
| `collection/assets/layers/00_background/background__evidence_void__v001.png` | background.evidence_void | 10, 97, 166, 449, 493, 1842 | CREATED_UNREVIEWED |
| `collection/assets/layers/00_background/background__rack_shadow__v001.png` | background.rack_shadow | 4, 112 | CREATED_UNREVIEWED |
| `collection/assets/layers/00_background/background__relay_well__v001.png` | background.relay_well | 3333 | CREATED_UNREVIEWED |
| `collection/assets/layers/20_rear_anatomy/entity__hollow__implant__antenna__v001.png` | entity.hollow, implant.antenna | 97 | CREATED_UNREVIEWED |
| `collection/assets/layers/20_rear_anatomy/entity__hollow__implant__root_port__v001.png` | entity.hollow, implant.root_port | 192 | CREATED_UNREVIEWED |
| `collection/assets/layers/20_rear_anatomy/entity__hollow__implant__spinal_bus__v001.png` | entity.hollow, implant.spinal_bus | 10 | CREATED_UNREVIEWED |
| `collection/assets/layers/20_rear_anatomy/entity__human__implant__antenna__v001.png` | entity.human, implant.antenna | 4 | CREATED_UNREVIEWED |
| `collection/assets/layers/20_rear_anatomy/entity__human__implant__neural_cable__v001.png` | entity.human, implant.neural_cable | 1842, 3333 | CREATED_UNREVIEWED |
| `collection/assets/layers/20_rear_anatomy/entity__specter__implant__neural_cable__v001.png` | entity.specter, implant.neural_cable | 366, 449 | CREATED_UNREVIEWED |
| `collection/assets/layers/20_rear_anatomy/entity__specter__implant__root_port__v001.png` | entity.specter, implant.root_port | 836 | CREATED_UNREVIEWED |
| `collection/assets/layers/20_rear_anatomy/entity__specter__implant__spinal_bus__v001.png` | entity.specter, implant.spinal_bus | 493 | CREATED_UNREVIEWED |
| `collection/assets/layers/20_rear_anatomy/entity__synthetic__implant__memory_spindle__v001.png` | entity.synthetic, implant.memory_spindle | 112 | CREATED_UNREVIEWED |
| `collection/assets/layers/30_mantle/mantle__archive_coat__entity__hollow__v001.png` | mantle.archive_coat | 192 | CREATED_UNREVIEWED |
| `collection/assets/layers/30_mantle/mantle__archive_coat__entity__human__v001.png` | mantle.archive_coat | 4, 166 | CREATED_UNREVIEWED |
| `collection/assets/layers/30_mantle/mantle__archive_coat__entity__synthetic__v001.png` | mantle.archive_coat | 112 | CREATED_UNREVIEWED |
| `collection/assets/layers/30_mantle/mantle__cable_shroud__entity__human__v001.png` | mantle.cable_shroud | 1842, 3333 | CREATED_UNREVIEWED |
| `collection/assets/layers/30_mantle/mantle__cable_shroud__entity__specter__v001.png` | mantle.cable_shroud | 366, 493 | CREATED_UNREVIEWED |
| `collection/assets/layers/30_mantle/mantle__ceramic_mantle__entity__hollow__v001.png` | mantle.ceramic_mantle | 10 | CREATED_UNREVIEWED |
| `collection/assets/layers/30_mantle/mantle__field_collar__entity__hollow__v001.png` | mantle.field_collar | 97 | CREATED_UNREVIEWED |
| `collection/assets/layers/30_mantle/mantle__unsupported_frame__entity__specter__v001.png` | mantle.unsupported_frame | 49, 449, 836 | CREATED_UNREVIEWED |
| `collection/assets/layers/40_entity_base/entity__hollow__material__carbon__architecture__suspended_core__v001.png` | architecture.suspended_core, entity.hollow, material.carbon | 192 | CREATED_UNREVIEWED |
| `collection/assets/layers/40_entity_base/entity__hollow__material__porcelain__architecture__sealed__v001.png` | architecture.sealed, entity.hollow, material.porcelain | 10, 97 | CREATED_UNREVIEWED |
| `collection/assets/layers/40_entity_base/entity__human__material__biosynth__architecture__double_plane__v001.png` | architecture.double_plane, entity.human, material.biosynth | 166, 1842 | CREATED_UNREVIEWED |
| `collection/assets/layers/40_entity_base/entity__human__material__porcelain__architecture__sealed__v001.png` | architecture.sealed, entity.human, material.porcelain | 4, 3333 | CREATED_UNREVIEWED |
| `collection/assets/layers/40_entity_base/entity__specter__material__phase_glass__architecture__inset__v001.png` | architecture.inset, entity.specter, material.phase_glass | 449 | CREATED_UNREVIEWED |
| `collection/assets/layers/40_entity_base/entity__specter__material__phase_glass__architecture__sealed__v001.png` | architecture.sealed, entity.specter, material.phase_glass | 49, 366 | CREATED_UNREVIEWED |
| `collection/assets/layers/40_entity_base/entity__specter__material__phase_glass__architecture__suspended_core__v001.png` | architecture.suspended_core, entity.specter, material.phase_glass | 836 | CREATED_UNREVIEWED |
| `collection/assets/layers/40_entity_base/entity__specter__material__reconstructed__architecture__double_plane__v001.png` | architecture.double_plane, entity.specter, material.reconstructed | 493 | CREATED_UNREVIEWED |
| `collection/assets/layers/40_entity_base/entity__synthetic__material__reconstructed__architecture__inset__v001.png` | architecture.inset, entity.synthetic, material.reconstructed | 112 | CREATED_UNREVIEWED |
| `collection/assets/layers/50_eyes/eyes__biometric__entity__human__v001.png` | eyes.biometric | 4, 1842 | CREATED_UNREVIEWED |
| `collection/assets/layers/50_eyes/eyes__optical_array__entity__synthetic__v001.png` | eyes.optical_array | 112 | CREATED_UNREVIEWED |
| `collection/assets/layers/50_eyes/eyes__thermal__entity__human__v001.png` | eyes.thermal | 166 | CREATED_UNREVIEWED |
| `collection/assets/layers/60_implant_front/implant__antenna__entity__hollow__v001.png` | implant.antenna | 97 | CREATED_UNREVIEWED |
| `collection/assets/layers/60_implant_front/implant__antenna__entity__human__v001.png` | implant.antenna | 4 | CREATED_UNREVIEWED |
| `collection/assets/layers/60_implant_front/implant__memory_spindle__entity__synthetic__v001.png` | implant.memory_spindle | 112 | CREATED_UNREVIEWED |
| `collection/assets/layers/60_implant_front/implant__neural_cable__entity__human__v001.png` | implant.neural_cable | 1842, 3333 | CREATED_UNREVIEWED |
| `collection/assets/layers/60_implant_front/implant__neural_cable__entity__specter__v001.png` | implant.neural_cable | 366, 449 | CREATED_UNREVIEWED |
| `collection/assets/layers/60_implant_front/implant__root_port__entity__hollow__v001.png` | implant.root_port | 192 | CREATED_UNREVIEWED |
| `collection/assets/layers/60_implant_front/implant__root_port__entity__specter__v001.png` | implant.root_port | 836 | CREATED_UNREVIEWED |
| `collection/assets/layers/60_implant_front/implant__spinal_bus__entity__hollow__v001.png` | implant.spinal_bus | 10 | CREATED_UNREVIEWED |
| `collection/assets/layers/60_implant_front/implant__spinal_bus__entity__specter__v001.png` | implant.spinal_bus | 493 | CREATED_UNREVIEWED |
| `collection/assets/layers/60_interface/interface__forensic_plate__entity__hollow__v001.png` | interface.forensic_plate | 97, 192 | CREATED_UNREVIEWED |
| `collection/assets/layers/60_interface/interface__forensic_plate__entity__specter__v001.png` | interface.forensic_plate | 449, 836 | CREATED_UNREVIEWED |
| `collection/assets/layers/60_interface/interface__forensic_plate__entity__synthetic__v001.png` | interface.forensic_plate | 112 | CREATED_UNREVIEWED |
| `collection/assets/layers/60_interface/interface__neural_veil__entity__specter__v001.png` | interface.neural_veil | 493 | CREATED_UNREVIEWED |
| `collection/assets/layers/60_interface/interface__null_mask__entity__human__v001.png` | interface.null_mask | 4, 3333 | CREATED_UNREVIEWED |
| `collection/assets/layers/60_interface/interface__respirator__entity__human__v001.png` | interface.respirator | 166 | CREATED_UNREVIEWED |
| `collection/assets/layers/60_interface/interface__skeletal_interface__entity__hollow__v001.png` | interface.skeletal_interface | 10 | CREATED_UNREVIEWED |
| `collection/assets/layers/70_architecture/architecture__double_plane__entity__human__v001.png` | architecture.double_plane | 166, 1842 | CREATED_UNREVIEWED |
| `collection/assets/layers/70_architecture/architecture__double_plane__entity__specter__v001.png` | architecture.double_plane | 493 | CREATED_UNREVIEWED |
| `collection/assets/layers/70_architecture/architecture__inset__entity__specter__v001.png` | architecture.inset | 449 | CREATED_UNREVIEWED |
| `collection/assets/layers/70_architecture/architecture__inset__entity__synthetic__v001.png` | architecture.inset | 112 | CREATED_UNREVIEWED |
| `collection/assets/layers/70_architecture/architecture__sealed__entity__hollow__v001.png` | architecture.sealed | 10, 97 | CREATED_UNREVIEWED |
| `collection/assets/layers/70_architecture/architecture__sealed__entity__human__v001.png` | architecture.sealed | 4, 3333 | CREATED_UNREVIEWED |
| `collection/assets/layers/70_architecture/architecture__sealed__entity__specter__v001.png` | architecture.sealed | 49, 366 | CREATED_UNREVIEWED |
| `collection/assets/layers/70_architecture/architecture__suspended_core__entity__hollow__v001.png` | architecture.suspended_core | 192 | CREATED_UNREVIEWED |
| `collection/assets/layers/70_architecture/architecture__suspended_core__entity__specter__v001.png` | architecture.suspended_core | 836 | CREATED_UNREVIEWED |
| `collection/assets/layers/80_corruption/corruption__absence__entity__hollow__v001.png` | corruption.absence | 97 | CREATED_UNREVIEWED |
| `collection/assets/layers/80_corruption/corruption__absence__entity__specter__v001.png` | corruption.absence | 366, 493 | CREATED_UNREVIEWED |
| `collection/assets/layers/80_corruption/corruption__checksum_burn__entity__human__v001.png` | corruption.checksum_burn | 1842 | CREATED_UNREVIEWED |
| `collection/assets/layers/80_corruption/corruption__packet_ghosting__entity__hollow__v001.png` | corruption.packet_ghosting | 192 | CREATED_UNREVIEWED |
| `collection/assets/layers/80_corruption/corruption__scan_shear__entity__human__v001.png` | corruption.scan_shear | 166 | CREATED_UNREVIEWED |
| `collection/assets/layers/80_corruption/corruption__scan_shear__entity__specter__v001.png` | corruption.scan_shear | 449 | CREATED_UNREVIEWED |
| `collection/assets/layers/90_witness_seam/witness_seam__v001.png` |  | 4, 10, 49, 97, 112, 166, 192, 366, 449, 493, 836, 1842, 3333 | CREATED_UNREVIEWED |
| `collection/assets/scenes/genesis/0001__v001.png` | access.root, architecture.singular, background.evidence_void, corruption.intact, entity.synthetic, eyes.biometric, implant.memory_spindle, interface.forensic_plate, mantle.archive_coat, material.porcelain, signal.locked | 1 | CREATED_UNREVIEWED |
| `collection/assets/scenes/genesis/0002__v001.png` | access.root, architecture.singular, background.cold_storage, corruption.absence, entity.hollow, eyes.void, implant.root_port, interface.none, mantle.cable_shroud, material.carbon, signal.silent | 2 | CREATED_UNREVIEWED |
| `collection/assets/scenes/genesis/0003__v001.png` | access.root, architecture.singular, background.relay_well, corruption.packet_ghosting, entity.specter, eyes.fractured, implant.signal_crown, interface.neural_veil, mantle.split_shell, material.reconstructed, signal.carrier_echo | 3 | CREATED_UNREVIEWED |
| `collection/assets/scenes/legendary/0303__v001.png` | access.root, architecture.severed_halo, background.rack_shadow, corruption.intact, entity.human, eyes.thermal, implant.signal_crown, interface.respirator, mantle.cable_shroud, material.biosynth, signal.locked | 303 | CREATED_UNREVIEWED |
| `collection/assets/scenes/legendary/0803__v001.png` | access.root, architecture.severed_halo, background.relay_well, corruption.packet_ghosting, entity.synthetic, eyes.thermal, implant.signal_crown, interface.neural_veil, mantle.split_shell, material.porcelain, signal.carrier_echo | 803 | CREATED_UNREVIEWED |
| `collection/assets/scenes/legendary/1503__v001.png` | access.root, architecture.severed_halo, background.rack_shadow, corruption.intact, entity.hollow, eyes.void, implant.signal_crown, interface.forensic_plate, mantle.ceramic_mantle, material.carbon, signal.intermittent | 1503 | CREATED_UNREVIEWED |
| `collection/assets/scenes/legendary/2803__v001.png` | access.root, architecture.severed_halo, background.evidence_void, corruption.intact, entity.hollow, eyes.fractured, implant.signal_crown, interface.none, mantle.archive_coat, material.carbon, signal.locked | 2803 | CREATED_UNREVIEWED |

All per-file anchors, alpha, bounds, overlap, blend, opacity and allowed neighboring paths are recorded in `manifests/test-batch-required-assets.json`. A present file is not an art approval.
