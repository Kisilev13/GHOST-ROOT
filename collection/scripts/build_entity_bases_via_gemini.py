#!/usr/bin/env python3
"""Author missing entity_base cutouts via Gemini image-to-image + chroma key.

Each base = entity x material x architecture. To keep framing/anchors consistent
with the existing bases (so entity-qualified overlays still align), we edit a
same-entity REFERENCE base: keep the exact head position/scale/pose/camera/
lighting, restyle only the material + architecture, on a solid magenta field.
Then chroma-key magenta -> alpha (dark interior voids stay opaque, unlike a
black key). Output is a transparent 2048 RGBA cutout at the resolver path.

Reads GEMINI_API_KEY from env. Never prints it. Resumable: skips existing
outputs unless --force; --limit / --only for testing.
"""
from __future__ import annotations
import argparse, json, os, subprocess, sys, tempfile
from pathlib import Path
import numpy as np
from PIL import Image, ImageFilter

COLLECTION = Path(__file__).resolve().parents[1]
ROOT = COLLECTION.parent
BASEDIR = COLLECTION / "assets" / "layers" / "40_entity_base"
GEN = COLLECTION / "scripts" / "gen_gemini.py"
PY = COLLECTION / ".venv" / "bin" / "python3"
CANVAS = 2048
MAGENTA = (255, 0, 255)

REF = {  # clean same-entity reference base for framing preservation
    "hollow": BASEDIR / "entity__hollow__material__porcelain__architecture__sealed__v001.png",
    "human": BASEDIR / "entity__human__material__porcelain__architecture__sealed__v001.png",
    "specter": BASEDIR / "entity__specter__material__phase_glass__architecture__sealed__v001.png",
    "synthetic": BASEDIR / "entity__synthetic__material__reconstructed__architecture__inset__v001.png",
}
MATERIAL = {
    "porcelain": "glossy white porcelain with fine craquelure",
    "ceramic": "matte ivory ceramic",
    "carbon": "dark matte graphite carbon-fibre",
    "biosynth": "translucent wet bio-synthetic polymer with a subsurface sheen",
    "phase_glass": "transparent refractive phase-glass",
    "reconstructed": "cracked reconstructed ceramic rejoined with fine gold kintsugi seams",
}
ARCH = {
    "sealed": "an intact sealed shell face",
    "inset": "the face recessed and set back inside a surrounding ceramic aperture frame",
    "double_plane": "two slightly offset facial planes — a front face with a faint echo plane beside it",
    "suspended_core": "the shell broken into fragments suspended around a central void core",
}
STYLE = ("Keep the exact head position, scale, pose, framing, camera angle and key "
         "light from upper-left perfectly identical — change ONLY the material and "
         "facial architecture as described. Solid flat magenta (#FF00FF) background, "
         "no shadow on the background. Frontal centered bust, bare (no implants, no "
         "mask, no cables, no halo), hyperreal sculptural museum-archival render. "
         "No text, no labels, no UI.")


def missing_bases() -> list[tuple[str, str, str, Path]]:
    d = json.loads((COLLECTION / "manifests" / "collection-required-assets.json").read_text())
    out = []
    for r in d["paths"]:
        if r["slot"] != "entity_base":
            continue
        p = ROOT / r["path"]
        if p.is_file():
            continue
        fn = p.name
        parts = fn.replace(".png", "").split("__")
        out.append((parts[1], parts[3], parts[5], p))  # entity, material, arch, dest
    return out


def flatten_on_magenta(ref: Path, dst: Path):
    im = Image.open(ref).convert("RGBA")
    bg = Image.new("RGB", (CANVAS, CANVAS), MAGENTA)
    bg.paste(im, mask=im.split()[3])
    bg.save(dst)


def chroma_key(src: Path, dst: Path) -> dict:
    im = Image.open(src).convert("RGB").resize((CANVAS, CANVAS), Image.LANCZOS)
    a = np.asarray(im, dtype=np.int16)
    R, G, B = a[..., 0], a[..., 1], a[..., 2]
    # magenta-ness: high R and B, low G
    mag = np.minimum(R, B) - G
    alpha = np.where(mag > 60, 0, 255).astype(np.uint8)
    # soft edge + despeckle
    al = Image.fromarray(alpha, "L").filter(ImageFilter.MedianFilter(3)).filter(ImageFilter.MaxFilter(3))
    alpha = np.asarray(al)
    # despill: where slightly magenta, pull R,B down toward G
    spill = (mag > 10) & (alpha > 0)
    rgb = a.copy()
    rgb[..., 0] = np.where(spill, np.minimum(R, G), R)
    rgb[..., 2] = np.where(spill, np.minimum(B, G), B)
    out = np.dstack([rgb.astype(np.uint8), alpha])
    # zero rgb fully-transparent
    out[alpha == 0] = (0, 0, 0, 0)
    img = Image.fromarray(out, "RGBA")
    dst.parent.mkdir(parents=True, exist_ok=True)
    img.save(dst, "PNG")
    a2 = img.getchannel("A")
    return {"bbox": a2.getbbox(), "opaque_pct": round(float((np.asarray(a2) > 10).mean()) * 100, 1)}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--only", help="substring filter on filename")
    ap.add_argument("--limit", type=int)
    ap.add_argument("--force", action="store_true")
    ap.add_argument("--model", default="gemini-3-pro-image")
    a = ap.parse_args()
    if not os.environ.get("GEMINI_API_KEY"):
        sys.exit("GEMINI_API_KEY not set")
    todo = missing_bases()
    if a.only:
        todo = [t for t in todo if a.only in t[3].name]
    if a.limit:
        todo = todo[: a.limit]
    print(f"{len(todo)} entity bases to author")
    tmp = Path(tempfile.mkdtemp(prefix="bases-"))
    env = dict(os.environ, GEMINI_IMAGE_SIZE="2K", GEMINI_ASPECT="1:1")
    refflat: dict[str, Path] = {}
    ok = 0
    for i, (ent, mat, arch, dest) in enumerate(todo, 1):
        if dest.is_file() and not a.force:
            print(f"[{i}] skip existing {dest.name}"); continue
        ref = REF[ent]
        if ent not in refflat:
            refflat[ent] = tmp / f"{ent}_ref_magenta.png"; flatten_on_magenta(ref, refflat[ent])
        edit = tmp / f"edit_{i}.png"
        prompt = (f"This is a {ent} archive figure. Restyle it: the material becomes "
                  f"{MATERIAL[mat]} and the facial architecture becomes {ARCH[arch]}. {STYLE}")
        r = subprocess.run([str(PY), str(GEN), "--model", a.model, "--ref", str(refflat[ent]),
                            "--prompt", prompt, "--out", str(edit)], env=env, capture_output=True, text=True)
        if r.returncode != 0 or not edit.exists():
            print(f"[{i}] FAIL {dest.name}: {r.stderr.strip()[:140]}"); continue
        info = chroma_key(edit, dest)
        print(f"[{i}] {dest.name}  opaque={info['opaque_pct']}% bbox={info['bbox']}")
        ok += 1
    print(f"\n{ok} bases written")


if __name__ == "__main__":
    main()
