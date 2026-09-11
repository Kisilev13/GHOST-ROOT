#!/usr/bin/env python3
"""Author the 31 fitted overlay layers via Gemini image-to-image + delta extraction.

For each fitted asset: flatten the representative entity base onto black, ask
gemini-3-pro-image to ADD the component while keeping the figure identical, then
delta-extract the addition and clean it (keep large connected components, drop
scattered texture noise) into a transparent overlay at the exact resolver path.

This guarantees anchor alignment (Gemini edits the real base) and produces
photoreal integrated hardware, not schematic overlays. Resumable: pass --only to
regenerate a subset; existing outputs are overwritten only for the requested keys.

Reads GEMINI_API_KEY from env (source ../.secrets/gemini.env). Never prints it.
"""
from __future__ import annotations
import argparse, io, os, subprocess, sys, tempfile
from pathlib import Path
import numpy as np
from scipy import ndimage
from PIL import Image, ImageFilter

COLLECTION = Path(__file__).resolve().parents[1]
LAYERS = COLLECTION / "assets" / "layers"
BASES = LAYERS / "40_entity_base"
GEN = COLLECTION / "scripts" / "gen_gemini.py"
PY = COLLECTION / ".venv" / "bin" / "python3"
CANVAS = 2048

# Representative base per entity (clean frontal face) used to author entity-qualified overlays.
BASE = {
    "human": BASES / "entity__human__material__porcelain__architecture__sealed__v001.png",
    "specter": BASES / "entity__specter__material__phase_glass__architecture__sealed__v001.png",
    "hollow": BASES / "entity__hollow__material__porcelain__architecture__sealed__v001.png",
    "synthetic": BASES / "entity__synthetic__material__reconstructed__architecture__inset__v001.png",
}

STYLE = ("Keep the figure, head, face, pose, material, framing, scale and lighting "
         "PERFECTLY identical and change nothing else. Solid pure black background. "
         "Photoreal sculptural museum-archival render, key light upper-left, aged "
         "gunmetal / bead-blasted steel / black rubberized conduit / ivory ceramic "
         "with fine gold kintsugi. No text, no labels, no UI, no wireframe, no neon.")

# component prompt fragments
IMPLANT = {
    "antenna": "Add a short bead-blasted steel antenna stalk rising from the temple with a machined collar where it enters the skull and a fine whip tip.",
    "memory_spindle": "Add a horizontal ceramic-and-steel memory-spindle data cartridge half-recessed against the right cheek and jaw, with a visible spool, retaining screws and a short ribbon lead tucked under the jaw.",
    "neural_cable": "Add a machined metal socket at the temple with a fitted connector and a single black rubberized cable draping down past the jaw and neck to a metal strain-relief clamp at the shoulder.",
    "root_port": "Add a heavy circular bulkhead root-port set into the crown/upper-occiput, with a threaded collar, four countersunk bolts and a blanking cap on a short chain.",
    "spinal_bus": "Add a segmented vertebral spinal-bus bar of machined ceramic vertebrae rising from the nape into the lower skull with cabling channels, wrapping slightly past the neck sides.",
    "signal_crown": "Add a sparse crown ring of thin gold antenna signal-crown spikes rising from the skull.",
}
REAR = {
    "antenna": "Behind the head, add the upper continuation of an antenna whip rising above the crown.",
    "neural_cable": "Behind the head and neck, add the hidden run of a black cable looping behind the jaw and around the shoulder so it reads as physically wrapping the neck.",
    "root_port": "Behind the shoulder, add a short cabling tail dropping from an occiput port.",
    "spinal_bus": "Behind the neck, add a tall vertebral column of stacked ceramic vertebrae standing proud above the nape and between the shoulder blades on either side of the neck.",
    "memory_spindle": "Behind the neck, add a ribbon lead routed behind the neck to the shoulder.",
}
INTERFACE = {
    "forensic_plate": "Add a hinged matte-steel forensic evidence plate clamped over the lower face from nose-base to chin, with an etched bezel, breathing slots and two temple straps passing behind the ears.",
    "neural_veil": "Add a fine chainmail mesh veil draped from the brow over the eyes and cheeks following the face contour, sagging slightly, anchored at temple hooks, opaque at the metal hem.",
    "null_mask": "Add a blank featureless ivory-ceramic full-face shell seated a few millimetres off the face with a shadow gap, a centre seam and two jaw clamps, no eye or mouth openings.",
    "respirator": "Add a fitted lower-face respirator with two filter canisters at the cheeks, a machined nose bridge, a ribbed hose stub under the chin and a head strap behind the head.",
    "skeletal_interface": "Add an exposed jaw-and-cheek exoskeleton of thin steel ribs following the skull, screwed to temple and jaw.",
}
CORRUPTION = {
    "absence": "Alter the surface: bite a clean-edged region of the face/shoulder away into a dark hollow void revealing empty interior, with a soft dust vignette at the torn edge.",
    "checksum_burn": "Alter the surface: scorch and crater a charred etched band across one cheek following the face curvature, with raised burnt lips at the edges.",
    "packet_ghosting": "Alter the surface: emboss a faint doubled/displaced echo of an edge feature (ear/jaw) offset a few pixels as if the material stuttered.",
    "scan_shear": "Alter the surface: slip the face along a clean horizontal shear line so the upper and lower halves are offset by about fifteen pixels, with a thin crushed-material seam.",
}

