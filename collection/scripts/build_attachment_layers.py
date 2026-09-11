#!/usr/bin/env python3
"""Sparse registered implant, interface, and rear-anatomy overlays."""
from __future__ import annotations

import hashlib
import io
import json
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter
from PIL.PngImagePlugin import PngInfo

from asset_manifest import COLLECTION, ROOT

N = 2048
EYE_L, EYE_R = (823, 737), (1168, 737)
TEMPLE_L, TEMPLE_R = (645, 675), (1393, 690)
CHIN = (1004, 1311)
NECK = (1024, 1430)


def _png(im: Image.Image) -> bytes:
    info = PngInfo()
    info.add(b"sRGB", b"\x00")
    buf = io.BytesIO()
    r, g, b, a = im.convert("RGBA").split()
    z = Image.new("L", im.size, 0)
    im = Image.merge("RGBA", (Image.composite(r, z, a), Image.composite(g, z, a), Image.composite(b, z, a), a))
    im.save(buf, format="PNG", optimize=False, compress_level=6, pnginfo=info)
    return buf.getvalue()


def _write(path: Path, im: Image.Image) -> None:
    data = _png(im.filter(ImageFilter.GaussianBlur(0.6)))
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(data)
    print(path.relative_to(ROOT), hashlib.sha256(data).hexdigest()[:12])


def implant(kind: str) -> Image.Image:
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    metal = (168, 172, 176, 220)
    dark = (28, 30, 32, 230)
    if kind == "antenna":
        d.line([TEMPLE_L, (TEMPLE_L[0] - 40, 220)], fill=dark, width=10)
        d.line([TEMPLE_L, (TEMPLE_L[0] - 40, 220)], fill=metal, width=4)
        d.ellipse((TEMPLE_L[0] - 22, TEMPLE_L[1] - 22, TEMPLE_L[0] + 18, TEMPLE_L[1] + 18), fill=dark, outline=metal)
    elif kind == "neural_cable":
        d.line([TEMPLE_R, (TEMPLE_R[0] + 180, 980), (TEMPLE_R[0] + 120, 1500)], fill=dark, width=16)
        d.line([TEMPLE_R, (TEMPLE_R[0] + 180, 980), (TEMPLE_R[0] + 120, 1500)], fill=metal, width=6)
        d.ellipse((TEMPLE_R[0] - 24, TEMPLE_R[1] - 24, TEMPLE_R[0] + 24, TEMPLE_R[1] + 24), fill=dark, outline=metal)
    elif kind == "spinal_bus":
        d.line([(NECK[0] - 18, 1180), (NECK[0] - 18, 1980)], fill=dark, width=22)
        d.line([(NECK[0] + 18, 1180), (NECK[0] + 18, 1980)], fill=dark, width=22)
        d.line([(NECK[0] - 18, 1180), (NECK[0] - 18, 1980)], fill=metal, width=6)
        d.line([(NECK[0] + 18, 1180), (NECK[0] + 18, 1980)], fill=metal, width=6)
    elif kind == "root_port":
        box = (TEMPLE_R[0] - 40, TEMPLE_R[1] + 80, TEMPLE_R[0] + 70, TEMPLE_R[1] + 190)
        d.rounded_rectangle(box, radius=12, fill=dark, outline=metal, width=4)
        d.ellipse((box[0] + 28, box[1] + 28, box[2] - 28, box[3] - 28), outline=metal, width=5)
    elif kind == "memory_spindle":
        d.rounded_rectangle((TEMPLE_L[0] - 18, TEMPLE_L[1] - 70, TEMPLE_L[0] + 18, TEMPLE_L[1] + 90), radius=8, fill=dark, outline=metal, width=3)
        d.ellipse((TEMPLE_L[0] - 28, TEMPLE_L[1] - 28, TEMPLE_L[0] + 28, TEMPLE_L[1] + 28), fill=metal)
    else:
        raise ValueError(kind)
    return im


def interface(kind: str) -> Image.Image:
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    metal = (176, 178, 180, 210)
    dark = (36, 38, 40, 200)
    bone = (198, 190, 178, 180)
    if kind == "forensic_plate":
        d.polygon([(EYE_L[0] - 90, EYE_L[1] - 40), (EYE_L[0] + 70, EYE_L[1] - 30),
                   (EYE_L[0] + 50, CHIN[1] - 80), (EYE_L[0] - 110, CHIN[1] - 60)], fill=metal, outline=dark)
    elif kind == "neural_veil":
        for i in range(8):
            y = 620 + i * 70
            d.arc((640, y - 180, 1400, y + 180), 200, 340, fill=bone, width=3)
    elif kind == "null_mask":
        d.rounded_rectangle((760, 640, 1280, 1180), radius=80, fill=dark)
        d.rounded_rectangle((760, 640, 1280, 1180), radius=80, outline=metal, width=5)
    elif kind == "respirator":
        d.rounded_rectangle((820, 980, 1220, 1280), radius=40, fill=dark, outline=metal, width=5)
        d.ellipse((860, 1020, 980, 1140), outline=metal, width=4)
        d.ellipse((1060, 1020, 1180, 1140), outline=metal, width=4)
    elif kind == "skeletal_interface":
        d.arc((700, 620, 1340, 1260), 200, 430, fill=metal, width=8)
        d.line([(760, 900), (1280, 900)], fill=metal, width=4)
        d.line([(820, 1040), (1220, 1040)], fill=metal, width=4)
    else:
        raise ValueError(kind)
    return im


def rear(kind: str) -> Image.Image:
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    dark = (22, 24, 26, 210)
    metal = (140, 144, 148, 180)
    # Rear sits behind the head: cables/ports around the crown and neck, not covering the face.
    if kind in ("antenna", "neural_cable"):
        d.line([(780, 240), (900, 520)], fill=dark, width=12)
        d.line([(1260, 240), (1140, 520)], fill=dark, width=12)
        d.line([(780, 240), (900, 520)], fill=metal, width=4)
        d.line([(1260, 240), (1140, 520)], fill=metal, width=4)
    elif kind == "spinal_bus":
        d.polygon([(960, 1480), (1080, 1480), (1120, 2048), (920, 2048)], fill=dark)
        d.line([(1000, 1480), (1000, 2048)], fill=metal, width=5)
    elif kind in ("root_port", "memory_spindle"):
        d.rounded_rectangle((1180, 1480, 1320, 1660), radius=16, fill=dark, outline=metal, width=4)
    else:
        raise ValueError(kind)
    return im


def main() -> None:
    plan = json.loads((COLLECTION / "manifests/test-batch-required-assets.json").read_text())
    for asset in plan["assets"]:
        slot = asset["slot"]
        path = ROOT / asset["path"]
        if slot == "implant_front":
            kind = next(t.split(".", 1)[1] for t in asset["trait_ids"] if t.startswith("implant."))
            _write(path, implant(kind))
        elif slot == "interface":
            kind = next(t.split(".", 1)[1] for t in asset["trait_ids"] if t.startswith("interface."))
            _write(path, interface(kind))
        elif slot == "rear_anatomy":
            kind = next(t.split(".", 1)[1] for t in asset["trait_ids"] if t.startswith("implant."))
            _write(path, rear(kind))


if __name__ == "__main__":
    main()
