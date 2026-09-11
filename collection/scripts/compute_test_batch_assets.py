#!/usr/bin/env python3
"""Phase 3 — the minimum unique layer-asset set the 20-token test batch
actually needs, vs. the full 105-row ASSET-MANIFEST.md (every value in every
category, for the full 3,333-token production run). Only required slots are
counted toward "required for this batch" — optional/entity-variant slots are
listed too, but don't block rendering.
"""
from __future__ import annotations

import csv
import json
from pathlib import Path

from asset_manifest import LAYER_STACK, candidate_paths, skip_reason

ROOT = Path(__file__).resolve().parents[2]
COLLECTION = ROOT / "collection"
CATEGORIES = ("entity", "access", "architecture", "material", "eyes", "interface",
              "implant", "mantle", "background", "signal", "corruption")


def load_design_witness() -> dict[int, dict]:
    with (COLLECTION / "design-witness.csv").open(newline="") as f:
        return {int(r["token_id"]): r for r in csv.DictReader(f)}


def trait_ids_for(row: dict) -> dict[str, str]:
    return {c: row[c] for c in CATEGORIES}


def main() -> None:
    selection = json.loads((COLLECTION / "manifests" / "test-batch-selection.json").read_text())
    ids = [s["token_id"] for s in selection["selected"]]
    witness = load_design_witness()

    required_paths: dict[Path, list[dict]] = {}
    optional_paths: dict[Path, list[dict]] = {}

    for token_id in ids:
        trait_ids = trait_ids_for(witness[token_id])
        for slot in LAYER_STACK:
            reason = skip_reason(slot, trait_ids)
            if reason is not None:
                continue
            candidates = candidate_paths(slot, trait_ids)
            if not candidates:
                continue
            # The generic (last) candidate is what the compositor will actually use
            # unless an entity-specific override is later added — that's the minimum
            # real requirement; entity-specific overrides are listed as optional.
            generic = candidates[-1]
            bucket = required_paths if slot.required else optional_paths
            bucket.setdefault(generic, []).append({"token_id": token_id, "slot": slot.slot, "z": slot.z})
            for extra in candidates[:-1]:
                optional_paths.setdefault(extra, []).append(
                    {"token_id": token_id, "slot": slot.slot, "z": slot.z, "entity_specific_override": True}
                )

    def present(p: Path) -> bool:
        return p.is_file()

    req_present = sum(1 for p in required_paths if present(p))
    req_missing = len(required_paths) - req_present

    report = {
        "batch_size": len(ids),
        "total_collection_assets_required": 105,  # from ASSET-MANIFEST.md's full enumeration
        "unique_required_for_batch": len(required_paths),
        "unique_required_present": req_present,
        "unique_required_missing": req_missing,
        "unique_optional_for_batch": len(optional_paths),
        "required_assets": [
            {"path": str(p.relative_to(ROOT)), "present": present(p),
             "used_by": sorted({u["token_id"] for u in uses}),
             "slots": sorted({u["slot"] for u in uses})}
            for p, uses in sorted(required_paths.items(), key=lambda kv: str(kv[0]))
        ],
        "optional_assets": [
            {"path": str(p.relative_to(ROOT)), "present": present(p),
             "used_by": sorted({u["token_id"] for u in uses}),
             "slots": sorted({u["slot"] for u in uses})}
            for p, uses in sorted(optional_paths.items(), key=lambda kv: str(kv[0]))
        ],
    }

    out_json = COLLECTION / "manifests" / "test-batch-required-assets.json"
    out_json.write_text(json.dumps(report, indent=2) + "\n")

    md = [
        "# GHOST//ROOT — test-batch minimum required assets",
        "",
        "_Computed by `collection/scripts/compute_test_batch_assets.py` from the 20 IDs in "
        "`test-batch-selection.json` and design-witness.csv's real trait assignments — not "
        "estimated._",
        "",
        "```",
        f"TOTAL COLLECTION ASSETS REQUIRED: {report['total_collection_assets_required']}",
        f"UNIQUE ASSETS REQUIRED FOR 20-BATCH: {report['unique_required_for_batch']}",
        f"PRESENT: {report['unique_required_present']}",
        f"MISSING: {report['unique_required_missing']}",
        "```",
        "",
        f"Plus {report['unique_optional_for_batch']} optional assets (entity-specific overrides, "
        "and non-required slots like environmental_depth/corruption-when-intact/grain) that would "
        "refine but not block a render.",
        "",
        "## Required (blocks rendering if missing)",
        "",
        "| Path | Present | Used by (token IDs) | Slot(s) |",
        "| --- | :---: | --- | --- |",
    ]
    for row in report["required_assets"]:
        md.append(f"| `{row['path']}` | {'yes' if row['present'] else 'MISSING'} | "
                   f"{', '.join(map(str, row['used_by']))} | {', '.join(row['slots'])} |")
    (COLLECTION / "reports" / "test-batch-required-assets.md").write_text("\n".join(md) + "\n")

    print(f"wrote {out_json.relative_to(ROOT)} and .md")
    print(f"TOTAL COLLECTION ASSETS REQUIRED: {report['total_collection_assets_required']}")
    print(f"UNIQUE ASSETS REQUIRED FOR 20-BATCH: {report['unique_required_for_batch']}")
    print(f"PRESENT: {report['unique_required_present']}")
    print(f"MISSING: {report['unique_required_missing']}")


if __name__ == "__main__":
    main()
