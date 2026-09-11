#!/usr/bin/env python3
"""Deterministic GHOST//ROOT production compositor.

Reads design-witness.csv's per-token trait assignment (the authoritative
identity/trait source — trait-architecture.md), resolves each of the 11
categories to a layer asset via asset_manifest.py, and composites them
back-to-front per art-bible.md's z-order into a 2048x2048 sRGB PNG.

This script never invents a trait assignment (it only reads design-witness.csv,
never randomizes), never fabricates a missing asset, and never silently skips
a required layer. A token with any required asset missing is reported and
skipped, not rendered with a placeholder standing in for real art. Genesis and
legendary IDs require complete authored scenes. Prototype renders are limited
to the 20 IDs in manifests/test-batch-selection.json.

Determinism: given the same design-witness.csv row and the same asset files,
byte-identical output every run. No timestamps, no EXIF, no random seed. The
approved exported layer bytes are the reproducibility boundary (art-bible.md).

Usage:
    generate_production.py --ids 1,2,3,303 --dest collection/assets/test-batch
    generate_production.py --ids-file ids.txt --report-only   # audit missing assets only
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import json
import sys
from pathlib import Path

from asset_manifest import LAYER_STACK, METADATA_ONLY_CATEGORIES, LayerSlot, load_trait_catalog, resolve, skip_reason

ROOT = Path(__file__).resolve().parents[2]
COLLECTION = ROOT / "collection"
CANVAS = 2048
DESIGN_WITNESS = COLLECTION / "design-witness.csv"


class GenerationError(Exception):
    pass


def load_design_witness() -> dict[int, dict[str, str]]:
    rows: dict[int, dict[str, str]] = {}
    with DESIGN_WITNESS.open(newline="") as f:
        for row in csv.DictReader(f):
            rows[int(row["token_id"])] = row
    return rows


def trait_ids_for(row: dict[str, str]) -> dict[str, str]:
    """category -> trait id, e.g. {"entity": "entity.synthetic", ...}."""
    categories = ("entity", "access", "architecture", "material", "eyes", "interface",
                  "implant", "mantle", "background", "signal", "corruption")
    return {c: row[c] for c in categories}


def validate_row(token_id: int, trait_ids: dict[str, str], catalog: dict[str, dict]) -> list[str]:
    """Defensive re-check of requires/excludes against traits.yaml. design-witness.csv was
    already validated at design time (validate_design.py); this re-validates at generation
    time so a hand-edited or future row can never silently render an illegal combination."""
    problems = []
    chosen = set(trait_ids.values())
    for tid in chosen:
        trait = catalog.get(tid)
        if trait is None:
            problems.append(f"unknown trait id {tid!r}")
            continue
        for group in trait["requires"]:
            if not chosen.intersection(group["any_of"]):
                problems.append(f"{tid} requires one of {group['any_of']}")
        if chosen.intersection(trait["excludes"]):
            problems.append(f"{tid} excludes {sorted(chosen.intersection(trait['excludes']))}")
    genesis = json.loads((COLLECTION / "genesis.yaml").read_text())["characters"]
    legendary = json.loads((COLLECTION / "legendary-reservations.yaml").read_text())["reservations"]
    for record in genesis + legendary:
        if record["token_id"] == token_id:
            for category, expected in record["traits"].items():
                if trait_ids.get(category) != expected:
                    problems.append(f"reservation mismatch: {category} must be {expected}")
    if trait_ids.get("architecture") == "architecture.singular" and token_id not in {r["token_id"] for r in genesis}:
        problems.append("SINGULAR is reserved for Genesis")
    if trait_ids.get("architecture") == "architecture.severed_halo" and token_id not in {r["token_id"] for r in legendary}:
        problems.append("SEVERED HALO is reserved for legendary IDs")
    return problems


# Geometric HUD leftovers from the first visual pass. Ordinary tokens in this
# prototype composite bust + mantle + eyes only; architecture is baked into
# entity_base. Fitted implant/interface/rear/corruption/seam come next.
UNREGISTERED_OVERLAY_SLOTS = {
    "rear_anatomy",
    "architecture",
    "corruption",
    "witness_seam",
    "implant_front",
    "interface",
}


def plan_layers(trait_ids: dict[str, str], omit_unregistered: bool = True) -> tuple[list[tuple], list[str]]:
    """Returns (resolved_layers, missing_descriptions). resolved_layers is
    [(slot, path), ...] in paste order; an empty missing list means every
    required layer resolved and this token is ready to composite."""
    resolved = []
    missing = []
    for slot in LAYER_STACK:
        if omit_unregistered and slot.slot in UNREGISTERED_OVERLAY_SLOTS:
            continue
        if skip_reason(slot, trait_ids) is not None:
            continue
        path = resolve(slot, trait_ids)
        if path is not None:
            resolved.append((slot, path))
            continue
        reason = skip_reason(slot, trait_ids)
        if reason is not None:
            continue  # legitimately nothing to paste (e.g. interface.none)
        if slot.required:
            missing.append(f"z{slot.z:03d} {slot.slot}: no asset for {slot.categories} = "
                            f"{[trait_ids.get(c) for c in slot.categories]}")
        # non-required, non-empty, unresolved (e.g. optional grain pass) -> silently skipped
    if omit_unregistered:
        # Front-facing break collar must sit on the neck, so mantle paints after the bust
        # in this prototype (art-bible z30 is rear collar; we do not yet have a split).
        order = {"background": 0, "entity_base": 1, "mantle": 2, "eyes": 3}
        resolved.sort(key=lambda item: order.get(item[0].slot, item[0].z))
    return resolved, missing


def special_scene(token_id: int) -> tuple[LayerSlot, Path] | None:
    """Authored complete scenes retain the fixed trait record; never generic Genesis."""
    for filename, key, family in (("genesis.yaml", "characters", "genesis"),
                                  ("legendary-reservations.yaml", "reservations", "legendary")):
        records = json.loads((COLLECTION / filename).read_text())[key]
        if token_id in {r["token_id"] for r in records}:
            return (LayerSlot(100, "authored_scene", "", (), False, True),
                    COLLECTION / "assets" / "scenes" / family / f"{token_id:04d}__v001.png")
    return None


def plan_token_layers(token_id: int, trait_ids: dict[str, str]) -> tuple[list[tuple], list[str]]:
    scene = special_scene(token_id)
    if scene:
        return ([scene], []) if scene[1].is_file() else ([], [f"NEEDS ART GENERATION: {scene[1].relative_to(ROOT)}"])
    return plan_layers(trait_ids, omit_unregistered=True)


def composite(resolved_layers: list[tuple]) -> "Image.Image":
    from PIL import Image

    canvas = Image.new("RGBA", (CANVAS, CANVAS), (0, 0, 0, 0))
    for slot, path in resolved_layers:
        with Image.open(path) as source:
            if source.format != "PNG":
                raise GenerationError(f"{path}: expected PNG")
            if source.info.get("icc_profile"):
                from PIL import ImageCms
                import io
                profile = ImageCms.ImageCmsProfile(io.BytesIO(source.info["icc_profile"]))
                if "srgb" not in ImageCms.getProfileDescription(profile).lower():
                    raise GenerationError(f"{path}: convert source profile to sRGB before import")
            layer = source.convert("RGBA")
        if layer.size != (CANVAS, CANVAS):
            raise GenerationError(
                f"{path} is {layer.size}, expected {CANVAS}x{CANVAS} (art-bible.md: "
                "every layer uses the full canvas with no trim)"
            )
        if layer.getchannel("A").getbbox() is None:
            raise GenerationError(f"{path}: blank transparent asset")
        if slot.slot in ("background", "authored_scene") and layer.getchannel("A").getextrema() != (255, 255):
            raise GenerationError(f"{path}: background/scene must be fully opaque")
        canvas.alpha_composite(layer)
    # Flatten to opaque sRGB per art-bible.md delivery spec (2048x2048, 8-bit sRGB, opaque).
    flat = Image.new("RGB", (CANVAS, CANVAS), (0, 0, 0))
    flat.paste(canvas, mask=canvas.split()[3])
    return flat


def deterministic_png_bytes(img: "Image.Image") -> bytes:
    import io

    buf = io.BytesIO()
    from PIL.PngImagePlugin import PngInfo
    info = PngInfo()
    info.add(b"sRGB", b"\x00")
    # No timestamps/EXIF/ICC nondeterminism; fixed compression for stable bytes.
    img.save(buf, format="PNG", optimize=False, compress_level=6, pnginfo=info)
    return buf.getvalue()


def _parse_ids_file(path: Path) -> list[int]:
    text = path.read_text()
    stripped = text.lstrip()
    if stripped.startswith("{"):
        data = json.loads(text)
        if "selected" in data:
            return [int(r["token_id"]) for r in data["selected"]]
        if "token_ids" in data:
            return [int(x) for x in data["token_ids"]]
        raise GenerationError(f"{path}: JSON ids file needs selected[] or token_ids[]")
    return [int(x) for x in text.replace(",", " ").split() if x.strip()]


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--ids", help="comma-separated token IDs")
    ap.add_argument("--ids-file", help="token IDs (one per line) or test-batch-selection.json")
    ap.add_argument("--dest", default=str(COLLECTION / "assets" / "test-batch"))
    ap.add_argument("--report-only", action="store_true", help="resolve + validate only, write nothing")
    ap.add_argument("--manifest-out", default=str((COLLECTION / "manifests" / "test-batch-render-manifest.json").resolve()))
    args = ap.parse_args()

    ids: list[int] = []
    if args.ids:
        ids.extend(int(x) for x in args.ids.split(",") if x.strip())
    if args.ids_file:
        ids.extend(_parse_ids_file(Path(args.ids_file)))
    if not ids:
        ap.error("provide --ids or --ids-file")
    selected = {r["token_id"] for r in json.loads((COLLECTION / "manifests/test-batch-selection.json").read_text())["selected"]}
    if len(ids) != len(set(ids)) or not set(ids).issubset(selected):
        ap.error("this prototype stage permits each selected test ID once only")

    witness = load_design_witness()
    catalog = load_trait_catalog()
    dest = Path(args.dest)
    if not dest.is_absolute():
        dest = ROOT / dest
    dest = dest.resolve()
    if not args.report_only:
        dest.mkdir(parents=True, exist_ok=True)

    results = []
    for token_id in ids:
        row = witness.get(token_id)
        if row is None:
            results.append({"token_id": token_id, "status": "UNKNOWN_ID"})
            print(f"[{token_id:04d}] UNKNOWN_ID — not in design-witness.csv", file=sys.stderr)
            continue

        trait_ids = trait_ids_for(row)
        problems = validate_row(token_id, trait_ids, catalog)
        if problems:
            results.append({"token_id": token_id, "status": "INVALID", "problems": problems})
            print(f"[{token_id:04d}] INVALID: {'; '.join(problems)}", file=sys.stderr)
            continue

        resolved, missing = plan_token_layers(token_id, trait_ids)
        if missing:
            results.append({
                "token_id": token_id,
                "status": "MISSING_ASSETS",
                "missing": missing,
                "resolved_count": len(resolved),
            })
            print(f"[{token_id:04d}] MISSING_ASSETS ({len(missing)} unresolved of "
                  f"{len(resolved) + len(missing)} required)", file=sys.stderr)
            continue

        if args.report_only:
            results.append({"token_id": token_id, "status": "READY_NOT_RENDERED"})
            continue

        try:
            img = composite(resolved)
        except (GenerationError, OSError, ValueError) as exc:
            results.append({"token_id": token_id, "status": "INVALID_ASSET", "error": str(exc)})
            continue
        png_bytes = deterministic_png_bytes(img)
        out_path = dest / f"{token_id:04d}.png"
        out_path.write_bytes(png_bytes)
        digest = hashlib.sha256(png_bytes).hexdigest()
        results.append({
            "token_id": token_id,
            "status": "RENDERED",
            "path": str(out_path.relative_to(ROOT)),
            "sha256": digest,
            "bytes": len(png_bytes),
            "layers_used": [str(p.relative_to(ROOT)) for _, p in resolved],
            "source_sha256": {str(p.relative_to(ROOT)): hashlib.sha256(p.read_bytes()).hexdigest() for _, p in resolved},
            "trait_ids": trait_ids,
            "witness_sha256": hashlib.sha256(DESIGN_WITNESS.read_bytes()).hexdigest(),
        })
        print(f"[{token_id:04d}] RENDERED -> {out_path.relative_to(ROOT)} ({digest[:12]}...)")

    Path(args.manifest_out).parent.mkdir(parents=True, exist_ok=True)
    Path(args.manifest_out).write_text(json.dumps({
        "generator": "collection/scripts/generate_production.py",
        "canvas": f"{CANVAS}x{CANVAS}",
        "metadata_only_categories": list(METADATA_ONLY_CATEGORIES),
        "results": results,
    }, indent=2))

    rendered = sum(1 for r in results if r["status"] == "RENDERED")
    print(f"\n{rendered}/{len(results)} rendered. Manifest: {args.manifest_out}", file=sys.stderr)
    return 0 if all(r["status"] in ("RENDERED", "READY_NOT_RENDERED") for r in results) else 1


if __name__ == "__main__":
    raise SystemExit(main())
