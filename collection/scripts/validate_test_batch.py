#!/usr/bin/env python3
"""Phase 10 — validate the 20-identity test batch: artwork, metadata, rarity.

Writes collection/reports/test-batch-validation.json (machine-readable) and
.md (human-readable). Every check is a real check against real files — a
category with nothing to check (e.g. artwork, since none is rendered yet)
reports that honestly rather than being silently skipped or faked as passing.
"""
from __future__ import annotations

import csv
import hashlib
import json
import re
from pathlib import Path

from jsonschema import Draft202012Validator

ROOT = Path(__file__).resolve().parents[2]
COLLECTION = ROOT / "collection"
TEST_BATCH_IMG = COLLECTION / "assets" / "test-batch"
TEST_BATCH_META = COLLECTION / "metadata" / "test-batch"
SCHEMA = json.loads((COLLECTION / "metadata" / "schema.json").read_text())
SELECTION = json.loads((COLLECTION / "manifests" / "test-batch-selection.json").read_text())

ARCH_TO_BAND = {
    "architecture.sealed": "COMMON", "architecture.inset": "UNCOMMON",
    "architecture.double_plane": "RARE", "architecture.suspended_core": "EPIC",
    "architecture.severed_halo": "LEGENDARY", "architecture.singular": "1/1",
}
DANGEROUS = re.compile(r"localhost|127\.0\.0\.1|0\.0\.0\.0|file://|/home/|/etc/|password|secret|api[_-]?key", re.I)


def load_design_witness() -> dict[int, dict]:
    with (COLLECTION / "design-witness.csv").open(newline="") as f:
        return {int(r["token_id"]): r for r in csv.DictReader(f)}


def load_trait_catalog() -> dict[str, dict]:
    data = json.loads((COLLECTION / "traits.yaml").read_text())
    return {t["id"]: t for t in data["traits"]}


def load_legendary_ids() -> set[int]:
    data = json.loads((COLLECTION / "legendary-reservations.yaml").read_text())
    return {r["token_id"] for r in data["reservations"]}


def check_artwork(ids: list[int]) -> dict:
    results = {"checked": 0, "present": 0, "issues": [], "hashes": {}, "exact_duplicates": []}
    for tid in ids:
        path = TEST_BATCH_IMG / f"{tid:04d}.png"
        results["checked"] += 1
        if not path.is_file():
            continue
        results["present"] += 1
        data = path.read_bytes()
        if len(data) == 0:
            results["issues"].append(f"{tid}: zero-byte file")
            continue
        try:
            from PIL import Image

            with Image.open(path) as img:
                if img.size != (2048, 2048):
                    results["issues"].append(f"{tid}: {img.size}, expected 2048x2048")
                if img.mode not in ("RGB", "RGBA"):
                    results["issues"].append(f"{tid}: mode {img.mode}, expected RGB/RGBA")
        except Exception as e:  # noqa: BLE001
            results["issues"].append(f"{tid}: unreadable image ({e})")
            continue
        digest = hashlib.sha256(data).hexdigest()
        if digest in results["hashes"]:
            results["exact_duplicates"].append([results["hashes"][digest], tid])
        results["hashes"][digest] = tid
    results["hashes"] = len(results["hashes"])  # don't leak full hash map into the report
    return results


def check_metadata(ids: list[int], witness: dict, catalog: dict, legendary_ids: set[int]) -> dict:
    validator = Draft202012Validator(SCHEMA)
    results = {"checked": 0, "valid_json": 0, "schema_pass": 0, "schema_expected_fail": 0,
               "trait_mismatches": [], "duplicate_categories": [], "null_required": [],
               "unexpected_categories": [], "filesystem_or_secret_leaks": [], "name_mismatches": [],
               "id_mismatches": [], "band_mismatches": [], "issues": []}
    for tid in ids:
        results["checked"] += 1
        path = TEST_BATCH_META / f"{tid:04d}.json"
        if not path.is_file():
            results["issues"].append(f"{tid}: metadata file missing")
            continue
        try:
            record = json.loads(path.read_text())
        except json.JSONDecodeError as e:
            results["issues"].append(f"{tid}: invalid JSON ({e})")
            continue
        results["valid_json"] += 1

        errors = sorted(validator.iter_errors(record), key=lambda e: e.path)
        # `image` is expected to fail schema (pending:// is not a real ipfs:// URI) until
        # upload happens — that's the ONE known/expected failure for test-batch metadata.
        real_errors = [e for e in errors if list(e.path) != ["image"]]
        if not real_errors:
            results["schema_pass"] += 1
        else:
            results["issues"].append(f"{tid}: schema errors: " + "; ".join(str(e.message) for e in real_errors))
        if any(list(e.path) == ["image"] for e in errors):
            results["schema_expected_fail"] += 1

        if record.get("token_id") != tid:
            results["id_mismatches"].append(tid)
        if record.get("name") != f"GHOST//{tid:04d}":
            results["name_mismatches"].append(tid)

        attrs = record.get("attributes", [])
        types_seen = [a["trait_type"] for a in attrs]
        dupes = {t for t in types_seen if types_seen.count(t) > 1}
        if dupes:
            results["duplicate_categories"].append({tid: sorted(dupes)})
        nulls = [a["trait_type"] for a in attrs if a.get("value") in (None, "")]
        if nulls:
            results["null_required"].append({tid: nulls})

        expected_types = {"Entity", "Access", "Architecture", "Face Material", "Eyes", "Interface",
                           "Implant", "Mantle", "Background", "Signal", "Origin Corruption",
                           "State", "Rarity Band"}
        unexpected = set(types_seen) - expected_types
        if unexpected:
            results["unexpected_categories"].append({tid: sorted(unexpected)})

        # trait values must exactly match design-witness.csv
        row = witness.get(tid, {})
        by_type = {a["trait_type"]: a["value"] for a in attrs}
        field_map = {"Entity": "entity", "Access": "access", "Architecture": "architecture",
                     "Face Material": "material", "Eyes": "eyes", "Interface": "interface",
                     "Implant": "implant", "Mantle": "mantle", "Background": "background",
                     "Signal": "signal", "Origin Corruption": "corruption"}
        for label, field in field_map.items():
            expected = catalog[row[field]]["display_name"] if row.get(field) in catalog else None
            if by_type.get(label) != expected:
                results["trait_mismatches"].append(f"{tid}.{label}: metadata={by_type.get(label)!r} "
                                                     f"design_witness={expected!r}")

        expected_band = ARCH_TO_BAND.get(row.get("architecture"))
        if by_type.get("Rarity Band") != expected_band:
            results["band_mismatches"].append(f"{tid}: metadata={by_type.get('Rarity Band')!r} "
                                                f"expected={expected_band!r}")

        blob = json.dumps(record)
        if DANGEROUS.search(blob.replace(record.get("image", ""), "")):
            results["filesystem_or_secret_leaks"].append(tid)

    return results


