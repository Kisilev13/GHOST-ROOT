# GHOST//ROOT — production visual specification

Authoritative pixel rules for compositor layers in this prototype. Complements
`art-bible.md` (design) and `canonical-trait-schema.json` (locked categories).
This file does not replace the design witness.

Status: `PROTOTYPE_VISUAL_LOCK` for the 20-token test batch only.
Not `MINT_READY`. Not an approval of any layer that merely exists on disk.

## Canvas and delivery

| Property | Value |
| --- | --- |
| Layer interchange | 2048 × 2048 PNG, 8-bit, straight RGBA |
| Final token | 2048 × 2048 PNG, 8-bit sRGB, fully opaque |
| Color | sRGB chunk; reject other ICC profiles on import |
| Alpha | RGB is `(0,0,0)` where alpha is 0; no baked matte; no empty frame |
| Background / authored scene | Every pixel alpha 255 |
| Overlay slots | Transparent outside the subject; no scene, floor, or cast environment |
| Text | None in any PFP or layer |

`collection/scripts/import_production_layer.py` is the only import path from
generated rasters. Native generator size may differ; import must resample to
2048 and fail closed on blank, non-PNG, or non-square sources that cannot be
centered without destroying the camera.

## Camera, framing, anchors

Fixed for every ordinary layer and every authored scene:

- 85 mm-equivalent portrait, camera level with the eyes
- Near-frontal, 8° subject turn toward **viewer-left**
- Head and upper shoulders only; no hands, no full body
- Crown y≈0.13, eyes y≈0.36, chin y≈0.64, shoulders below y≈0.68
- Head center x≈0.49

Pixel-center anchors (origin top-left):

| Name | x, y |
| --- | --- |
| head_center | 1004, 922 |
| crown | 1004, 266 |
| eye_left | 823, 737 |
| eye_right | 1168, 737 |
| chin | 1004, 1311 |
| collar_left | 651, 1454 |
| collar_gap_viewer_right | 1400, 1495 |
| implant_left_temple | 645, 675 |
| implant_right_temple | 1393, 690 |
| rear_neck | 1024, 1430 |

Avatar-safe circle: center `(1024, 1024)`, radius `860`. Face, collar opening,
and signature geometry stay inside it.

Safe bounds for overlay layers: `[164, 164, 1884, 2048]`.

Break collar: monumental ceramic, **open on viewer-right**. Never close that
gap. Never mirror the collar.

## Lighting and palette

- Key: upper viewer-left, ~45° elevation
- Fill: ~1/4 of key; rim weaker than key
- Exposure and view transform are shared. Carbon, hollow voids, and dark
  faces must remain readable; do not crush them into the archive field
- Palette: archive black, bone ceramic, graphite, restrained vermilion
- No gold, rainbow, neon bloom, or rarity-color coding
- Grain is a fixed material property baked into approved sources, never a
  per-token random seed

## Silhouette and class anatomy

Each entity is a sculpted instrument, not a costume on a shared mesh.

| Entity | Structure |
| --- | --- |
| HUMAN | Living cranial volume, intact sockets, tissue-capable surface |
| SYNTHETIC | Precision shell, manufactured seams, optical hardware sockets |
| HOLLOW | Real missing interior volume with a structural rim; not a painted hole |
| SPECTER | Double or incomplete reconstructed contours that only agree along the witness seam |

Do not use a generic head with stickers. Do not silently reuse another class's
layer. Entity-qualified filenames are mandatory.

## Slot rules (back to front)

| Z | Slot | Alpha | Notes |
| ---: | --- | --- | --- |
| 00 | background | opaque | Archive location. Quiet value hierarchy, no figure, no text |
| 10 | environmental_depth | optional | May be baked into the background for this prototype |
| 20 | rear_anatomy | straight | Entity × implant rear occlusion; omitted when `implant.none` |
| 30 | mantle | straight | Shoulder/collar silhouette; keep the viewer-right collar gap |
| 40 | entity_base | straight | Entity × material × architecture. Topology lives here, not as a decal |
| 50 | eyes | straight | Socket-fitted. VOID is a cavity. No doubled pupils |
| 60 | implant_front | straight | Named sockets. SIGNAL CROWN is sparse hardware, not jewelry. Omitted when `implant.none` |
| 60 | interface | straight | Face-mounted. Omitted when `interface.none` |
| 70 | architecture | straight | Front topology pass and contact shadow; must match the entity_base architecture |
| 80 | corruption | straight | Bounded stencil. Omitted when `corruption.intact` |
| 90 | witness_seam | straight | One shared geometric source, clipped to material/negative space |
| 100 | grain | optional | Baked into approved sources for this prototype |
| 100 | authored_scene | opaque | Genesis and legendary complete images; same camera; no generic overlay |

