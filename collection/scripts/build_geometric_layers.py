#!/usr/bin/env python3
"""Author registered geometry layers: witness seam, architecture, corruption.

Sculptural anatomy is not drawn here. These overlays use the locked pixel
anchors in PRODUCTION-VISUAL-SPEC.md so they composite on a shared camera.
"""
from __future__ import annotations

import hashlib
import io
import json
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter
from PIL.PngImagePlugin import PngInfo

from asset_manifest import COLLECTION, ROOT

N = 2048
ANCHORS = {
    "head_center": (1004, 922),
    "crown": (1004, 266),
    "eye_left": (823, 737),
    "eye_right": (1168, 737),
    "chin": (1004, 1311),
    "collar_left": (651, 1454),
    "collar_gap": (1400, 1495),
}


def _png(im: Image.Image) -> bytes:
    info = PngInfo()
    info.add(b"sRGB", b"\x00")
    buf = io.BytesIO()
    im.save(buf, format="PNG", optimize=False, compress_level=6, pnginfo=info)
    return buf.getvalue()


def _zero(im: Image.Image) -> Image.Image:
    r, g, b, a = im.convert("RGBA").split()
    z = Image.new("L", im.size, 0)
    return Image.merge("RGBA", (Image.composite(r, z, a), Image.composite(g, z, a), Image.composite(b, z, a), a))


def _write(path: Path, im: Image.Image) -> None:
    data = _png(_zero(im))
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(data)
    print(path.relative_to(ROOT), hashlib.sha256(data).hexdigest()[:12])


