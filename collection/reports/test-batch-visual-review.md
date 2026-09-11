# GHOST//ROOT — test-batch visual QA

## Pass 5 — 2026-09-11, authored-art gate (Claude)

### Verdict

**REPEAT_VISUAL_PROTOTYPE_STAGE** — pass state: **`BLOCKED_ON_AUTHORED_ART_TOOL`**

Not `APPROVED_FOR_PRODUCTION_RENDER`. The two remaining blocker classes (legendaries
1503/2803 and fitted implant/interface/rear/corruption hardware) are **authored-art**
problems, and this session has **no image-generation capability**: no Garden imagegen
(`OPENAI_API_KEY` unset, `ENABLE_GARDEN_IMAGEGEN` unset, no gateway file) and no
host-native image tool. Per the task's Phase 26, the missing art is NOT faked. Instead
this pass produced exact, executable production briefs and left the pipeline (which
already works) untouched. No pixels changed; the Pass 4 renders stand.

### Why blocked, not approved

The remaining work is illustration, not engineering. Faking it — enabling the existing
schematic/wireframe overlay assets, or generating unvetted images and dropping them in
as finished NFTs — is exactly what the brief forbids. The honest outcome is to specify
the assets precisely and stop.

### Deliverables produced this pass

- `reports/pass5-implant-inventory.md` — every Implant value in the 20-batch, its
  resolver filename, current state (all schematic), and mount anchor.
- `reports/pass5-authored-art-briefs.md` — full briefs for all **31** fitted assets
  (9 implant-front, 9 rear-anatomy, 7 interface, 6 corruption) with canvas, crop,
  composition, material, perspective, lighting, palette, anchors, occlusion, negative
  prompt, transparency, and references to the approved Pass 4 sources.
- `reports/legendary-art-direction.md` — complete scene briefs for 1503 ("The
  Intermittent Witness", void-as-subject) and 2803 ("The Opened Reliquary", revealed
  internal machinery), each with a distinct concept/silhouette/architecture/lighting and
  their exact canonical trait vectors.
- `PRODUCTION-VISUAL-SPEC.md` — **witness/seam decision recorded**: it is not a trait
  category (absent from the schema's art-visible and metadata-only lists and from the
  witness CSV), so it is defined as an optional universal micro-texture pass, disabled
  by default. No ambiguous art-visible category is left silently unrendered.

### Per-Phase status

- **1503** — UNAUTHORED; brief written. Blocks approval.
- **2803** — UNAUTHORED; brief written. Blocks approval.
- **Implant system** — 5 families (antenna, memory_spindle, neural_cable, root_port,
  spinal_bus) invisible on ordinary tokens; 18 files (9 front + 9 rear) to re-author.
- **Neural cable** — briefed as priority (temple socket + connector + draped cable +
  rear neck routing). This is the specific asset that resolves `(49,366)`.
- **Interface system** — 7 files (forensic_plate ×3, neural_veil, null_mask, respirator,
  skeletal_interface) to re-author; all currently schematic/invisible.
- **Rear anatomy** — 9 files briefed (spinal_bus is the main silhouette contributor).
- **Corruption** — 6 files briefed (absence/checksum_burn/packet_ghosting/scan_shear as
  masked material alterations, not overlays).
- **Seam/witness** — resolved as optional-universal, disabled by default (not a blocker).
- **Compositor slots registered** — none this pass (correct: do not register schematic
  fallbacks; register only when authored art lands).
- **Masks added** — none this pass (occlusion requirements specified in the briefs).
- **49/366** — still flags (dist 1); root cause and fix documented (needs neural_cable).
  Not resolvable without the art.

### Mechanical state (unchanged from Pass 4, re-confirmed)

20/20 render · 2048×2048 PNG · validator exit 0 · selftest 9/9 · canonical schema PASS ·
0 exact duplicates · near-dup flags `(49,366)` d1, `(49,836)` d4 (49/836 accepted).
No regression to the Pass 4 fixes (mantles, 0010, 0303, 0803, 4/3333, 166/1842, 10/97).

---

## Pass 4 — 2026-09-11, mantle/collar/legendary re-authoring review (Claude)

### Verdict

