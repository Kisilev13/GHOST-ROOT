#!/usr/bin/env python3
"""Author the 7 missing architecture overlays procedurally, in the existing
schematic line-art style, with a deterministic per-entity accent so no two
entity variants are byte-identical.

Reuses architecture_layer(kind, entity) from build_geometric_layers.py (the same
kind geometry as the existing 9), then adds a subtle entity-keyed structural mark
inside the frame. Deterministic PIL only — no Gemini, no network, no seed.
Writes only resolver paths that are missing; refuses to overwrite an existing,
differing asset.
"""
from __future__ import annotations
import hashlib, json
from pathlib import Path
from PIL import Image, ImageDraw, ImageFilter

from asset_manifest import COLLECTION, ROOT
from build_geometric_layers import architecture_layer, ANCHORS, _png, _zero, N

RESOLVER = COLLECTION / "manifests" / "collection-required-assets.json"
BONE = (186, 178, 168, 200)
GRAPHITE = (44, 48, 52, 190)
COOL = (150, 160, 172, 150)


def entity_accent(entity: str) -> Image.Image:
    """A subtle, schematic, entity-specific mark drawn around the face frame."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    cx, cy = ANCHORS["head_center"]
    if entity == "hollow":
        # faint inner void ring — an extra recessed gap line
        d.arc((cx - 300, cy - 340, cx + 250, cy + 300), 200, 430, fill=(8, 10, 12, 150), width=6)
        d.arc((cx - 270, cy - 310, cx + 220, cy + 270), 200, 430, fill=COOL, width=2)
    elif entity == "human":
        # bone rivet dots around the frame perimeter
        for ang_x, ang_y in ((-280, -300), (240, -280), (-300, 60), (260, 80), (-150, 300), (150, 310)):
            d.ellipse((cx + ang_x - 9, cy + ang_y - 9, cx + ang_x + 9, cy + ang_y + 9), fill=BONE, outline=(20, 18, 16, 180))
    elif entity == "specter":
        # cool carbon hatch across the frame band
        for i in range(-6, 7):
            x = cx + i * 46
            d.line([(x, cy - 320), (x + 120, cy + 300)], fill=(120, 128, 138, 70), width=2)
    elif entity == "synthetic":
        # ceramic bolt squares at frame corners + a centre seam
        for bx, by in ((-290, -320), (250, -320), (-300, 260), (260, 260)):
            d.rectangle((cx + bx - 12, cy + by - 12, cx + bx + 12, cy + by + 12), outline=GRAPHITE, width=4)
            d.line([(cx + bx - 6, cy + by), (cx + bx + 6, cy + by)], fill=BONE, width=2)
        d.line([(cx, cy - 300), (cx, cy + 280)], fill=(150, 145, 138, 90), width=3)
    else:
        raise ValueError(entity)
    return im.filter(ImageFilter.GaussianBlur(0.6))


def build(kind: str, entity: str) -> Image.Image:
    base = architecture_layer(kind, entity)              # existing schematic kind geometry
    accent = entity_accent(entity)
    # keep the accent only within the base's own footprint plus the frame band so it
    # reads as part of the architecture, then merge.
    return Image.alpha_composite(base, accent)


def missing_architecture():
    d = json.loads(RESOLVER.read_text())
    out = []
    for r in d["paths"]:
        if r["slot"] != "architecture":
            continue
        p = ROOT / r["path"]
        if p.is_file():
            continue
        fn = Path(r["path"]).name
        kind = fn.split("__")[1]
        entity = fn.split("entity__")[1].split("__")[0]
        out.append((kind, entity, p, r["path"]))
    return out


def main():
    todo = missing_architecture()
    print(f"{len(todo)} architecture overlays to author (procedural, per-entity accent)")
    recs = []
    for kind, entity, path, rel in todo:
        data = _png(_zero(build(kind, entity)))
        if path.exists() and path.read_bytes() != data:
            raise SystemExit(f"Refusing to overwrite a different asset: {path}")
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(data)
        sha = hashlib.sha256(data).hexdigest()
        recs.append({"path": rel, "kind": kind, "entity": entity, "sha256": sha,
                     "dimensions": [N, N], "status": "CREATED_UNREVIEWED",
                     "technique": "procedural schematic architecture_layer + deterministic per-entity accent",
                     "source": "collection/scripts/build_wave1_architecture.py"})
        print(f"{rel}  {sha[:12]}")
    (COLLECTION / "assets/sources/architecture-wave1-provenance.json").write_text(json.dumps(recs, indent=2) + "\n")
    print(f"\n{len(recs)} written")


if __name__ == "__main__":
    main()
