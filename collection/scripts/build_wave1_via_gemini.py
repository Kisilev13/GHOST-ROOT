#!/usr/bin/env python3
"""Wave 1 Gemini fitted authoring for the missing EYES and ARCHITECTURE overlays.

Same philosophy as build_fitted_via_gemini.py: flatten the representative entity
base onto black, ask gemini-3-pro-image to ADD the component while keeping the
figure identical, then delta-extract the addition into a transparent overlay at
the exact resolver path. Guarantees anchor alignment (Gemini edits the real base).

Reads GEMINI_API_KEY from env (source ../.secrets/gemini.env). Never prints it.
Resumable: --only substring filter; --slot eyes|architecture. Writes only the
missing resolver paths by default (skips existing unless --force).
"""
from __future__ import annotations
import argparse, json, os, subprocess, sys, tempfile
from pathlib import Path
import numpy as np
from scipy import ndimage
from PIL import Image, ImageFilter

COLLECTION = Path(__file__).resolve().parents[1]
ROOT = COLLECTION.parent
LAYERS = COLLECTION / "assets" / "layers"
BASES = LAYERS / "40_entity_base"
GEN = COLLECTION / "scripts" / "gen_gemini.py"
PY = COLLECTION / ".venv" / "bin" / "python3"
RESOLVER = COLLECTION / "manifests" / "collection-required-assets.json"
CANVAS = 2048

# Representative sealed/clean base per entity for anchor-consistent editing.
BASE = {
    "human": BASES / "entity__human__material__porcelain__architecture__sealed__v001.png",
    "specter": BASES / "entity__specter__material__phase_glass__architecture__sealed__v001.png",
    "hollow": BASES / "entity__hollow__material__porcelain__architecture__sealed__v001.png",
    "synthetic": BASES / "entity__synthetic__material__ceramic__architecture__sealed__v001.png",
}

STYLE = ("Keep the figure, head, face, pose, material, framing, scale and lighting "
         "PERFECTLY identical and change nothing else outside the described element. "
         "Solid pure black background. Photoreal sculptural museum-archival render, "
         "key light upper-left. No text, no labels, no UI, no wireframe, no neon.")

EYES = {
    "biometric": "Seat a pair of biometric scanner eyes exactly in the two eye sockets: pale amber machined iris-scanner lenses with a fine concentric ring reticle and a thin horizontal scan line across each lens.",
    "optical_array": "Seat a pair of compound optical-array eyes exactly in the two eye sockets: each socket filled with a tight cluster of small machined lenslets forming a faceted dark sensor pod with faint glints.",
    "signal_burn": "Seat a pair of signal-burn eyes exactly in the two eye sockets: sockets scorched and overexposed with a restrained violet-white emitter glow bleeding into a few fine hairline cracks at the socket rim.",
    "thermal": "Seat a pair of recessed thermal sensor oculi exactly in the two eye sockets: dark machined socket rings with a recessed sensor bed and a small deep-red emitter core with one restrained hot spot.",
}
# architecture overlay = the structural facial architecture element added to a sealed shell.
ARCH = {
    "sealed": "Add a crisp machined aperture rim and a fine bone-and-graphite seam framing the intact sealed face shell, a low relief structural edge around the face only.",
    "inset": "Recess the face back inside a surrounding raised aperture frame: a machined ceramic collar standing proud around the face with a visible shadow gap, the face set a few centimetres behind the frame plane.",
    "double_plane": "Add a second offset facial plane: a faint displaced echo of the face shifted slightly to one side, a thin structural ridge between the two planes.",
    "suspended_core": "Break the face shell into a few floating fragments suspended a short distance around a central dark core void, with clean fracture edges and thin structural gaps between the pieces.",
}


def missing_for(slot: str):
    d = json.loads(RESOLVER.read_text())
    out = []
    for r in d["paths"]:
        if r["slot"] != slot:
            continue
        p = ROOT / r["path"]
        fn = Path(r["path"]).name
        ent = fn.split("entity__")[1].split("__")[0]
        # kind token differs by slot layout
        if slot == "eyes":
            kind = fn.split("__")[1]          # eyes__<kind>__entity__<ent>
        else:
            kind = fn.split("__")[1]          # architecture__<kind>__entity__<ent>
        out.append({"slot": slot, "dir": p.parent.name, "fn": fn, "entity": ent, "kind": kind,
                    "path": p, "exists": p.is_file()})
    return out