**REPEAT_VISUAL_PROTOTYPE_STAGE**

Not `APPROVED_FOR_PRODUCTION_RENDER`. Real, substantial progress this pass — five
of the seven prior blockers are genuinely fixed on the re-rendered pixels — but two
blockers remain that would embarrass the collection at 3,333 scale, and both need
authored art that does not yet exist. No fake approval.

### What Pass 4 genuinely fixed (verified on re-rendered 20)

- **Duplicate mantles resolved.** All 8 mantle layer files now hash uniquely.
  `cable_shroud__human` re-authored as a black conduit shoulder rig with metal
  clamps; `ceramic_mantle__hollow` re-authored as a clean fitted break collar.
- **0010 collar fixed.** The ragged gray floating collar is gone; 0010 now wears the
  clean ceramic break collar, correctly seated on the shoulders (detail sheet).
- **Legendary 0303 authored.** Cracked archive severed-halo arc + signal-crown spikes
  + respirator + cabling. Reads as a deliberate high-rarity scene before metadata.
- **Legendary 0803 authored.** Shattering porcelain body + mesh mask + halo cradle.
  A whole-body destruction motif distinct from any bust.
- **Near-duplicates: the three worst pairs gone.** `(4,3333)` and `(166,1842)` were
  distance 0; both 3333 and 1842 now carry the authored cable-shroud shoulder rig and
  are no longer flagged. `(10,97)` (dist 2) resolved by the 0010 collar re-author.
  Detector now flags only `(49,366)` dist 1 and `(49,836)` dist 4.

### What still blocks approval

1. **Legendary 1503 and 2803 are unauthored.** Both are HOLLOW severed_halo IDs that
   route through near-empty scene files: 1503 is a void hood + broken collar; **2803
   is a bare void mannequin with no collar and no hardware — the plainest image in the
   entire batch, plainer than ordinary hollow tokens 0097/0192.** Two of the four
   legendaries in the roster do not justify their rarity. See
   `legendary-art-direction.md` for the required direction.
2. **Fitted hardware still omitted.** `implant_front`, `interface`, `rear_anatomy`,
   `corruption`, and `witness_seam` remain in `UNREGISTERED_OVERLAY_SLOTS`. The
   consequence is visible, not theoretical: the `interface` trait (respirator,
   null_mask, forensic_plate, skeletal, neural_veil) and `implant` trait are invisible
   on every ordinary token, so tokens that differ *only* in those traits collapse to
   the same image. The remaining `(49,366)` near-duplicate is exactly this — two
   specter tokens whose sole difference (`implant.neural_cable`) is unrendered.

### Human-level QA (fresh, on Pass 4 pixels)

- **A. Cohesion** — Strong. One material world (porcelain/phase-glass/ceramic on dark
  archive grounds), consistent camera and light. PASS.
- **B. Entity integration** — Good. Subjects sit in the archive environment with
  matched key light and contact shadow. PASS.
- **C. Hardware realism** — Mixed. The authored mantles (cable-shroud rigs on 1842/
  3333, break collars) and legendary halos (0303/0803) read as real installed
  hardware. But `interface`/`implant`/`corruption` hardware is absent on ordinary
  tokens. FAIL (blocker 2).
- **D. Silhouette diversity** — Improved (cable rigs, halos, suspended-core shards give
  real outline variety) but undercut by 5 near-identical specter phase-glass faces
  (49/366/449/493/836 share one face) because their differentiators are unrendered.
  PARTIAL.
- **E. Trait readability** — FAIL for `interface`/`implant` (invisible); PASS for
  entity/material/architecture/mantle/eyes/background.
- **F. Ordinary token quality** — Genesis/legendary aside, the strongest ordinary
  tokens (0001-adjacent porcelain, 0166 thermal, 1842/3333 cable rigs) look finished;
  the weakest (bare void hollows, under-dressed specters) look under-specified because
  their hardware traits don't render. PARTIAL.
- **G. Genesis** — 0001 (glowing biometric iris), 0002 (cable-nest void), 0003
  (split-shell veil) are intentionally authored. PASS.
