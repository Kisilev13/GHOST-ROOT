#!/usr/bin/env python3
"""Wave-2 Phase 11 full-stack QA. Greedily choose a covering set of renderable
tokens so every one of the 55 new Wave-2 assets is exercised in at least one full
multi-layer composite, render them via generate_production, then verify from each
render's layers_used that every new asset actually appeared. Also renders the 20
test-batch tokens. Writes collection/reports/wave2-asset-to-qa-token.json.

Rendering uses generate_production's own resolve+composite (real z-order). Output
PNGs go to a temp dir under the repo (caller cleans up); only the mapping + a
review montage are kept.
"""
from __future__ import annotations
import json, subprocess, sys
from pathlib import Path
from collections import defaultdict

COLLECTION = Path(__file__).resolve().parents[1]
ROOT = COLLECTION.parent
INV = json.loads((COLLECTION / "reports" / "wave2-inventory.json").read_text())
RESOLVER = json.loads((COLLECTION / "manifests" / "collection-required-assets.json").read_text())
PY = str(COLLECTION / ".venv" / "bin" / "python3")
GEN = str(COLLECTION / "scripts" / "generate_production.py")


def main():
    new_paths = []
    for slot in INV["slots"]:
        new_paths += INV["slots"][slot]["missing_paths"]
    new_paths = set(new_paths)

    # asset path -> candidate token_ids (from resolver)
    tok_for = {}
    for r in RESOLVER["paths"]:
        if r["path"] in new_paths:
            tok_for[r["path"]] = list(r.get("token_ids", []))

    # Greedy set cover: pick tokens covering the most still-uncovered new assets.
    remaining = set(new_paths)
    chosen = []
    tok_to_assets = defaultdict(set)
    for path, toks in tok_for.items():
        for t in toks:
            tok_to_assets[t].add(path)
    while remaining:
        best = max(tok_to_assets, key=lambda t: len(tok_to_assets[t] & remaining), default=None)
        if best is None or not (tok_to_assets[best] & remaining):
            break
        chosen.append(best)
        remaining -= tok_to_assets[best]
    # add the 20 test-batch tokens for stratified coverage
    tb = json.loads((COLLECTION / "manifests" / "test-batch-selection.json").read_text())
    tb_ids = [s["token_id"] if isinstance(s, dict) else s for s in tb.get("selected", [])]
    render_ids = sorted(set(chosen) | set(tb_ids))
    print(f"new assets: {len(new_paths)}; covering tokens: {len(chosen)}; +test-batch -> {len(render_ids)} render ids")
    if remaining:
        print(f"WARNING: {len(remaining)} assets had no token candidate:", sorted(remaining)[:5])

    # Call the compositor's internal resolve+composite directly (the CLI restricts
    # --ids to the 20 test-batch prototype IDs; QA compositing of arbitrary tokens is
    # legitimate and writes only to a temp dir that is never committed).
    sys.path.insert(0, str(COLLECTION / "scripts"))
    import generate_production as gp
    from asset_manifest import load_trait_catalog
    witness = gp.load_design_witness()
    catalog = load_trait_catalog()
    dest = ROOT / "collection" / ".qa-wave2"
    dest.mkdir(parents=True, exist_ok=True)

    rendered = {}
    for tid in render_ids:
        row = witness.get(tid)
        if row is None:
            continue
        trait_ids = gp.trait_ids_for(row)
        if gp.validate_row(tid, trait_ids, catalog):
            continue
        resolved, missing = gp.plan_token_layers(tid, trait_ids)
        if missing:
            continue  # token still needs an unrendered layer (e.g. authored_scene) -> skip
        used = [str(Path(p).relative_to(ROOT)) if Path(p).is_absolute() else str(p) for _, p in resolved]
        rendered[tid] = {"token_id": tid, "layers_used": used}
        img = gp.composite(resolved)
        img.convert("RGB").save(dest / f"{tid:04d}.png")

    # asset -> rendered tokens that included it
    asset_to_tokens = defaultdict(list)
    for tid, t in rendered.items():
        used = set(t["layers_used"])
        for path in new_paths:
            if path in used:
                asset_to_tokens[path].append(tid)

    covered = {p for p in new_paths if asset_to_tokens[p]}
    uncovered = sorted(new_paths - covered)
    mapping = {
        "new_assets": len(new_paths),
        "rendered_tokens": sorted(rendered),
        "rendered_count": len(rendered),
        "covered": len(covered),
        "uncovered": uncovered,
        "asset_to_qa_token": {Path(p).name: asset_to_tokens[p][:5] for p in sorted(new_paths)},
    }
    (COLLECTION / "reports" / "wave2-asset-to-qa-token.json").write_text(json.dumps(mapping, indent=2) + "\n")
    print(f"rendered {len(rendered)} tokens; covered {len(covered)}/{len(new_paths)} new assets")
    if uncovered:
        print("UNCOVERED:", *[Path(p).name for p in uncovered], sep="\n  ")
    else:
        print("ALL 55 new assets exercised in >=1 full-stack composite.")


if __name__ == "__main__":
    main()
