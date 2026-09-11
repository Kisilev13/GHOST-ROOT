"""Shared asset-path resolution for the GHOST//ROOT production compositor.

Maps trait categories (traits.yaml) onto the art-bible's z-ordered layer stack
and resolves the on-disk file each token needs. Used by both
generate_production.py (the compositor) and build_asset_manifest.py (the
inventory report) so the two can never disagree about where an asset lives.

DESIGN DECISION (documented, not silently assumed — see
collection/reports/production-art-audit.md "Finding 2" and the AMBIGUOUS
line for requirement #14):

art-bible.md's z-layer table (00-100) does not map 1:1 onto the 11 trait
categories. Some z-layers are universal/fixed (10 environmental depth is an
optional background accent, 90 witness seam is one shared geometric source,
100 grain/color-transform is a final filter pass — none of these vary by
trait value). Two categories, Access and Signal, have visual_importance 1 and
no z-layer the art bible names for them; the compatibility table shows their
effect is largely expressed *indirectly*, by which Implant/Interface/
Architecture values they gate (e.g. "ROOT PORT requires SYSTEM or ROOT
access"), not through a dedicated paint layer. This module treats them as
metadata-only (no resolved asset) rather than inventing a layer the spec
never asked for. If an art director decides Access/Signal need their own
visual treatment, that is a design decision for them to make, not this
script to assume.

Filenames follow art-bible.md's own suggested convention verbatim: the
trait's stable `id` from traits.yaml (already "<category>.<value>", e.g.
"entity.synthetic") with "." replaced by "__", plus a version suffix —
e.g. "entity__synthetic__v001.png" is exactly the art bible's own example.
Where a trait's rendering depends on which Entity it's layered onto (sockets/
occlusion differ by class — see trait-architecture.md's compatibility table),
this resolver first tries an entity-qualified filename and falls back to a
generic one. That is a fallback to a *more generic* asset, never to a
*different class's* asset, so it does not violate art-bible.md's "never
silently fall back to another class's layer."
"""
from __future__ import annotations

import json
from dataclasses import dataclass
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
COLLECTION = ROOT / "collection"
LAYERS_DIR = COLLECTION / "assets" / "layers"
VERSION = "v001"


@dataclass(frozen=True)
class LayerSlot:
    z: int
    slot: str
    dirname: str
    categories: tuple[str, ...]  # traits.yaml category id(s) this slot draws from; () = fixed/universal
    entity_qualified: bool  # try an entity-specific filename before the generic one
    required: bool  # must resolve for a token to be composited at all


# Back-to-front compositing order. One entry per art-bible.md z-layer.
LAYER_STACK: tuple[LayerSlot, ...] = (
    LayerSlot(0, "background", "00_background", ("background",), False, True),
    LayerSlot(10, "environmental_depth", "10_environmental_depth", ("background",), False, False),
    LayerSlot(20, "rear_anatomy", "20_rear_anatomy", ("entity", "implant"), True, False),
    LayerSlot(30, "mantle", "30_mantle", ("mantle",), True, True),
    LayerSlot(40, "entity_base", "40_entity_base", ("entity", "material"), False, True),
    LayerSlot(50, "eyes", "50_eyes", ("eyes",), True, True),
    LayerSlot(60, "interface", "60_interface", ("interface",), True, False),  # NONE is common; not required
    LayerSlot(60, "implant_front", "60_implant_front", ("implant",), True, False),
    LayerSlot(70, "architecture", "70_architecture", ("architecture",), False, True),
    LayerSlot(80, "corruption", "80_corruption", ("corruption",), False, False),  # INTACT has nothing to paste
    LayerSlot(90, "witness_seam", "90_witness_seam", (), False, True),  # one shared geometric source
    LayerSlot(100, "grain", "100_grain", (), False, False),  # optional final color-transform pass
)

# Categories with no dedicated layer at all (see module docstring). Listed
# explicitly so callers can tell "intentionally not rendered" apart from
# "forgotten".
METADATA_ONLY_CATEGORIES = ("access", "signal")


def _slug(trait_id: str) -> str:
    """traits.yaml's stable id ("entity.synthetic") -> art-bible filename stem."""
    return trait_id.replace(".", "__")


def candidate_paths(slot: LayerSlot, trait_ids: dict[str, str]) -> list[Path]:
    """Every path this slot would accept, most specific first."""
    if not slot.categories:
        # Universal/fixed layer: one shared asset, no trait in the name.
        return [LAYERS_DIR / slot.dirname / f"{slot.slot}__{VERSION}.png"]

    parts = [_slug(trait_ids[c]) for c in slot.categories if trait_ids.get(c)]
    if not parts:
        return []
    base = "__".join(parts)
    out = []
    if slot.entity_qualified and "entity" in trait_ids and "entity" not in slot.categories:
        out.append(LAYERS_DIR / slot.dirname / f"{base}__{_slug(trait_ids['entity'])}__{VERSION}.png")
    out.append(LAYERS_DIR / slot.dirname / f"{base}__{VERSION}.png")
    return out


def resolve(slot: LayerSlot, trait_ids: dict[str, str]) -> Path | None:
    """First candidate path that exists on disk, or None if none do."""
    for p in candidate_paths(slot, trait_ids):
        if p.is_file():
            return p
    return None


def skip_reason(slot: LayerSlot, trait_ids: dict[str, str]) -> str | None:
    """Why a slot legitimately has nothing to paste (NONE/INTACT-style traits), or None."""
    empties = {"interface.none", "implant.none", "corruption.intact"}
    for c in slot.categories:
        if trait_ids.get(c) in empties:
            return f"{c}={trait_ids[c]} has no visual content by design"
    return None


def load_trait_catalog() -> dict[str, dict]:
    data = json.loads((COLLECTION / "traits.yaml").read_text())
    return {t["id"]: t for t in data["traits"]}