# The 31 assets: (slot_dir, filename, entity, prompt_fragment)
def build_table():
    t = []
    def add(slot, fn, ent, frag): t.append((slot, fn, ent, frag))
    # implant_front (9)
    for ent in ("hollow","human"): add("60_implant_front", f"implant__antenna__entity__{ent}__v001.png", ent, IMPLANT["antenna"])
    add("60_implant_front","implant__memory_spindle__entity__synthetic__v001.png","synthetic",IMPLANT["memory_spindle"])
    for ent in ("human","specter"): add("60_implant_front", f"implant__neural_cable__entity__{ent}__v001.png", ent, IMPLANT["neural_cable"])
    for ent in ("hollow","specter"): add("60_implant_front", f"implant__root_port__entity__{ent}__v001.png", ent, IMPLANT["root_port"])
    for ent in ("hollow","specter"): add("60_implant_front", f"implant__spinal_bus__entity__{ent}__v001.png", ent, IMPLANT["spinal_bus"])
    # rear_anatomy (9): filename is entity__{ent}__implant__{val}
    for ent,val in [("hollow","antenna"),("hollow","root_port"),("hollow","spinal_bus"),("human","antenna"),
                    ("human","neural_cable"),("specter","neural_cable"),("specter","root_port"),
                    ("specter","spinal_bus"),("synthetic","memory_spindle")]:
        add("20_rear_anatomy", f"entity__{ent}__implant__{val}__v001.png", ent, REAR[val])
    # interface (7)
    for ent in ("hollow","specter","synthetic"): add("60_interface", f"interface__forensic_plate__entity__{ent}__v001.png", ent, INTERFACE["forensic_plate"])
    add("60_interface","interface__neural_veil__entity__specter__v001.png","specter",INTERFACE["neural_veil"])
    add("60_interface","interface__null_mask__entity__human__v001.png","human",INTERFACE["null_mask"])
    add("60_interface","interface__respirator__entity__human__v001.png","human",INTERFACE["respirator"])
    add("60_interface","interface__skeletal_interface__entity__hollow__v001.png","hollow",INTERFACE["skeletal_interface"])
    # corruption (6)
    for ent in ("hollow","specter"): add("80_corruption", f"corruption__absence__entity__{ent}__v001.png", ent, CORRUPTION["absence"])
    add("80_corruption","corruption__checksum_burn__entity__human__v001.png","human",CORRUPTION["checksum_burn"])
    add("80_corruption","corruption__packet_ghosting__entity__hollow__v001.png","hollow",CORRUPTION["packet_ghosting"])
    for ent in ("human","specter"): add("80_corruption", f"corruption__scan_shear__entity__{ent}__v001.png", ent, CORRUPTION["scan_shear"])
    return t


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
    # bridge small gaps then keep only large connected components (drop texture noise)
    mask = ndimage.binary_closing(mask, structure=np.ones((3, 3)), iterations=1)
    lbl, n = ndimage.label(mask)
    if n:
        sizes = ndimage.sum(np.ones_like(lbl), lbl, index=range(1, n + 1))
        keep = {i + 1 for i, s in enumerate(sizes) if s >= min_area}
        mask = np.isin(lbl, list(keep)) if keep else np.zeros_like(mask)
    mask = mask.astype(np.uint8) * 255
    alpha = Image.fromarray(mask, "L").filter(ImageFilter.MaxFilter(3))
    # straight RGB from edited under the mask, zero elsewhere
    rgba = b_img.convert("RGBA"); rgba.putalpha(alpha)
    r, g, bl, al = rgba.split(); z = Image.new("L", rgba.size, 0)
    out = Image.merge("RGBA", (Image.composite(r, z, al), Image.composite(g, z, al), Image.composite(bl, z, al), al))
    bbox = alpha.getbbox()
    opaque = int((np.asarray(alpha) > 10).sum())
    dst.parent.mkdir(parents=True, exist_ok=True)
    out.save(dst, "PNG")
    return {"bbox": bbox, "opaque": opaque, "pct": round(opaque / (CANVAS * CANVAS) * 100, 2)}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--only", help="substring filter on filename")
    ap.add_argument("--threshold", type=int, default=40)
    ap.add_argument("--min-area", type=int, default=1200)
    ap.add_argument("--model", default="gemini-3-pro-image")
    args = ap.parse_args()
    if not os.environ.get("GEMINI_API_KEY"):
        sys.exit("GEMINI_API_KEY not set (source .secrets/gemini.env)")
    table = build_table()
    if args.only:
        table = [r for r in table if args.only in r[1]]
    print(f"processing {len(table)} fitted assets")
    tmp = Path(tempfile.mkdtemp(prefix="fitted-"))
    env = dict(os.environ, GEMINI_IMAGE_SIZE="2K", GEMINI_ASPECT="1:1")
    ok = 0
    for i, (slot, fn, ent, frag) in enumerate(table, 1):
        base = BASE[ent]
        flat = tmp / f"{ent}_flat.png"
        if not flat.exists():
            flatten(base, flat)
        edit = tmp / f"edit_{i}.png"
        prompt = f"{frag} {STYLE}"
        r = subprocess.run([str(PY), str(GEN), "--model", args.model, "--ref", str(flat),
                            "--prompt", prompt, "--out", str(edit)], env=env,
                           capture_output=True, text=True)
        if r.returncode != 0 or not edit.exists():
            print(f"[{i}/{len(table)}] FAIL gen {fn}: {r.stderr.strip()[:160]}")
            continue
        dst = LAYERS / slot / fn
        info = extract_clean(flat, edit, dst, args.threshold, args.min_area)
        print(f"[{i}/{len(table)}] {fn}  {info['pct']}% bbox={info['bbox']}")
        ok += 1
    print(f"\n{ok}/{len(table)} fitted assets written")


if __name__ == "__main__":
    main()
