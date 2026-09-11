#!/usr/bin/env python3
"""Build the test-batch contact sheet and a detail sheet from the rendered 20.

Labels are drawn only on the sheet margins, never composited onto token art.
Deterministic: same renders in -> same sheet bytes out. Run after rendering:
    collection/.venv/bin/python3 collection/scripts/build_review_sheets.py
"""
from __future__ import annotations

import json
from pathlib import Path

from PIL import Image, ImageDraw

COLLECTION = Path(__file__).resolve().parents[1]
TB = COLLECTION / "assets" / "test-batch"
REPORTS = COLLECTION / "reports"
SEL = json.loads((COLLECTION / "manifests" / "test-batch-selection.json").read_text())
IDS = [r["token_id"] for r in SEL["selected"]]

BG = (16, 16, 18)
FG = (222, 222, 222)


def contact_sheet() -> None:
    cols, tile, pad = 5, 320, 26
    rows = (len(IDS) + cols - 1) // cols
    sheet = Image.new("RGB", (tile * cols, (tile + pad) * rows), BG)
    d = ImageDraw.Draw(sheet)
    for i, t in enumerate(IDS):
        im = Image.open(TB / f"{t:04d}.png").convert("RGB").resize((tile, tile), Image.LANCZOS)
        x, y = (i % cols) * tile, (i // cols) * (tile + pad)
        sheet.paste(im, (x, y))
        d.text((x + 6, y + tile + 7), f"GHOST//{t:04d}", fill=FG)
    sheet.save(REPORTS / "test-batch-contact-sheet.png")


# Detail crops: (token, label, normalized box x0,y0,x1,y1 on the 2048 canvas).
DETAILS = [
    (1, "0001 eyes / biometric", (0.34, 0.26, 0.66, 0.42)),
    (10, "0010 collar (re-authored)", (0.28, 0.42, 0.78, 0.72)),
    (112, "0112 void eyes", (0.30, 0.28, 0.70, 0.46)),
    (166, "0166 double-plane + thermal", (0.30, 0.18, 0.74, 0.44)),
    (303, "0303 legendary halo/crown", (0.05, 0.02, 0.70, 0.48)),
    (803, "0803 legendary broken shell", (0.28, 0.05, 0.86, 0.52)),
    (1503, "1503 legendary (UNAUTHORED)", (0.20, 0.10, 0.80, 0.62)),
    (2803, "2803 legendary (UNAUTHORED)", (0.24, 0.06, 0.78, 0.52)),
    (49, "0049 specter face", (0.24, 0.10, 0.72, 0.46)),
    (366, "0366 specter face (≈0049)", (0.24, 0.10, 0.72, 0.46)),
    (1842, "1842 cable-shroud shoulders", (0.18, 0.44, 0.90, 0.82)),
    (3333, "3333 cable-shroud shoulders", (0.18, 0.44, 0.90, 0.82)),
]


def detail_sheet() -> None:
    cols, tile, pad = 4, 360, 30
    rows = (len(DETAILS) + cols - 1) // cols
    sheet = Image.new("RGB", (tile * cols, (tile + pad) * rows), BG)
    d = ImageDraw.Draw(sheet)
    for i, (t, label, box) in enumerate(DETAILS):
        im = Image.open(TB / f"{t:04d}.png").convert("RGB")
        w, h = im.size
        crop = im.crop((int(box[0] * w), int(box[1] * h), int(box[2] * w), int(box[3] * h)))
        crop = crop.resize((tile, tile), Image.LANCZOS)
        x, y = (i % cols) * tile, (i // cols) * (tile + pad)
        sheet.paste(crop, (x, y))
        d.text((x + 6, y + tile + 8), label, fill=FG)
    sheet.save(REPORTS / "test-batch-detail-sheet.png")


if __name__ == "__main__":
    contact_sheet()
    detail_sheet()
    print("wrote reports/test-batch-contact-sheet.png and reports/test-batch-detail-sheet.png")
