#!/usr/bin/env python3
"""Readable per-entity contact sheets for entity-base visual QA (offline).

One sheet per entity (human/hollow/specter/synthetic): rows = material,
cols = architecture, each tile composited on neutral gray with a canonical label.
Tiles are large (not postage stamps) so a human can actually review them.
Writes collection/reports/entity-base-review-<entity>.png.
"""
from __future__ import annotations
import collections
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

COLLECTION = Path(__file__).resolve().parents[1]
BASEDIR = COLLECTION / "assets" / "layers" / "40_entity_base"
OUTDIR = COLLECTION / "reports"
TILE = 460
LABEL_H = 30
PAD = 8
BG = (32, 34, 38)
GRID = (70, 74, 82)
CHECK_A, CHECK_B = (60, 60, 66), (48, 48, 54)
TXT = (232, 232, 236)

ARCH_ORDER = ["sealed", "inset", "double_plane", "suspended_core"]


def font(sz):
    for p in ("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
              "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"):
        if Path(p).is_file():
            return ImageFont.truetype(p, sz)
    return ImageFont.load_default()


def checker(sz, cell=16):
    im = Image.new("RGB", (sz, sz), CHECK_A)
    d = ImageDraw.Draw(im)
    for y in range(0, sz, cell):
        for x in range(0, sz, cell):
            if (x // cell + y // cell) % 2:
                d.rectangle([x, y, x + cell, y + cell], fill=CHECK_B)
    return im


def tile(path: Path) -> Image.Image:
    base = checker(TILE)
    with Image.open(path) as im:
        rgba = im.convert("RGBA").resize((TILE, TILE), Image.LANCZOS)
    base.paste(rgba, (0, 0), rgba)
    return base


def main():
    files = sorted(BASEDIR.glob("*.png"))
    by_ent = collections.defaultdict(dict)  # ent -> {(mat,arch): path}
    for p in files:
        parts = p.name.replace(".png", "").split("__")
        by_ent[parts[1]][(parts[3], parts[5])] = p

    fnt = font(20)
    fnt_hdr = font(30)
    written = []
    for ent in sorted(by_ent):
        combos = by_ent[ent]
        mats = sorted({m for m, a in combos})
        archs = [a for a in ARCH_ORDER if any(a == aa for _, aa in combos)]
        ncols, nrows = len(archs), len(mats)
        W = PAD + ncols * (TILE + PAD)
        H = 56 + nrows * (TILE + LABEL_H + PAD) + PAD
        sheet = Image.new("RGB", (W, H), BG)
        d = ImageDraw.Draw(sheet)
        d.text((PAD, 14), f"GHOST//ROOT entity_base — {ent.upper()}  ({len(combos)} bases)  rows=material  cols=architecture",
                fill=TXT, font=fnt_hdr)
        for r, mat in enumerate(mats):
            for c, arch in enumerate(archs):
                x = PAD + c * (TILE + PAD)
                y = 56 + r * (TILE + LABEL_H + PAD)
                p = combos.get((mat, arch))
                if p is None:
                    d.rectangle([x, y, x + TILE, y + TILE], outline=GRID, width=2)
                    d.text((x + 10, y + TILE // 2), "(no combo)", fill=GRID, font=fnt)
                else:
                    sheet.paste(tile(p), (x, y))
                    d.rectangle([x, y, x + TILE, y + TILE], outline=GRID, width=2)
                d.rectangle([x, y + TILE, x + TILE, y + TILE + LABEL_H], fill=(20, 20, 24))
                d.text((x + 6, y + TILE + 5), f"{ent}/{mat}/{arch}", fill=TXT, font=fnt)
        out = OUTDIR / f"entity-base-review-{ent}.png"
        sheet.save(out)
        written.append(out)
        print(f"wrote {out}  ({W}x{H}, {ncols}x{nrows} grid)")
    print("\nsheets:", len(written))


if __name__ == "__main__":
    main()