Blend: source-over, opacity 1.0.

`access` and `signal` are metadata-only. They do not get paint layers.

## Material, eye, implant, interface, corruption

- PORCELAIN: thin glazed shell. CERAMIC: thicker, matte, load-bearing. Not palette swaps.
- BIO-SYNTH: living tissue cues. CARBON: directional composite grain. RECONSTRUCTED: repaired joints. PHASE GLASS: refractive thickness (SPECTER).
- VOID: occluded cavity. BIOMETRIC / THERMAL: intact sockets (HUMAN/SYNTHETIC). OPTICAL ARRAY: manufactured array (SYNTHETIC/HOLLOW). FRACTURED: optical depth split, not extra pupils.
- RESPIRATOR: HUMAN/SYNTHETIC sockets only. NEURAL VEIL: follows volume. NULL MASK: blocks rear-access hardware. SKELETAL INTERFACE: HOLLOW/SPECTER opening, never with SIGNAL CROWN.
- SCAN SHEAR: one bounded displaced band. PACKET GHOSTING: one offset reconstruction contour. CHECKSUM BURN: restrained vermilion scorch. ABSENCE: real missing volume with depth-correct edges.

## Backgrounds in this batch

Procedural 2048 plates, currently `CREATED_UNREVIEWED`:

- `evidence_void` — empty archive field, quiet key from viewer-left
- `cold_storage` — recessed door jambs, lid seams, no figure
- `rack_shadow` — unlit rack depth planes, no LEDs or glyphs
- `relay_well` — elliptical vault ribs, cropped well

No characters, no NFT stand-ins, no labels.

## Genesis and legendary

IDs 1–3 and reserved legendary IDs use **complete authored scenes**. The
compositor does not stack generic SINGULAR or SEVERED HALO layers onto an
ordinary body. Metadata still stores the locked witness vector.

Genesis direction (`genesis.md` / `genesis.yaml`):

- 0001 THE WITNESS — thin porcelain two-plane cavity, biometric rings, half-width forensic plate, one memory spindle, heavy archive coat
- 0002 THE NULL — carbon cranial rim around a missing face volume, disconnected witness-seam fragments, dense cable shroud, blind root port. No skull-teeth horror
- 0003 THE LAST ADMINISTRATOR — double reconstructed face agreeing at the seam, inward-facing severed receivers, neural veil, imperial silhouette without jewelry

Legendary IDs in this batch (303 observer, 803 fracture, 1503 signal, 2803
witness) must be visibly rarer through silhouette, architecture, lighting, or
scene treatment, while remaining the same collection. Do not gold-plate them.

## Generation method

1. Code (PIL) for registered geometry: backgrounds, witness seam, architecture
   front topology, bounded corruption stencils.
2. Image tools for sculptural parts, always from four entity masters. Never
   generate a second independent portrait of the same class.
3. Import through `import_production_layer.py`. A present file is not an
   art approval.

Exclusions (do not prompt them in, and reject them on review): hoodie, code
rain, neon skyline, weapons, logos, insignia, watermarks, celebrity likeness,
extra pupils, malformed face, visible hands, gold/rainbow rarity, excessive
bloom, tilted camera, burned-in labels, named-artist imitation.

## QA before `APPROVED_FOR_PRODUCTION_RENDER`

Review every test-batch token at full size, on a contact sheet, and as a 64 px
circle. Reject broken anatomy, pasted accessories, unreadable common faces,
clipped collar gap, inconsistent camera, and Genesis/legendary pieces that
look like ordinary composites. Byte-identical images fail. A background swap
alone is not identity differentiation.
