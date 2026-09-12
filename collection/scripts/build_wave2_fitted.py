#!/usr/bin/env python3
"""Wave 2 fitted authoring: mantle + implant_front + rear_anatomy + interface +
corruption, for exactly the resolver paths that are still missing.

REUSES the established fitted-Gemini pipeline (build_fitted_via_gemini.py):
flatten the representative entity base on black -> gemini image-to-image edit that
ADDS the component keeping the figure identical -> delta-extract + connected-
component cleanup -> transparent 2048 overlay at the resolver path. This module
only adds the prompt fragments the first batch did not cover (mantle kinds and the
corruption 'memory_bleed' kind) and drives generation from the live resolver.

Reads GEMINI_API_KEY from env (source ../.secrets/gemini.env). Never prints it.
"""
from __future__ import annotations
import argparse, json, os, subprocess, sys, tempfile
from pathlib import Path

from build_fitted_via_gemini import (
    IMPLANT, REAR, INTERFACE, CORRUPTION, STYLE, BASE, GEN, PY, LAYERS, CANVAS,
    flatten, extract_clean,
)
from asset_manifest import COLLECTION, ROOT

RESOLVER = COLLECTION / "manifests" / "collection-required-assets.json"

# Kinds the first fitted batch did not define.
MANTLE = {
    "archive_coat": "Add a tall structured archival ceramic coat-collar rising around the neck and standing over both shoulders, stiff overlapping ceramic panels with fine seams, open at the front of the throat.",
    "cable_shroud": "Add a shroud of thick black rubberized cables and conduit bundled densely around the neck and draping over both shoulders, held by a few machined metal clamps.",
    "ceramic_mantle": "Add a broken cracked ceramic crescent mantle collar encircling the neck and upper chest, thick glazed ceramic with one clean fracture and fine gold kintsugi seams.",
    "field_collar": "Add a tall smooth ceramic field-collar standing high and clean around the neck, a minimal cylindrical collar with a subtle vertical front seam.",
    "split_shell": "Add a segmented split-shell shoulder mantle: two symmetric ceramic shell halves seated over the shoulders and hinged at the sides with a clear central gap at the sternum, standing proud of the body.",
}
CORRUPTION_EXTRA = {
    "memory_bleed": "Alter the surface: bleed a soft smeared vertical trail of displaced material running down from one eye and cheek like leaking memory, with faint doubled ghosting streaks following the face contour, restrained and semi-transparent.",
}

SLOT_DIR = {"mantle": "30_mantle", "implant_front": "60_implant_front",
            "rear_anatomy": "20_rear_anatomy", "interface": "60_interface", "corruption": "80_corruption"}
# delta-extract tuning per slot (larger min_area for big garments; smaller for surface fx)
SLOT_PARAMS = {"mantle": (40, 2500), "implant_front": (40, 1200), "rear_anatomy": (40, 1400),
               "interface": (40, 1500), "corruption": (34, 900)}


def prompt_for(slot: str, kind: str) -> str | None:
    if slot == "mantle":
        return MANTLE.get(kind)
    if slot == "implant_front":
        return IMPLANT.get(kind)
    if slot == "rear_anatomy":
        return REAR.get(kind)
    if slot == "interface":
        return INTERFACE.get(kind)
    if slot == "corruption":
        return CORRUPTION.get(kind) or CORRUPTION_EXTRA.get(kind)
    return None


def parse_name(slot: str, fn: str) -> tuple[str, str]:
    """Return (entity, kind) from the canonical filename for the slot."""
    stem = fn.replace(".png", "")
    ent = stem.split("entity__")[1].split("__")[0] if "entity__" in stem else None
    if slot == "rear_anatomy":               # entity__<ent>__implant__<kind>
        kind = stem.split("implant__")[1].split("__")[0]
    else:                                     # <slot>__<kind>__entity__<ent>
        kind = stem.split("__")[1]
    return ent, kind


def missing(slot: str):
    d = json.loads(RESOLVER.read_text())
    out = []
    for r in d["paths"]:
        if r["slot"] != slot or (ROOT / r["path"]).is_file():
            continue
        ent, kind = parse_name(slot, Path(r["path"]).name)
        out.append({"slot": slot, "path": ROOT / r["path"], "rel": r["path"],
                    "fn": Path(r["path"]).name, "entity": ent, "kind": kind})
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--slot", required=True, choices=list(SLOT_DIR))
    ap.add_argument("--only", help="substring filter on filename")
    ap.add_argument("--threshold", type=int)
    ap.add_argument("--min-area", type=int)
    ap.add_argument("--model", default="gemini-3-pro-image")
    a = ap.parse_args()
    if not os.environ.get("GEMINI_API_KEY"):
        sys.exit("GEMINI_API_KEY not set (source .secrets/gemini.env)")
    thr0, ma0 = SLOT_PARAMS[a.slot]
    thr = a.threshold if a.threshold is not None else thr0
    ma = a.min_area if a.min_area is not None else ma0
    todo = missing(a.slot)
    if a.only:
        todo = [t for t in todo if a.only in t["fn"]]
    print(f"{a.slot}: {len(todo)} missing to author (threshold={thr} min_area={ma})")
    tmp = Path(tempfile.mkdtemp(prefix=f"w2-{a.slot}-"))
    env = dict(os.environ, GEMINI_IMAGE_SIZE="2K", GEMINI_ASPECT="1:1")
    ok = 0
    for i, t in enumerate(todo, 1):
        frag = prompt_for(a.slot, t["kind"])
        if not frag:
            print(f"[{i}] SKIP {t['fn']}: no prompt for kind '{t['kind']}'"); continue
        flat = tmp / f"{t['entity']}_flat.png"
        if not flat.exists():
            flatten(BASE[t["entity"]], flat)
        edit = tmp / f"edit_{i}.png"
        p = subprocess.run([str(PY), str(GEN), "--model", a.model, "--ref", str(flat),
                            "--prompt", f"{frag} {STYLE}", "--out", str(edit)],
                           env=env, capture_output=True, text=True)
        if p.returncode != 0 or not edit.exists():
            print(f"[{i}] FAIL gen {t['fn']}: {p.stderr.strip()[:150]}"); continue
        info = extract_clean(flat, edit, t["path"], thr, ma)
        print(f"[{i}] {t['fn']}  {info['pct']}% bbox={info['bbox']}")
        ok += 1
    print(f"\n{ok}/{len(todo)} written")


if __name__ == "__main__":
    main()