def _bead(d: ImageDraw.ImageDraw, a, b, width=18, fill=(198, 188, 176, 220), edge=(18, 16, 14, 180)):
    d.line([a, b], fill=edge, width=width + 6)
    d.line([a, b], fill=fill, width=width)
    d.line([a, b], fill=(230, 222, 210, 90), width=max(3, width // 4))


def witness_seam() -> Image.Image:
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    y = ANCHORS["eye_left"][1]
    # Two physical ceramic fragments; gap across the facial opening.
    _bead(d, (168, y + 28), (700, y - 8), width=14)
    _bead(d, (1276, y - 12), (1900, y + 30), width=14)
    d.ellipse((686, y - 16, 714, y + 12), fill=(210, 200, 188, 200), outline=(20, 18, 16, 180))
    d.ellipse((1264, y - 20, 1294, y + 10), fill=(210, 200, 188, 200), outline=(20, 18, 16, 180))
    return im.filter(ImageFilter.GaussianBlur(0.7))


def _clear_region(im: Image.Image, drawer) -> Image.Image:
    mask = Image.new("L", (N, N), 0)
    drawer(ImageDraw.Draw(mask))
    r, g, b, a = im.split()
    a = Image.composite(Image.new("L", (N, N), 0), a, mask)
    return Image.merge("RGBA", (r, g, b, a))


def architecture_layer(kind: str, entity: str) -> Image.Image:
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    cx, cy = ANCHORS["head_center"]
    opening = [(1388, 1280), (1680, 1180), (1760, 1600), (1410, 1720)]
    bone = (186, 178, 168, 210)
    graphite = (36, 40, 44, 230)
    shadow = (0, 0, 0, 140)
    if kind == "sealed":
        d.pieslice((cx - 520, cy - 580, cx + 430, cy + 520), 200, 430, fill=graphite)
        im = _clear_region(im, lambda m: m.pieslice((cx - 430, cy - 490, cx + 340, cy + 430), 200, 430, fill=255))
        d = ImageDraw.Draw(im)
        d.polygon([(cx - 420, 1240), (cx - 80, 1460), (cx - 90, 1720), (cx - 460, 1560)], fill=graphite)
        d.arc((cx - 520, cy - 580, cx + 430, cy + 520), 200, 430, fill=bone, width=10)
        d.arc((cx - 430, cy - 490, cx + 340, cy + 430), 200, 430, fill=shadow, width=16)
    elif kind == "inset":
        d.rounded_rectangle((cx - 310, cy - 330, cx + 210, cy + 260), radius=70, fill=graphite)
        im = _clear_region(im, lambda m: m.rounded_rectangle((cx - 230, cy - 250, cx + 130, cy + 180), radius=48, fill=255))
        d = ImageDraw.Draw(im)
        d.rounded_rectangle((cx - 230, cy - 250, cx + 130, cy + 180), radius=48, outline=bone, width=6)
        d.arc((cx - 340, 1180, cx + 200, 1680), 200, 340, fill=shadow, width=22)
    elif kind == "double_plane":
        d.polygon([(cx - 380, cy - 400), (cx + 60, cy - 320), (cx + 20, cy + 300), (cx - 400, cy + 230)], fill=(28, 30, 34, 200))
        d.polygon([(cx - 240, cy - 320), (cx + 260, cy - 230), (cx + 210, cy + 320), (cx - 260, cy + 220)], fill=(48, 50, 54, 140))
        d.line([(cx - 60, cy - 40), (cx + 140, cy + 16)], fill=bone, width=8)
        d.line([(cx - 380, cy - 400), (cx + 60, cy - 320)], fill=bone, width=5)
    elif kind == "suspended_core":
        d.ellipse((cx - 130, cy - 110, cx + 110, cy + 130), fill=graphite)
        im = _clear_region(im, lambda m: m.ellipse((cx - 70, cy - 50, cx + 50, cy + 70), fill=255))
        d = ImageDraw.Draw(im)
        d.ellipse((cx - 130, cy - 110, cx + 110, cy + 130), outline=bone, width=6)
        for start, end in (
            ((cx - 360, 380), (cx - 60, cy - 40)),
            ((cx + 340, 420), (cx + 50, cy - 10)),
            ((cx - 320, 1540), (cx - 40, cy + 80)),
            ((cx + 280, 1520), (cx + 40, cy + 60)),
        ):
            _bead(d, start, end, width=16, fill=graphite, edge=bone)
    else:
        raise ValueError(kind)
    im = _clear_region(im, lambda m: m.polygon(opening, fill=255))
    if entity in ("hollow", "specter"):
        a = im.getchannel("A").point(lambda v: int(v * 0.85))
        im.putalpha(a)
    return im.filter(ImageFilter.GaussianBlur(0.8))


def corruption_layer(kind: str, entity: str) -> Image.Image:
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    cx, cy = ANCHORS["head_center"]
    if kind == "scan_shear":
        y0, y1 = 620, 810
        d.polygon([(240, y0 + 18), (1780, y0 - 22), (1800, y1 - 8), (250, y1 + 28)], fill=(16, 14, 14, 80))
        d.line([(260, y0 + 10), (1760, y0 - 16)], fill=(186, 58, 46, 150), width=6)
        d.line([(270, y1), (1740, y1 + 12)], fill=(186, 58, 46, 90), width=3)
    elif kind == "checksum_burn":
        d.polygon([(cx + 20, cy - 240), (cx + 300, cy - 60), (cx + 220, cy + 190), (cx - 30, cy + 40)], fill=(78, 18, 14, 110))
        d.line([(cx + 20, cy - 240), (cx + 300, cy - 60), (cx + 220, cy + 190)], fill=(196, 62, 44, 180), width=7)
        d.ellipse((cx + 70, cy - 40, cx + 150, cy + 40), fill=(40, 8, 6, 90))
    elif kind == "packet_ghosting":
        d.arc((cx - 340, cy - 380, cx + 280, cy + 320), 200, 430, fill=(170, 178, 188, 110), width=14)
        d.arc((cx - 260, cy - 320, cx + 360, cy + 380), 200, 430, fill=(170, 178, 188, 55), width=8)
    elif kind == "absence":
        d.ellipse((cx - 260, cy - 300, cx + 200, cy + 250), fill=(4, 6, 8, 210))
        im = _clear_region(im, lambda m: m.ellipse((cx - 180, cy - 220, cx + 120, cy + 170), fill=255))
        d = ImageDraw.Draw(im)
        d.ellipse((cx - 260, cy - 300, cx + 200, cy + 250), outline=(22, 24, 26, 220), width=10)
    else:
        raise ValueError(kind)
    if entity == "hollow" and kind == "absence":
        a = im.getchannel("A").point(lambda v: min(255, int(v * 1.1)))
        im.putalpha(a)
    return im.filter(ImageFilter.GaussianBlur(0.7))


def main() -> None:
    plan = json.loads((COLLECTION / "manifests/test-batch-required-assets.json").read_text())
    for asset in plan["assets"]:
        path = ROOT / asset["path"]
        slot = asset["slot"]
        if slot == "witness_seam":
            _write(path, witness_seam())
        elif slot == "architecture":
            kind = next(t.split(".", 1)[1] for t in asset["trait_ids"] if t.startswith("architecture."))
            entity = next((t.split(".", 1)[1] for t in asset["trait_ids"] if t.startswith("entity.")), "human")
            # Filenames encode entity even when trait_ids only list architecture.
            name = Path(asset["path"]).name
            if "entity__" in name:
                entity = name.split("entity__")[1].split("__")[0]
            _write(path, architecture_layer(kind, entity))
        elif slot == "corruption":
            kind = next(t.split(".", 1)[1] for t in asset["trait_ids"] if t.startswith("corruption."))
            name = Path(asset["path"]).name
            entity = name.split("entity__")[1].split("__")[0] if "entity__" in name else "human"
            _write(path, corruption_layer(kind, entity))


if __name__ == "__main__":
    main()
