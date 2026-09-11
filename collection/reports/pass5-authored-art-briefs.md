# Pass 5 — authored-art production briefs

2026-09-11. **This session has no image-generation capability** (no Garden / no
`OPENAI_API_KEY`, no host-native image tool). Per the task's Phase 26, the missing
art is NOT faked. These are the exact production briefs a tool-enabled session or a
human artist must execute to reach `APPROVED_FOR_PRODUCTION_RENDER`.

Status of the pass: **`BLOCKED_ON_AUTHORED_ART_TOOL`**.

## Global specification (applies to every asset below)

- **Canvas:** 2048×2048, straight-alpha RGBA PNG, sRGB. Transparent everywhere except
  the authored component. No burned-in labels, no background.
- **Camera / registration:** identical to the entity bases — frontal, eye line per
  `assets/attachment-anchors.json` (see anchor table in `pass5-implant-inventory.md`).
  The component must be painted at final composite scale; `fit_authored_layer.py` only
  translates/scales a reviewed rectangle, it does not fix perspective.
- **Lighting:** single key from upper-left, soft fill lower-right, matching the bases.
  Highlights on upper-left faces of forms; contact shadow cast down-right onto the
  body. Ambient occlusion in every crevice where hardware meets skin/ceramic.
- **Material language (GHOST//ROOT):** worn archival hardware — matte gunmetal, aged
  nickel, bead-blasted steel, black rubberized conduit, bone/ivory ceramic with fine
  craquelure. Trace grime in seams. **Forbidden:** neon, emissive RGB, Tron lines,
  glued-on glow, glossy plastic, anime cybernetics, HUD/vector line-art, flat symbols.
  Reference the approved Pass 4 assets `assets/sources/pass4/cable-shroud.png` (black
  conduit + nickel clamps) and `ceramic-mantle.png` — that is the exact material bar.
- **Integration:** every part needs physical thickness, a believable mount (bolt,
  clamp, socket collar, strap), a contact shadow, and correct occlusion (see per-slot
  occlusion notes). Nothing floats.
- **Delivery:** `import_production_layer.py` → review → `fit_authored_layer.py --box`
  into the exact filename the resolver expects (names in `pass5-implant-inventory.md`
  and below). Then remove the slot from `UNREGISTERED_OVERLAY_SLOTS` and re-render.

---

## A. Implant — front (z60, `assets/layers/60_implant_front/`)

Each mounts to the entity's temple/crown/nape per anchor; front piece sits over the
base, rear piece (section B) sits behind it for depth.

- **antenna** (`__entity__human`, `__entity__hollow`) — a short bead-blasted steel
  stalk rising 180–260px from the temple just outside eye_l, with a machined collar
  where it enters the skull, a strain-relief boot, and a fine whip tip. Occlusion: base
  of stalk disappears under a small skin/ceramic lip. Contact shadow onto the temple.
- **memory_spindle** (`__entity__synthetic`) — a horizontal ceramic-and-steel spindle
  cartridge seated against the right cheek/jaw (from eye_r toward jaw), like a data reel
  half-recessed into the reconstructed face. Visible spool, retaining screws, a short
  ribbon lead tucked under the jaw. Occlusion: right edge passes behind the ear/jaw line.
- **neural_cable** (`__entity__human`, `__entity__specter`) — **priority asset.** A
  machined temple socket (Ø ~90px) seated at the side of the head level with eye_l, a
  fitted connector plugged into it, and a black rubberized cable leaving the connector,
  draping down past the jaw and neck with one strain-relief clamp, terminating out of
  frame at the shoulder. Must read as physically plugged in, not a drawn line. Occlusion:
  cable passes **behind** the jaw for a segment then in front of the neck (needs the
  rear companion in B). Contact shadow of the cable on the neck.
- **root_port** (`__entity__hollow`, `__entity__specter`) — a heavy circular bulkhead
  port (Ø ~140px) set into the crown/upper-occiput, threaded collar, blanking cap on a
  short chain, four countersunk bolts. Occlusion: lower rim tucks under the skull curve.
- **spinal_bus** (`__entity__hollow`, `__entity__specter`) — a segmented vertebral bus
  bar rising from the nape into the lower skull, 3–4 machined vertebrae with cabling
  channels. Mostly a rear element (see B); the front sliver is only what wraps past the
  neck sides. Occlusion: disappears behind the neck centrally.

## B. Rear anatomy (z20, `assets/layers/20_rear_anatomy/`)

Sits **behind** the entity base (z40) — only the parts wider/taller than the head/
shoulders are visible, which is the point: they add silhouette and depth without
covering the face. Files: `entity__{entity}__implant__{value}__v001.png`.

- **antenna rear** (human, hollow) — the whip continuing up above the crown; faint.
- **neural_cable rear** (human, specter) — the cable's hidden run: the segment that
  passes behind the jaw/neck and the shoulder loop, so the front cable reads as truly
  wrapping the neck. This is what makes 366 differ from 49 in silhouette.
- **root_port rear** (hollow, specter) — a short cabling tail exiting the occiput port
  and dropping behind the shoulder.
- **spinal_bus rear** (hollow, specter) — the main event: the vertebral column standing
  proud above the nape and between the shoulder blades, visible on either side of the
  neck. Strongest rear-silhouette contributor.
- **memory_spindle rear** (synthetic) — ribbon lead routed behind the neck.

## C. Interface (z60, `assets/layers/60_interface/`)

Files: `interface__{value}__entity__{entity}__v001.png`. Must conform to face/jaw/
temple/skull geometry per anchor — not a flat plate pasted over the face.

- **forensic_plate** (hollow, specter, synthetic) — a hinged evidence plate clamped
  over the lower face (nose-base to chin), matte steel with an etched case-number bezel,
  two temple straps to the sides of the head, breathing slots. Occlusion: straps pass
  behind the ears/skull. Reference the mask in `assets/sources/pass4/0303.png`.
- **neural_veil** (specter) — a fine chainmail/mesh veil draped from the brow over the
  eyes and cheeks, following the phase-glass contour, sagging slightly under gravity,
  anchored at temple hooks. Semi-transparent where mesh, opaque at the metal hem.
  Reference the mesh in `assets/sources/pass4/0803.png`.
- **null_mask** (human) — a blank featureless ivory-ceramic full-face shell with no eye
  or mouth openings, seated a few mm off the face with a shadow gap, a seam down the
  centre and two jaw clamps. Occlusion: edges wrap behind the jaw.
- **respirator** (human) — a fitted lower-face respirator: two filter canisters at the
  cheeks, a machined nose bridge, ribbed hose stub under the chin, head strap. This one
  is already close in `0303` — match that quality. Occlusion: strap behind the head.
- **skeletal_interface** (hollow) — an exposed jaw-and-cheek exoskeleton of thin steel
  ribs following the skull, screwed to temple and jaw, revealing the void beneath.

## D. Corruption (z80, `assets/layers/80_corruption/`)

Files: `corruption__{value}__entity__{entity}__v001.png`. Must **alter the existing
material**, masked to the body — never a full-frame overlay or generic glitch bar.

- **absence** (hollow, specter) — a region of the face/shoulder simply *missing*: a
  clean-edged void bitten out of the ceramic revealing dark interior, with a soft
  vignette of dust at the edge. Masked to the body silhouette only.
- **checksum_burn** (human) — a scorched/etched band across one cheek where the surface
  is charred and cratered, following the face curvature, with raised burnt lips at the
  edges. Localized, not a rectangle.
- **packet_ghosting** (hollow) — a faint doubled/displaced echo of an edge feature (ear,
  jaw) offset a few px, as if the material stuttered — physically embossed, not a
  transparency ghost. Masked to the head.
- **scan_shear** (human, specter) — a horizontal slip fault across the face where the
  upper and lower halves are offset by ~15px along a clean shear line, with a thin
  crushed-material seam. Masked to the silhouette; respects the face lighting.

## E. Witness / seam (z90) — decision

`witness_seam` is **not a trait category** (not in the canonical schema's
`art_visible_categories` nor `metadata_only_categories`; it is a universal compositor
slot). Decision recorded in `PRODUCTION-VISUAL-SPEC.md`: treat it as an **optional
universal micro-texture pass** (a hairline kintsugi-style repair seam), not a per-token
trait. It is therefore not a blocker and stays disabled until/unless art-directed as a
global finish. No ambiguous art-visible category is left silently unrendered.

## F. Registration checklist (after art lands)

1. Replace each schematic file in place (same filename) with the authored version.
2. `git`-review the render of one token per family before enabling.
3. Remove `implant_front`, `interface`, `rear_anatomy`, `corruption` from
   `UNREGISTERED_OVERLAY_SLOTS` in `generate_production.py` (keep `witness_seam` out).
4. Re-render the 20; run validator + near-dup; confirm `(49,366)` no longer flags and
   no prior fix regressed.