- **H. Legendary** — 0303/0803 PASS; 1503/2803 FAIL (blocker 1).
- **I. Near-duplicates** — `(49,366)` a collector would call the same piece: FAIL until
  hardware renders. `(49,836)` acceptable (suspended-core shards differ). Others fixed.
- **J. Technical defects** — No exact duplicates, no clipping/halo/broken-transparency
  defects observed on the re-render; 0010 collar seam clean; determinism holds
  (selftest 9/9, validator exit 0). PASS.

### Delta from Pass 3

Pass 3 kept fitted overlays disabled and had duplicate mantles, a broken 0010 collar,
class-master legendaries, and two distance-0 near-dup pairs. Pass 4 cleared the
mantles, the collar, two legendaries, and the distance-0 pairs. The residual is now
concentrated in exactly two authored-art gaps rather than spread across the batch.

---

## Pass 3 — 2026-09-10, schema-drift fix + fitted-overlay evaluation (Claude)

### Verdict

**REPEAT_VISUAL_PROTOTYPE_STAGE** (unchanged from Pass 2)

No regression, no fake approval. This pass fixed one real defect, tested the
one candidate improvement that existed on disk, found it not production-grade,
and left the render pipeline at the same (already-reviewed) safe baseline
Pass 2 approved-for-continuation. 20/20 still render clean; mechanical gates
still pass; the substantive blockers below are unchanged from Pass 2 and
still require real illustration this session cannot produce.

### What this pass did

1. **Fixed schema drift.** `build_canonical_schema.py --check` reported
   STALE (`scripts/asset_manifest.py` had changed since the lock was last
   built). Regenerated `manifests/canonical-trait-schema.json`; `--check` now
   PASSes. This was the one item on Pass 2's "next prototype" list that was a
   pure code/data fix with no art dependency.
2. **Evaluated the candidate fitted-overlay art.** Since Pass 2, someone
   built `measure_anchors.py` + `attachment-anchors.json` (verified correct
   in `reports/anchor-verification.png` — crosshairs land on true eye
   sockets across all entity/material/architecture combos) and
   `build_fitted_overlays.py`, which regenerated `interface`, `implant_front`,
   `rear_anatomy`, `corruption`, `witness_seam`, and `70_architecture` layers
   at those corrected anchors. Pass 2 left these `UNREGISTERED_OVERLAY_SLOTS`
   (omitted from the composite) because the *old* anchors floated hardware
   ~160px off the face.
   Before wiring them in, I test-rendered 4 representative tokens
   (0004, 0166, 0836, 0010) with `omit_unregistered=False` to a scratch
   directory and inspected the output. **The anchors are now correct, but
   the overlay art itself is still schematic/wireframe** — thin line-art HUD
   rectangles, a literal camera-icon glyph, concentric target circles, tick
   marks, and jagged wire "cracks" superimposed on the photoreal bust. This
   is the same HUD-sticker failure Pass 1 already killed once, just
   re-registered at the right coordinates instead of the wrong ones. It is
   not integrated attachment art (no shared material, lighting, or
   photoreal rendering — see `reports/geometric-layer-review.png`, which
   shows the raw overlays are thin vector-style outlines, not rendered
   hardware).
   **Decision: left `UNREGISTERED_OVERLAY_SLOTS` and `omit_unregistered=True`
   unchanged.** Wiring these in would be a visible regression from the
   Pass-2-reviewed baseline, not an improvement. This is a genuine
   NEEDS ART GENERATION / NEEDS ILLUSTRATION item, not a registration bug —
   the anchor math is solved; the art is not.
3. **Re-rendered all 20 tokens** from a clean run (the on-disk render
   manifest was a stale `--report-only` dry run, not the record of the
   images actually on disk) to get a verified-current manifest. Byte content
   is unchanged from Pass 2 — same compositor, same trait data, same layer
   files, deterministic output — confirmed via `selftest_generator.py` 9/9
   PASS and `validate_test_batch.py` exit 0.
4. Re-ran `build_batch_asset_plan.py` + `build_asset_manifest.py` (226
   collection paths under the current witness-driven inventory; 72 unique
   present for the 20-batch / 0 missing, 0 formally `VALIDATED` by a human
   reviewer, 154 missing across the full collection — expected, this task
   renders 20 not 3,333).
