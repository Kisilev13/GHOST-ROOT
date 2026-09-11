#!/usr/bin/env python3
"""Re-author THERMAL eyes as fitted socket sensors (code-drawn, no speckle).

The previous thermal layer was a delta-extract: cartoonish full-red ovals
plus dither speckle around the sockets. In composites it read as a pasted
sticker (token 0166).

This script draws restrained sensor oculi at the TRUE socket positions,
measured from the red pixels in the rendered 0166 composite
(L=(808,576), R=(1116,576) on the 2048 canvas):

- machined dark socket ring that fills the socket,
- recessed sensor bed,
- small deep-red emitter core with a restrained hot spot
  (no full-eye glow, no neon).

Deterministic PIL-only output, straight alpha, sRGB chunk.
Overwrites the v001 file in place; prints sha256.

Usage:
    collection/.venv/bin/python collection/scripts/rebuild_thermal_eyes.py
"""
from __future__ import annotations

import hashlib
import io
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter
from PIL.PngImagePlugin import PngInfo

from asset_manifest import COLLECTION

N = 2048
EYE_L = (808, 576)
EYE_R = (1116, 576)

DEST = COLLECTION / "assets/layers/50_eyes/eyes__thermal__entity__human__v001.png"


def _png(im: Image.Image) -> bytes:
    info = PngInfo()
    info.add(b"sRGB", b"\x00")
    buf = io.BytesIO()
    r, g, b, a = im.convert("RGBA").split()
    z = Image.new("L", im.size, 0)
    im = Image.merge(
        "RGBA",
        (
            Image.composite(r, z, a),
            Image.composite(g, z, a),
            Image.composite(b, z, a),
            a,
        ),
    )
    im.save(buf, format="PNG", optimize=False, compress_level=6, pnginfo=info)
    return buf.getvalue()


def main() -> None:
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    ring = (150, 154, 158, 255)
    housing = (26, 28, 30, 255)
    bed = (10, 10, 12, 255)
    emitter = (150, 18, 12, 255)
    hot = (225, 70, 45, 255)
    for cx, cy in (EYE_L, EYE_R):
        d.ellipse((cx - 78, cy - 58, cx + 78, cy + 58), fill=housing, outline=ring, width=3)
        d.ellipse((cx - 58, cy - 42, cx + 58, cy + 42), fill=bed)
        d.ellipse((cx - 26, cy - 20, cx + 26, cy + 20), fill=emitter)
        d.ellipse((cx - 11, cy - 8, cx + 11, cy + 8), fill=hot)
    im = im.filter(ImageFilter.GaussianBlur(0.6))
    data = _png(im)
    DEST.write_bytes(data)
    print(str(DEST.relative_to(COLLECTION.parents[0])), hashlib.sha256(data).hexdigest()[:12])


if __name__ == "__main__":
    main()
