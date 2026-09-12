#!/usr/bin/env python3
"""Wave-2 contact sheets: composite each NEW asset on its canonical entity base.
Front slots (mantle/implant_front/interface/corruption) draw the overlay over the
base; rear_anatomy is composited BEHIND the base (bg -> rear -> base) so it is
reviewed exactly as the compositor stacks it. Readable tiles, canonical labels.
"""
from __future__ import annotations
import json, sys
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

COLLECTION = Path(__file__).resolve().parents[1]
ROOT = COLLECTION.parent
L = COLLECTION / "assets" / "layers"
BASES = L / "40_entity_base"
INV = json.loads((COLLECTION / "reports" / "wave2-inventory.json").read_text())
SEALED = {
    "hollow": "entity__hollow__material__porcelain__architecture__sealed__v001.png",
    "human": "entity__human__material__porcelain__architecture__sealed__v001.png",
    "specter": "entity__specter__material__phase_glass__architecture__sealed__v001.png",
    "synthetic": "entity__synthetic__material__ceramic__architecture__sealed__v001.png",
}
SLOT_DIR = {"mantle": "30_mantle", "implant_front": "60_implant_front",
            "rear_anatomy": "20_rear_anatomy", "interface": "60_interface", "corruption": "80_corruption"}


def font(s):
    q = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
    return ImageFont.truetype(q, s) if Path(q).is_file() else ImageFont.load_default()


def entity_of(fn):
    return fn.split("entity__")[1].split("__")[0] if "entity__" in fn else fn.split("__")[1]


def composite(slot, fn):
    ent = entity_of(fn)
    base = Image.open(BASES / SEALED[ent]).convert("RGBA")
    ov = Image.open(L / SLOT_DIR[slot] / fn).convert("RGBA")
    canvas = Image.new("RGBA", (2048, 2048), (48, 48, 54, 255))
    if slot == "rear_anatomy":            # bg -> rear -> base (rear behind base)
        canvas = Image.alpha_composite(canvas, ov)
        canvas = Image.alpha_composite(canvas, base)
    else:                                  # bg -> base -> overlay
        canvas = Image.alpha_composite(canvas, base)
        canvas = Image.alpha_composite(canvas, ov)
    return canvas.convert("RGB")


def build(slot):
    files = sorted(INV["slots"][slot]["missing_files"])
    n = len(files); cols = 4 if n > 6 else 3
    rows = (n + cols - 1) // cols
    T, lab = 470, 24
    f = font(19)
    sheet = Image.new("RGB", (cols * T, rows * (T + lab)), (26, 26, 30))
    d = ImageDraw.Draw(sheet)
    for i, fn in enumerate(files):
        img = composite(slot, fn).resize((T, T), Image.LANCZOS)
        x, y = (i % cols) * T, (i // cols) * (T + lab)
        sheet.paste(img, (x, y))
        lbl = fn.replace(f"{slot}__", "").replace("__v001.png", "").replace("implant__", "").replace("entity__", "")
        d.rectangle([x, y + T, x + T, y + T + lab], fill=(10, 10, 14))
        d.text((x + 5, y + T + 4), lbl, fill=(232, 232, 236), font=f)
    out = COLLECTION / "reports" / f"wave2-{slot.replace('_','-')}-review.png"
    sheet.save(out)
    print(f"wrote {out}  ({n} tiles, {cols}x{rows})")


if __name__ == "__main__":
    for slot in (sys.argv[1:] or SLOT_DIR):
        build(slot)