5. Added perceptual near-duplicate detection to `validate_test_batch.py`
   (dhash, Hamming distance over all 190 pairs among the 20 renders — this
   was Phase 10's explicit ask and was not implemented before this pass).
   It flags, never hard-fails (cohesive design can legitimately score close).
   5 pairs flagged at distance <=4/81: `(4, 3333)` and `(166, 1842)` at
   distance 0, `(49, 366)`, `(10, 97)`, `(49, 836)`. The `(166, 1842)` pair
   independently confirms Pass 2 finding #2 (DOUBLE PLANE reads as
   near-identical extra-head silhouettes) with an actual measurement instead
   of eyeballing it — evidence the detector is catching something real, not
   noise.

### Still failing (unchanged from Pass 2, verified still present this pass)

1. **0010's hollow collar** still needs re-authoring — visibly the outlier
   in the contact sheet (mismatched gray tone, ragged break edge unlike the
   other collars).
2. **Legendary scenes are still class-master drops.** 0303 and 0803 in the
   fresh contact sheet are still a generic ruff-collar and a
   bowl/ring-with-floating-head composition respectively — no stronger
   silhouette/material/lighting differentiation that reads as LEGENDARY
   beyond the metadata label.
3. **Fitted implant/interface attachments are confirmed not composite-ready**
   (see above) — anchors fixed, art still schematic.
4. **Duplicate mantle files, unresolved:**
   `mantle__archive_coat__entity__human` ≡ `mantle__cable_shroud__entity__human`
   and `mantle__archive_coat__entity__hollow` ≡ `mantle__ceramic_mantle__entity__hollow`
   (byte-identical, confirmed by sha256 this pass). Distinct art per trait
   was not attempted this pass — see rationale below.
5. Camera/crop still mixed; DOUBLE PLANE echo-plane cleanup not reattempted.

### Why no further art was attempted this pass

This session has no image-generation/illustration tool available — only
Pillow-based procedural drawing. The finding above (item 2) shows that this
pipeline's procedural-geometry approach reliably produces schematic/vector
line-art, not photoreal integrated hardware, for anything beyond simple
socket-ring geometry (which worked for THERMAL eyes in Pass 2 because a
sensor ring genuinely is a simple circular primitive). Legendary scene
composition, a repainted break collar, and fitted garment textures are not
in that category — the art bible correctly treats them as requiring real
illustration. Forcing a code-only fix here would either (a) reproduce the
same HUD-sticker failure just documented, or (b) require inventing painted
detail not grounded in any real source image, which is exactly the
fabrication this task prohibits. These remain honestly flagged as
**NEEDS ART GENERATION**, not silently worked around.

### Next prototype (unchanged priority order)

1. Real illustration/image-edit pass: hollow break collar (0010), legendary
   scene redirection (0303, 0803 at minimum), distinct mantle art for
   `cable_shroud` and `ceramic_mantle`.
2. Re-illustrate `interface`/`implant_front`/`rear_anatomy`/`corruption`/
   `witness_seam` as integrated photoreal attachments at the now-correct
   anchors (the anchor math from this pass can be reused as-is).
3. DOUBLE PLANE echo-plane cleanup at thumbnail scale.
4. Re-render, re-validate, re-judge.

---

## Pass 2 — 2026-09-11, collar-key + thermal + 2803 pass (Muse)

### Verdict

**REPEAT_VISUAL_PROTOTYPE_STAGE**

Real, verified improvement; still not a coherent fitted-attachment
collection. Ordinary tokens read as busts on archive fields. Do not scale
to 3,333. Not `APPROVED_FOR_PRODUCTION_RENDER`. Not `MINT_READY`.

### Mechanical

- 20/20 rendered, 2048×2048 PNG, no exact duplicates
- Validator exits 0; generator selftest 9/9 PASS
- Contact sheet: `collection/reports/test-batch-contact-sheet.png`
- Compositor still omits unregistered overlays (implant / interface / rear /
  architecture / corruption / seam). VOID/FRACTURED eyes stay empty sockets
  on the bust.

### What this pass fixed (verified on re-render)