def flatten(base_path: Path, dst: Path):
    im = Image.open(base_path).convert("RGBA")
    flat = Image.new("RGB", (CANVAS, CANVAS), (0, 0, 0))
    flat.paste(im, mask=im.split()[3])
    flat.save(dst)


def extract_clean(base_flat: Path, edited: Path, dst: Path, threshold: int, min_area: int) -> dict:
    a = np.asarray(Image.open(base_flat).convert("RGB"), dtype=np.int16)
    b_img = Image.open(edited).convert("RGB").resize((CANVAS, CANVAS), Image.LANCZOS)
    b = np.asarray(b_img, dtype=np.int16)
    diff = np.abs(a - b).max(axis=2)
    mask = (diff >= threshold).astype(np.uint8)
    mask = ndimage.binary_closing(mask, structure=np.ones((3, 3)), iterations=1)
    lbl, n = ndimage.label(mask)
    if n:
        sizes = ndimage.sum(np.ones_like(lbl), lbl, index=range(1, n + 1))
        keep = {i + 1 for i, s in enumerate(sizes) if s >= min_area}
        mask = np.isin(lbl, list(keep)) if keep else np.zeros_like(mask)
    mask = mask.astype(np.uint8) * 255
    alpha = Image.fromarray(mask, "L").filter(ImageFilter.MaxFilter(3))
    b_rgba = b_img.convert("RGBA"); b_rgba.putalpha(alpha)
    r, g, bl, al = b_rgba.split(); z = Image.new("L", b_rgba.size, 0)
    out = Image.merge("RGBA", (Image.composite(r, z, al), Image.composite(g, z, al), Image.composite(bl, z, al), al))
    dst.parent.mkdir(parents=True, exist_ok=True)
    out.save(dst, "PNG")
    a2 = np.asarray(alpha)
    return {"bbox": alpha.getbbox(), "opaque_pct": round(float((a2 > 10).mean()) * 100, 2)}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--slot", choices=["eyes", "architecture"], required=True)
    ap.add_argument("--only", help="substring filter on filename")
    ap.add_argument("--threshold", type=int)
    ap.add_argument("--min-area", type=int)
    ap.add_argument("--force", action="store_true")
    ap.add_argument("--model", default="gemini-3-pro-image")
    args = ap.parse_args()
    if not os.environ.get("GEMINI_API_KEY"):
        sys.exit("GEMINI_API_KEY not set (source .secrets/gemini.env)")
    prompts = EYES if args.slot == "eyes" else ARCH
    threshold = args.threshold if args.threshold is not None else (32 if args.slot == "eyes" else 34)
    min_area = args.min_area if args.min_area is not None else (250 if args.slot == "eyes" else 1500)

    todo = [r for r in missing_for(args.slot) if (args.force or not r["exists"])]
    if args.only:
        todo = [r for r in todo if args.only in r["fn"]]
    print(f"{args.slot}: {len(todo)} to author (threshold={threshold} min_area={min_area})")
    tmp = Path(tempfile.mkdtemp(prefix=f"w1-{args.slot}-"))
    env = dict(os.environ, GEMINI_IMAGE_SIZE="2K", GEMINI_ASPECT="1:1")
    ok = 0
    for i, r in enumerate(todo, 1):
        ent, kind = r["entity"], r["kind"]
        if kind not in prompts:
            print(f"[{i}] SKIP {r['fn']}: no prompt for kind '{kind}'"); continue
        flat = tmp / f"{ent}_flat.png"
        if not flat.exists():
            flatten(BASE[ent], flat)
        edit = tmp / f"edit_{i}.png"
        prompt = f"{prompts[kind]} {STYLE}"
        p = subprocess.run([str(PY), str(GEN), "--model", args.model, "--ref", str(flat),
                            "--prompt", prompt, "--out", str(edit)], env=env, capture_output=True, text=True)
        if p.returncode != 0 or not edit.exists():
            print(f"[{i}] FAIL gen {r['fn']}: {p.stderr.strip()[:150]}"); continue
        info = extract_clean(flat, edit, r["path"], threshold, min_area)
        print(f"[{i}] {r['fn']}  opaque={info['opaque_pct']}% bbox={info['bbox']}")
        ok += 1
    print(f"\n{ok}/{len(todo)} written")


if __name__ == "__main__":
    main()
