#!/usr/bin/env python3
"""Phase 9 — Metaplex-style test metadata for the 20-identity test batch.

Reads design-witness.csv (authoritative trait assignment), traits.yaml
(display names), genesis.yaml (bespoke Genesis lore), and
legendary-reservations.yaml (special-slot status) — never invents a trait
value. Writes collection/metadata/test-batch/NNNN.json.

image: this is TEST metadata, generated before any render or upload exists.
collection/metadata/schema.json's `image` pattern requires a real ipfs://
CID — inventing one would be a fake IPFS URL, which this task explicitly
forbids. Instead every test file uses an obviously-local, obviously-pending
URI scheme (pending://) that cannot be mistaken for a real link and will
correctly fail schema validation until upload actually happens; validate.py
(Phase 10) reports that failure as EXPECTED for test-batch files, not as a
generation bug.
"""
from __future__ import annotations

import csv
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
COLLECTION = ROOT / "collection"
OUT_DIR = COLLECTION / "metadata" / "test-batch"

ARCH_TO_BAND = {
    "architecture.sealed": "COMMON",
    "architecture.inset": "UNCOMMON",
    "architecture.double_plane": "RARE",
    "architecture.suspended_core": "EPIC",
    "architecture.severed_halo": "LEGENDARY",
    "architecture.singular": "1/1",
}

CATEGORIES = ("entity", "access", "architecture", "material", "eyes", "interface",
              "implant", "mantle", "background", "signal", "corruption")


def load_design_witness() -> dict[int, dict]:
    with (COLLECTION / "design-witness.csv").open(newline="") as f:
        return {int(r["token_id"]): r for r in csv.DictReader(f)}


def load_trait_catalog() -> dict[str, dict]:
    data = json.loads((COLLECTION / "traits.yaml").read_text())
    return {t["id"]: t for t in data["traits"]}


def load_genesis() -> dict[int, dict]:
    data = json.loads((COLLECTION / "genesis.yaml").read_text())
    return {c["token_id"]: c for c in data["characters"]}


def load_legendary() -> dict[int, dict]:
    data = json.loads((COLLECTION / "legendary-reservations.yaml").read_text())
    return {r["token_id"]: r for r in data["reservations"]}


def build(token_id: int, row: dict, catalog: dict, genesis: dict, legendary: dict) -> dict:
    name = f"GHOST//{token_id:04d}"
    attributes = []
    for cat in CATEGORIES:
        trait = catalog[row[cat]]
        display = "Face Material" if cat == "material" else trait["category"].capitalize()
        display = {"material": "Face Material", "corruption": "Origin Corruption"}.get(cat, cat.capitalize())
        attributes.append({"trait_type": display, "value": trait["display_name"]})

    band = ARCH_TO_BAND[row["architecture"]]
    attributes.append({"trait_type": "State", "value": "DORMANT"})
    attributes.append({"trait_type": "Rarity Band", "value": band})

    if token_id in genesis:
        g = genesis[token_id]
        description = (
            f"{g['quote']} — GHOST//ROOT Genesis identity {g['codename']}. "
            "One of exactly three hand-authored 1/1 origin records. "
            f"Origin: {g['origin']}"
        )
        provenance = {"kind": "genesis", "codename": g["codename"], "art_status": g["art_status"],
                      "dossier": g["dossier"]}
    elif token_id in legendary:
        r = legendary[token_id]
        description = (
            f"GHOST//ROOT legendary identity — archetype {r['archetype'].upper()}, "
            f"interpretation {r['interpretation']}. Recovered from the ROOT network's "
            "severed-halo tier."
        )
        provenance = {"kind": "legendary", "archetype": r["archetype"], "interpretation": r["interpretation"],
                      "art_status": r["art_status"], "full_trait_vector_status": r["full_trait_vector_status"]}
    else:
        description = (
            "GHOST//ROOT — one of 3,333 identities recovered from a distributed autonomous "
            "security network that disappeared without explanation."
        )
        provenance = {"kind": "procedural"}

    # schema.json sets additionalProperties: false at the top level (deliberately strict,
    # matching this project's "never silently relax a target" philosophy) — so operational
    # provenance/status data does NOT belong inside the schema-conformant metadata file.
    # It goes in a separate companion file instead; see build().
    metadata = {
        "token_id": token_id,
        "name": name,
        "description": description,
        "image": f"pending://not-yet-rendered/collection/assets/test-batch/{token_id:04d}.png",
        "external_url": "https://ghostroot.site/identity/" + str(token_id) + "/",
        "collection": {"name": "GHOST//ROOT", "symbol": "GHRT"},
        "attributes": attributes,
    }
    companion = {
        "token_id": token_id,
        "design_witness_status": row["status"],
        "provenance": provenance,
        "chain": "solana",
        "standard": "mpl_core",
        "media_status": "not_rendered",
        "metadata_status": "test_batch_not_production",
    }
    return metadata, companion


def main() -> None:
    selection = json.loads((COLLECTION / "manifests" / "test-batch-selection.json").read_text())
    ids = [s["token_id"] for s in selection["selected"]]

    witness = load_design_witness()
    catalog = load_trait_catalog()
    genesis = load_genesis()
    legendary = load_legendary()

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    written = []
    for token_id in ids:
        row = witness[token_id]
        metadata, companion = build(token_id, row, catalog, genesis, legendary)
        path = OUT_DIR / f"{token_id:04d}.json"
        path.write_text(json.dumps(metadata, indent=2) + "\n")
        companion_path = OUT_DIR / f"{token_id:04d}.provenance.json"
        companion_path.write_text(json.dumps(companion, indent=2) + "\n")
        written.append(str(path.relative_to(ROOT)))
        print(f"wrote {path.relative_to(ROOT)} + {companion_path.name}")

    print(f"\n{len(written)} metadata files written to {OUT_DIR.relative_to(ROOT)} "
          "(plus one .provenance.json companion each — operational data schema.json doesn't allow)")


if __name__ == "__main__":
    main()
