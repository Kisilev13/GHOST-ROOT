"""Canonical asset resolution, art-bible z00-z100.

Access and Signal are metadata-only by the explicit canonical-schema decision.
Anatomical layers require an entity-qualified asset, with no generic fallback.
Entity/material bases also encode architecture so topology is not just an overlay.
NONE/INTACT mean no asset; every non-empty trait must resolve. Environmental depth
may be baked into the background; grain may be baked into approved sources.
Genesis and legendary complete scenes are resolved by token ID in the compositor.
The historical 105-path generic inventory is SUPERSEDED_FOR_PRODUCTION.
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
    entity_qualified: bool  # require an entity-specific filename
    required: bool  # must resolve for a token to be composited at all


# Back-to-front compositing order. One entry per art-bible.md z-layer.
LAYER_STACK: tuple[LayerSlot, ...] = (
    LayerSlot(0, "background", "00_background", ("background",), False, True),
    LayerSlot(10, "environmental_depth", "10_environmental_depth", ("background",), False, False),
    LayerSlot(20, "rear_anatomy", "20_rear_anatomy", ("entity", "implant"), True, True),
    LayerSlot(30, "mantle", "30_mantle", ("mantle",), True, True),
    LayerSlot(40, "entity_base", "40_entity_base", ("entity", "material", "architecture"), False, True),
    LayerSlot(50, "eyes", "50_eyes", ("eyes",), True, True),
    LayerSlot(60, "interface", "60_interface", ("interface",), True, True),
    LayerSlot(60, "implant_front", "60_implant_front", ("implant",), True, True),
    LayerSlot(70, "architecture", "70_architecture", ("architecture",), True, True),
    LayerSlot(80, "corruption", "80_corruption", ("corruption",), True, True),
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
    else:
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
    # VOID is empty-socket anatomy on entity_base, not a second painted iris.
    empties = {"interface.none", "implant.none", "corruption.intact", "eyes.void", "eyes.fractured"}
    for c in slot.categories:
        if trait_ids.get(c) in empties:
            return f"{c}={trait_ids[c]} has no visual content by design"
    return None


def load_trait_catalog() -> dict[str, dict]:
    data = json.loads((COLLECTION / "traits.yaml").read_text())
    return {t["id"]: t for t in data["traits"]}