def check_rarity(ids: list[int], witness: dict, legendary_ids: set[int]) -> dict:
    results = {"band_by_id": {}, "legendary_ids_in_batch": [], "legendary_reservation_ok": True, "issues": []}
    for tid in ids:
        row = witness[tid]
        band = ARCH_TO_BAND[row["architecture"]]
        results["band_by_id"][tid] = band
        if tid in legendary_ids:
            results["legendary_ids_in_batch"].append(tid)
            if band != "LEGENDARY":
                results["legendary_reservation_ok"] = False
                results["issues"].append(f"{tid}: reserved legendary but design-witness band is {band}")
    return results


def main() -> None:
    ids = [s["token_id"] for s in SELECTION["selected"]]
    witness = load_design_witness()
    catalog = load_trait_catalog()
    legendary_ids = load_legendary_ids()

    report = {
        "batch_size": len(ids),
        "token_ids": ids,
        "artwork": check_artwork(ids),
        "metadata": check_metadata(ids, witness, catalog, legendary_ids),
        "rarity": check_rarity(ids, witness, legendary_ids),
    }

    out_json = COLLECTION / "reports" / "test-batch-validation.json"
    out_json.write_text(json.dumps(report, indent=2) + "\n")

    md = ["# GHOST//ROOT — test-batch validation", "", f"_{len(ids)} token IDs; see "
          "`manifests/test-batch-selection.json` for why each was chosen._", "",
          "## Artwork", "",
          f"- {report['artwork']['present']}/{report['artwork']['checked']} images present"
          f" (0 expected — see production-art-audit.md Finding 2: no layer assets exist to render from)",
          f"- issues: {report['artwork']['issues'] or 'none'}",
          f"- exact duplicates: {report['artwork']['exact_duplicates'] or 'none'}", "",
          "## Metadata", "",
          f"- {report['metadata']['valid_json']}/{report['metadata']['checked']} valid JSON",
          f"- {report['metadata']['schema_pass']}/{report['metadata']['checked']} pass schema "
          f"({report['metadata']['schema_expected_fail']} have the expected `image` failure — "
          "pending:// is not a real ipfs:// URI yet, by design)",
          f"- id mismatches: {report['metadata']['id_mismatches'] or 'none'}",
          f"- name mismatches: {report['metadata']['name_mismatches'] or 'none'}",
          f"- trait value mismatches vs design-witness.csv: {report['metadata']['trait_mismatches'] or 'none'}",
          f"- duplicate trait categories: {report['metadata']['duplicate_categories'] or 'none'}",
          f"- null required traits: {report['metadata']['null_required'] or 'none'}",
          f"- unexpected trait categories: {report['metadata']['unexpected_categories'] or 'none'}",
          f"- rarity band mismatches: {report['metadata']['band_mismatches'] or 'none'}",
          f"- filesystem/secret leaks: {report['metadata']['filesystem_or_secret_leaks'] or 'none'}",
          f"- other issues: {report['metadata']['issues'] or 'none'}", "",
          "## Rarity", "",
          f"- legendary IDs in batch: {report['rarity']['legendary_ids_in_batch']}",
          f"- legendary reservation consistency: {'OK' if report['rarity']['legendary_reservation_ok'] else 'FAIL'}",
          f"- band distribution: {report['rarity']['band_by_id']}", ""]
    (COLLECTION / "reports" / "test-batch-validation.md").write_text("\n".join(md))

    print(f"wrote {out_json.relative_to(ROOT)} and .md")
    hard_fail = (report["metadata"]["id_mismatches"] or report["metadata"]["name_mismatches"]
                 or report["metadata"]["trait_mismatches"] or report["metadata"]["duplicate_categories"]
                 or report["metadata"]["null_required"] or report["metadata"]["unexpected_categories"]
                 or report["metadata"]["filesystem_or_secret_leaks"] or report["metadata"]["band_mismatches"]
                 or not report["rarity"]["legendary_reservation_ok"] or report["artwork"]["exact_duplicates"])
    raise SystemExit(1 if hard_fail else 0)


if __name__ == "__main__":
    main()
