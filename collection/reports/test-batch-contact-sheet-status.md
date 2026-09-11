# GHOST//ROOT — test-batch contact sheet (status, not an image)

_Phase 11 asked for `test-batch-contact-sheet.png`. That file is not included: a contact sheet
exists to support visual review, and 0 of the 20 test-batch images have been rendered (see
`production-art-audit.md` Finding 2 — zero layer assets exist to composite from). Generating a
PNG of placeholder rectangles labeled with IDs would not serve a visual review and could be
mistaken for reviewed art, so it was not created. This document is the honest substitute: the
full 20-identity roster the contact sheet would have shown, in table form._

| ID | Name | Band | Entity | Architecture | Material | Eyes | Corruption | Why selected |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- |
| 0001 | GHOST//0001 | 1/1 | SYNTHETIC | SINGULAR | PORCELAIN | BIOMETRIC | INTACT | Genesis — THE WITNESS (bespoke, non-procedural) |
| 0002 | GHOST//0002 | 1/1 | HOLLOW | SINGULAR | CARBON | VOID | ABSENCE | Genesis — THE NULL (missing-face signature; the exact case that failed v2 AI-portrait review) |
| 0003 | GHOST//0003 | 1/1 | SPECTER | SINGULAR | RECONSTRUCTED | FRACTURED | PACKET GHOSTING | Genesis — THE LAST ADMINISTRATOR (the ADMIN-tier collision case from the v2 review) |
| 0004 | GHOST//0004 | COMMON | HUMAN | SEALED | PORCELAIN | BIOMETRIC | INTACT | COMMON (architecture.sealed), ordinary trait combination — the collectibility floor |
| 0010 | GHOST//0010 | COMMON | HOLLOW | SEALED | PORCELAIN | FRACTURED | INTACT | SKELETAL INTERFACE — rarest interface value (33/3333), stresses a rare socket/occlusion case |
| 0049 | GHOST//0049 | COMMON | SPECTER | SEALED | PHASE GLASS | FRACTURED | INTACT | COMMON band with 3 rare-tier traits co-occurring — legal-but-unusual combination at the most common architecture |
| 0097 | GHOST//0097 | COMMON | HOLLOW | SEALED | PORCELAIN | VOID | ABSENCE | HOLLOW entity + ABSENCE corruption — a real-missing-volume case at procedural (non-Genesis) tier, directly comparable to Genesis ghost-0002 |
| 0112 | GHOST//0112 | UNCOMMON | SYNTHETIC | INSET | RECONSTRUCTED | OPTICAL ARRAY | INTACT | UNCOMMON (architecture.inset), ordinary trait combination |
| 0166 | GHOST//0166 | RARE | HUMAN | DOUBLE PLANE | BIO-SYNTH | THERMAL | SCAN SHEAR | RARE (architecture.double_plane), ordinary trait combination |
| 0192 | GHOST//0192 | EPIC | HOLLOW | SUSPENDED CORE | CARBON | FRACTURED | PACKET GHOSTING | EPIC (architecture.suspended_core), ordinary trait combination |
| 0303 | GHOST//0303 | LEGENDARY | HUMAN | SEVERED HALO | BIO-SYNTH | THERMAL | INTACT | LEGENDARY special ID — archetype 'observer' interpretation 1; trait vector is feasibility-only, art_status concept_required |
| 0366 | GHOST//0366 | COMMON | SPECTER | SEALED | PHASE GLASS | VOID | ABSENCE | SPECTER entity + PHASE GLASS material — both rarest-in-category (133/3333, 33/3333) at COMMON architecture; extreme rarity intersection at the most common band |
| 0449 | GHOST//0449 | UNCOMMON | SPECTER | INSET | PHASE GLASS | VOID | SCAN SHEAR | UNCOMMON band with 3 rare-tier traits co-occurring |
| 0493 | GHOST//0493 | RARE | SPECTER | DOUBLE PLANE | RECONSTRUCTED | VOID | ABSENCE | RARE band with 2 rare-tier traits co-occurring |
| 0803 | GHOST//0803 | LEGENDARY | SYNTHETIC | SEVERED HALO | PORCELAIN | THERMAL | PACKET GHOSTING | LEGENDARY special ID — archetype 'fracture' interpretation 3 |
| 0836 | GHOST//0836 | EPIC | SPECTER | SUSPENDED CORE | PHASE GLASS | VOID | INTACT | EPIC band with 3 rare-tier traits co-occurring |
| 1503 | GHOST//1503 | LEGENDARY | HOLLOW | SEVERED HALO | CARBON | VOID | INTACT | LEGENDARY special ID — archetype 'signal' interpretation 1 |
| 1842 | GHOST//1842 | RARE | HUMAN | DOUBLE PLANE | BIO-SYNTH | BIOMETRIC | CHECKSUM BURN | Already referenced live on ghostroot.site (front-page.php's featured-identity fallback) — continuity with the deployed site |
| 2803 | GHOST//2803 | LEGENDARY | HOLLOW | SEVERED HALO | CARBON | FRACTURED | INTACT | LEGENDARY special ID — archetype 'witness' interpretation 2 |
| 3333 | GHOST//3333 | COMMON | HUMAN | SEALED | PORCELAIN | VOID | INTACT | Last token ID — boundary/edge case |

Once layer assets exist and `generate_production.py` renders this batch, regenerate this
as a real image contact sheet (labels on the sheet only, per art-bible.md's QA section —
never burned into the token images themselves).
