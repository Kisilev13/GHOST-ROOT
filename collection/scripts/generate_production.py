#!/usr/bin/env python3
"""Deterministic GHOST//ROOT production compositor.

Reads design-witness.csv's per-token trait assignment (the authoritative
identity/trait source — trait-architecture.md), resolves each of the 11
categories to a layer asset via asset_manifest.py, and composites them
back-to-front per art-bible.md's z-order into a 2048x2048 sRGB PNG.

This script never invents a trait assignment (it only reads design-witness.csv,
never randomizes), never fabricates a missing asset, and never silently skips
a required layer. A token with any required asset missing is reported and
skipped, not rendered with a placeholder standing in for real art — see
collection/reports/production-art-audit.md, "Finding 2": zero layer assets
exist yet, so in the current repository state every token will report missing
and none will render. That is the correct, honest behavior, not a bug.

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

from asset_manifest import LAYER_STACK, METADATA_ONLY_CATEGORIES, load_trait_catalog, resolve, skip_reason

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
    return problems


def plan_layers(trait_ids: dict[str, str]) -> tuple[list[tuple], list[str]]:
    """Returns (resolved_layers, missing_descriptions). resolved_layers is
    [(slot, path), ...] in paste order; an empty missing list means every
    required layer resolved and this token is ready to composite."""
    resolved = []
    missing = []
    for slot in LAYER_STACK:
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
    return resolved, missing


def composite(resolved_layers: list[tuple]) -> "Image.Image":
    from PIL import Image

    canvas = Image.new("RGBA", (CANVAS, CANVAS), (0, 0, 0, 0))
    for slot, path in resolved_layers:
        layer = Image.open(path).convert("RGBA")
        if layer.size != (CANVAS, CANVAS):
            raise GenerationError(
                f"{path} is {layer.size}, expected {CANVAS}x{CANVAS} (art-bible.md: "
                "every layer uses the full canvas with no trim)"
            )
        canvas.alpha_composite(layer)
    # Flatten to opaque sRGB per art-bible.md delivery spec (2048x2048, 8-bit sRGB, opaque).
    flat = Image.new("RGB", (CANVAS, CANVAS), (0, 0, 0))
    flat.paste(canvas, mask=canvas.split()[3])
    return flat


def deterministic_png_bytes(img: "Image.Image") -> bytes:
    import io

    buf = io.BytesIO()
    # No timestamps/EXIF/ICC nondeterminism; fixed compression for stable bytes.
    img.save(buf, format="PNG", optimize=False, compress_level=6)
    return buf.getvalue()


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--ids", help="comma-separated token IDs")
    ap.add_argument("--ids-file", help="file with one token ID per line")
    ap.add_argument("--dest", default=str(COLLECTION / "assets" / "test-batch"))
    ap.add_argument("--report-only", action="store_true", help="resolve + validate only, write nothing")
    ap.add_argument("--manifest-out", default=str(COLLECTION / "manifests" / "test-batch-render-manifest.json"))
    args = ap.parse_args()

    ids: list[int] = []
    if args.ids:
        ids.extend(int(x) for x in args.ids.split(",") if x.strip())
    if args.ids_file:
        ids.extend(int(x) for x in Path(args.ids_file).read_text().split() if x.strip())
    if not ids:
        ap.error("provide --ids or --ids-file")

    witness = load_design_witness()
    catalog = load_trait_catalog()
    dest = Path(args.dest)
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

        resolved, missing = plan_layers(trait_ids)
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

        img = composite(resolved)
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
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
