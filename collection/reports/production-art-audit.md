# GHOST//ROOT — production art audit

_2026-09-10. Phase 1-2 of the production pipeline task. Read in full: `art-bible.md`,
`trait-architecture.md`, `traits.yaml`, `legendary-reservations.yaml`, `metadata/schema.json`,
`collection.yaml`, `release-manifest.json`, `design-witness.csv` (all 3,333 rows), `rarity-report.csv`,
`scripts/validate_design.py`, plus `genesis.md`/`genesis.yaml` (read earlier this session) and
`ghost-root/generator/config.py` (for the cross-check in finding 0). No design decisions were
invented; every gap below is reported, not filled._

## Finding 0 — two incompatible trait schemas coexist in this project (blocking, read first)

This is the single most important thing this audit found. It is a pre-existing conflict, not
something introduced here.

| | `collection/` (this audit's subject) | `ghost-root/generator/` + live WordPress plugin + `visual-tests*` |
| --- | --- | --- |
| Categories | 11: Entity, Access, **Architecture**, Face Material, Eyes, **Interface**, Implant, **Mantle**, Background, Signal, Origin Corruption | 9: entity, access, **face**, eyes, **mask**, implant, corruption, background, signal |
| Rarity bands | 6, driven by Architecture: COMMON/UNCOMMON/RARE/EPIC/LEGENDARY/1-1 (1800/900/420/180/30/3) | 5: STANDARD/CORRUPTED/ADMIN/ROOT/GENESIS (2700/500/100/30/3 — `Vocab::RARITY_BANDS` in the live plugin) |
| Attribute count in metadata | 13 (11 + State + Rarity Band) — `collection/metadata/schema.json` | 12 (9 + State + Rarity Band + Archetype) — `Vocab::TRAITS` / `ghost-root/generator/config.py` |
| Where it's authoritative | Design docs, `design-witness.csv` (3,333 rows), `genesis.yaml`, `legendary-reservations.yaml`, this task's own Phase 1 instructions | **The deployed WordPress plugin's data model, ghostroot.site's live identity posts, and the OpenSea integration's `EXPECTED_TRAIT_TYPES` built this session** (which happens to match `collection/`'s 13, since it was built from `collection/metadata/schema.json`) |

Neither side is "the mistake" — they read like two different design passes, and `collection/`'s
is dated 2026-09-07 with a cryptographic design-witness (`release-manifest.json`'s
`design_file_hashes`), which is the more rigorous, more recently validated one. But **the live
site's identity pages, taxonomies, and rarity math are running the other schema today.** Building
a production generator strictly from `collection/`'s schema (as this task directs) produces
artwork/metadata that will not line up with what ghostroot.site already displays for identities
1-3333 unless the WordPress side is migrated too — a decision and a body of work well outside
this task's scope (touches live post meta, taxonomies, and the front-page display templates
reconciled from live only two sessions ago).

**This audit proceeds using `collection/`'s schema**, per this task's explicit instruction to
treat `design-witness.csv` as authoritative and because it's what this session's own OpenSea
work already assumed. **Reconciling `ghost-root/generator/` and the live WordPress trait model
against it is a separate, unresolved decision, flagged here, not made by this audit.**

## Finding 1 — the art bible's actual pipeline is layer compositing, not per-portrait AI generation

`art-bible.md`: *"Independent prompts for 3,333 complete faces are not the deterministic
collection pipeline."* AI is specified for reference/exploration only; the real pipeline is
hand-authored/modeled **layer assets** (11 z-order layers, `00` background through `100` grain)
composited deterministically per token via `design-witness.csv`'s trait assignment.

This means `visual-tests/` and `visual-tests-v2/` (whole-portrait AI generation via
`build_image_prompts.py`, reviewed twice, `REPEAT_VISUAL_PROTOTYPE_STAGE` both times) were never
running the pipeline the art bible specifies. That doesn't make the prompt-contradiction fixes
made earlier this session wrong — but it explains why polishing that pipeline kept hitting a
ceiling: it was optimizing a fundamentally different production method than the one designed.
This task's Phase 5 (a Pillow-based deterministic compositor) **is** what the art bible asked for.

## Finding 2 — zero visual assets exist; the compositor has nothing to composite

