# GHOST//ROOT — art production specification

## Master frame

Master: 4096 × 4096 RGBA, 16-bit working source; delivery: 2048 × 2048, 8-bit sRGB PNG, opaque final image. Layer interchange: 2048 × 2048 RGBA PNG, straight alpha, lossless. Small-site derivatives: 512/256 WebP and 128 PNG; derivatives never replace archival originals.

Coordinates are pixel centers with origin at upper left, x increasing right, y down. Normalized coordinates are `(x/2048, y/2048)`. Crown at y≈0.13, eyes at y≈0.36, chin at y≈0.64, shoulders below y≈0.68. Head center x≈0.49. Camera: 85 mm-equivalent portrait perspective, level with eyes, 8° subject turn toward viewer-left. Set these values in the source scene; AI-generated approximations require manual normalization.

Key light: upper viewer-left, approximately 45° elevation. Fill: one quarter key intensity; rim below key intensity. Fix exposure and view transform across assets. Validate dark skin, carbon, and negative-space faces independently; a common exposure does not justify unreadable faces.

Central avatar-safe circle: center (1024,1024), radius 860. Keep face, collar opening, and distinguishing geometry inside it. Telemetry lives in a separate 2048-square dossier export with edge-safe labels; base PFP images contain no small text.

## Layer hierarchy, back to front

| Z | Layer | Notes |
| ---: | --- | --- |
| 00 | Archive background | Flat black must still carry an intentional value hierarchy |
| 10 | Environmental depth | Low-contrast rack, well, or vault silhouettes |
| 20 | Rear anatomy and rear implant | Class-specific occlusion masks |
| 30 | Mantle and rear collar | Mandatory gap on viewer-right |
| 40 | Entity base / face material | Full class-specific anatomy, not universal stickers |
| 50 | Eyes and internal face depth | No doubled pupils or stray highlights |
| 60 | Interface and front implant | Named attachment sockets and collision masks |
| 70 | Architecture front geometry | Band-specific topology pass and contact shadows |
| 80 | Corruption / state accents | Controlled stencil; preserve feature legibility |
| 90 | Witness seam | One geometric source, clipped to material/negative space |
| 100 | Fixed grain and color transform | Same approved pipeline; no uniqueness by random noise |

Layer order alone cannot solve 3D intersections. Each entity × architecture combination needs an approved base, depth masks, and attachment sockets. Render front/back parts separately when a cable crosses the shoulder. Genesis uses complete authored scenes with the same final camera and compositing transform.

Suggested filenames: `entity__synthetic__v001.png`, `interface__respirator__human__front__v001.png`, `mask__hollow__double_plane__face__v001.png`. Trait IDs remain stable across asset revisions. An asset manifest maps each trait/context to its exact path and SHA-256; missing variants fail generation. Never silently fall back to another class's layer.

PNG rules: RGB is zero where alpha is zero; no baked matte; no accidental semitransparent frame edges; shadows use a documented pass; every layer uses the full canvas with no trim. Export color profiles consistently and remove nondeterministic timestamps. Reject unlicensed textures and embedded third-party marks.

## Material and effect rules

- PORCELAIN is thin, slightly translucent glaze over structured anatomy; CERAMIC is thicker, matte, and load-bearing. They cannot be indistinguishable palette swaps.
- BIO-SYNTH preserves living tissue cues. CARBON shows directional composite grain; RECONSTRUCTED shows meaningful repaired joints; PHASE GLASS introduces refraction and physical thickness.
- VOID eyes are shaped cavities with correct occlusion, not two painted black circles.
- RESPIRATOR follows HUMAN/SYNTHETIC face sockets. NEURAL VEIL follows volumetric contours. NULL MASK blocks incompatible rear-access interfaces according to the rule engine.
- SCAN SHEAR displaces one bounded band; PACKET GHOSTING is one displaced reconstruction contour; ABSENCE removes a real volume with depth-correct edges.
- SIGNAL CROWN is a sparse antenna topology keyed to ROOT access, never gold jewelry.

## Controlled AI assistance

Use AI to explore reference compositions and individual material treatments, then redraw/model approved bases and separate the layers. Independent prompts for 3,333 complete faces are not the deterministic collection pipeline. A prompt and seed may not reproduce an image after a hosted model changes. The approved exported source bytes are the reproducibility boundary.

Track provider/model identifier, version if exposed, full prompt, reference hashes, seed if exposed, sampler/settings if exposed, generation timestamp, selection author, edit history, license/terms snapshot, and final asset hash. Use `unknown` for unavailable generation settings. Do not invent reproducibility claims.

Base prompt: “Forensic editorial portrait of a fictional adult [ENTITY], [FACIAL STRUCTURE], [MATERIAL], [EYES], [IMPLANT], [INTERFACE]. Monumental ceramic break collar open on viewer-right. One interrupted witness seam across the eye line. 85 mm portrait perspective, near frontal with an 8-degree turn toward viewer-left, head and upper shoulders, hard upper-left studio key, subdued opposite rim. Archive-black field, bone ceramic and graphite, restrained vermilion accent. Physically coherent contact shadows, fine surface detail, composed and unreadable expression. No text in the image.”

Exclusions: hoodie, code rain, neon skyline, weapons, copied logos, government insignia, stock watermark, celebrity likeness, extra pupils, malformed face, visible hands, gold/rainbow rarity treatment, excessive bloom, tilted camera, altered crop. Do not imitate a named living artist; use these material and compositional properties.

## Asset milestones

1. Approve four entity bases and three common portraits per entity: 12 attractive ordinary GHOSTS.
2. Approve a 20-cell entity × non-Genesis architecture matrix, marking impossible cells explicitly rather than producing unused assets.
3. Draw the three Genesis scenes; art-direct the 30 legendary scenes against their locked trait records.
4. Complete material, eye, interface, implant, mantle, environment, and effect variants; prove socket and occlusion compatibility with stress combinations.
5. Render a 144-portrait pilot spanning class, band, high-conflict combinations, and state effects.
6. Lock source assets, seed, rule version, renderer/runtime, manifests, and manual override records. Generate the full edition and all four states.

## QA acceptance

Review every final token at full size, on a contact sheet, in a circular 64 px avatar, and against its other three states. Record reviewer/date/token/state/decision. A 144-up sheet yields 24 sheets per state, 96 total, with the final sheet partly filled. Genesis and legendary assets get two independent art reviewers.

Reject broken anatomy, malformed eyes, hands entering frame, pasted-on accessories, impossible shadow direction, malformed text in dossier exports, hidden logos, inconsistent scale, clipped signature motifs, material drift, unreadable common faces, and deformation introduced solely to inflate rarity.

Detect byte duplicates and decoded-pixel duplicates after color normalization. Flag perceptually similar pairs for human adjudication. Compare both whole portraits and face/collar crops with background and serial overlays excluded. A fingerprint, background change, alpha noise, or one-pixel effect is not sufficient identity differentiation. Compare distinct token IDs within the same state; separately verify that intended state variants visibly change.

No final portraits or contact-sheet approvals are included in this design release.