- **Black-box collars gone.** `clean_delta_layers.py` keys baked-background
  pixels (max(RGB) < 30), median-filters alpha, drops disconnected bits
  < 2500 px, keeps straight alpha. Removed 199k baked-background pixels
  across 4 mantle files. 0097 and 0192 collars now read clean; 0010's
  chest black box and both shoulder scribbles are gone.
- **THERMAL is a fitted sensor.** `rebuild_thermal_eyes.py` replaces the
  sticker-red delta ovals with code-drawn socket rings + recessed bed +
  small deep-red emitter at the true socket positions measured from the
  0166 render (L=(808,576), R=(1116,576)). 0166 no longer has glowing red
  cartoon eyes.
- **1503 / 2803 no longer duplicates.** 2803 THE WITNESS recomposes on the
  collarless naked hollow framing over evidence_void; 1503 keeps the
  collared figure on rack_shadow (`compose_authored_scenes.py` updated).

### What still fails

1. **0010's hollow collar needs re-authoring.** The extract's break zone is
   a gray dither smear connected to the collar body — no blind key can
   separate it from real paint. Needs a clean 0004-style break-collar
   paint, not another pixel-diff.
2. **DOUBLE PLANE still reads as extra heads** at thumbnail (0166, 0493,
   1842; 0836 partially). The base plates are fine at full size; the echo
   planes need an image-edit pass, not code.
3. **Fitted implants/interfaces are not in the composite.** Deliberately
   still omitted: the geometric overlays in `build_attachment_layers.py`
   are drawn at anchors (EYE_L=(823,737), EYE_R=(1168,737)) that measure
   ~160 px too low against true sockets, and the box-type interfaces
   (null_mask, respirator, forensic_plate) would print as stickers over
   photoreal busts. Needs registered image-edit attachments from the same
   naked-bust framing, then anchor recalibration.
4. **Legendary scenes are still class-master drops** (0303 ruff, 0803
   object-in-a-ring); only the 1503/2803 duplication is fixed. Genesis
   0001 keeps micro-glyphs on the plate and glowing irises (baked into the
   authored figure).
5. **Camera/crop still mixed** (still-life heads vs editorial busts).
6. **Duplicate layer content:** `mantle__archive_coat__entity__hollow` and
   `mantle__ceramic_mantle__entity__hollow` are byte-identical files under
   two trait names (same sha after cleaning); likewise
   `mantle__cable_shroud__entity__human` duplicates
   `mantle__archive_coat__entity__human`. Two traits sharing one file is a
   readability hazard at 3,333 scale.
7. **Pre-existing, untouched:** `build_canonical_schema.py --check`
   reports STALE (schema source drift from before this pass; schema
   sources were not modified here).

### Next prototype

1. Image-edit pass: hollow break collar (0010), DOUBLE PLANE echoes,
   four legendary authored scenes, 0001 plate/iris cleanup.
2. Registered implant/interface attachments from naked-bust framing +
   anchor recalibration, then enable in compositor.
3. Resolve duplicate mantle files (one trait → one file).
4. Reconcile canonical schema drift, re-render, re-judge.

---

## Pass 1 — 2026-09-11, socket-rebuild pass (Grok)

Verdict: **REPEAT_VISUAL_PROTOTYPE_STAGE**

This pass killed the HUD-sticker failure. It did not yet produce a coherent
fitted-attachment collection. Ordinary tokens now read as busts on archive
fields. Legendary/Genesis authored scenes were not rebuilt in this pass.

Mechanical: 20/20 rendered, 2048×2048 PNG, no exact duplicates. Validator
exits 0. Compositor prototype omits unregistered HUD overlays; VOID/FRACTURED
eyes are empty sockets on the bust, not a second iris layer.

Improved: 0004 as quality floor (porcelain bust, forensic irises in real
sockets, break collar open viewer-right); HOLLOW identities (0010, 0097,
0192) as missing-volume heads; 0112 SYNTHETIC sockets; HUD arcs, gray
rectangles, giant floating irises gone.

Failed at the time: dirty collar extracts (0010 worst); DOUBLE PLANE extra
heads (0166, 0493, 1842); THERMAL sticker-red sensor; fitted
implants/interfaces missing; legendary scenes unchanged; 0001 glyphs/glow;
mixed camera/crop.