`collection/assets/README.md`: *"No final source art is included."* Confirmed: no layer PNGs
anywhere in the repo. The art bible's own "Asset milestones" describes what producing them
requires: 4 entity bases + 3 portraits each (12), a 20-cell entity×architecture matrix, 3 Genesis
scenes, 30 legendary scenes, then material/eye/interface/implant/mantle/environment/effect
variants proven against stress combinations, then a 144-portrait pilot — before any of the
3,333 can render. That's real digital-art production (illustration/3D + compositing), not
something a code session can bootstrap from nothing. The generator built in this pass (Phase 5)
is real, tested code — it composites correctly — but it has no approved assets to draw from, so
it cannot yet produce anything resembling final art. See `ASSET-MANIFEST.md` for the itemized gap.

## Requirement classification

| # | Requirement | Status | Note |
| --- | --- | --- | --- |
| 1 | Trait categories, values, quotas | **READY** | 63 traits / 11 categories, exact quotas, `traits.yaml` + `rarity-report.csv` internally consistent |
| 2 | Layer ordering | **READY** (spec) / **NEEDS IMPLEMENTATION** (code) | `art-bible.md` z-order table 00-100 is precise; no compositor existed before this task |
| 3 | Incompatibility / requires-excludes rules | **READY** | `trait-architecture.md`'s rule language + `traits.yaml`'s `requires`/`excludes` per trait |
| 4 | Conditional traits | **READY** | Same rule engine (`any_of` groups); `validate_design.py` already implements + enforces it |
| 5 | Architecture rules (band mapping) | **READY** | Architecture/Access joint table, exact counts, matches `design-witness.csv` counts 1800/900/420/180/30/3 |
| 6 | Color/palette rules | **NEEDS VISUAL ASSET** | `art-bible.md` gives exposure/key-light/material rules (verbal spec) but no palette swatches or reference renders exist |
| 7 | Legendary requirements | **AMBIGUOUS** | Only Access+Architecture are fixed for the 30 slots (`legendary-reservations.yaml`); the other 9 categories are `full_trait_vector_status: pending`, `art_status: concept_required` for all 30 — explicitly not approved |
| 8 | Genesis identity requirements | **NEEDS VISUAL ASSET** | Lore/traits/"Art prompt delta" text are complete and rich (`genesis.md`, wired into the prompt builder in PR #5); zero actual artwork exists — "art_status": "concept_only" for all 3 |
| 9 | Reserved special IDs | **READY** | 3 Genesis + 30 legendary = 33, matches `collection.yaml`'s `special_ids` list exactly |
| 10 | Exact rarity quotas | **READY** | `design-witness.csv`'s 3,333 rows match `traits.yaml` target counts exactly per category (spot-checked architecture: 1800/900/420/180/30/3) |
| 11 | Dynamic state requirements | **READY** (spec) | 4 states (DORMANT/ACTIVE/COMPROMISED/ROOTED per `collection.yaml`); state is mutable narrative data, not a generation-time trait — out of scope for the art generator itself |
| 12 | Which traits affect visible artwork | **READY** | All 11 origin categories are visual per `visual_importance` scores in `traits.yaml`; Access (importance 1) and Signal (importance 1) are the least visually load-bearing but still specified (art-bible doesn't exempt any category from having a layer) |
| 13 | Which traits are metadata/lore only | **READY** | State, fingerprint, token ID, wallet, edition number, generation seed — explicit in `trait-architecture.md` |
| 14 | Is the visual spec detailed enough for final production assets | **AMBIGUOUS**, see Finding 1/2 | The *rules* (layering, sockets, occlusion, material behavior) are detailed; the *assets* implementing them don't exist, and producing them is an illustration/3D production effort, not a spec gap |

## What this unblocks vs. what it doesn't

**Ready to build now (this task, Phases 3-13):** config migration, directory structure, the
deterministic compositor (code), an asset manifest cataloging every required layer file by exact
path convention, the 20-identity test selection + metadata generation, and validators. All of
this is code/data work within this session's capability.

**Not unblocked by this task:** any real rendered artwork. The compositor will run against
placeholder/synthetic layer stand-ins (clearly marked as such, never presented as approved art)
purely to prove the pipeline executes end-to-end. Producing the actual 60+ approved layer assets
the art bible calls for is a separate, larger body of work (art direction + illustration/3D +
review), outside what a code session can originate.
