#!/usr/bin/env python3
"""Normalize a generated raster into a compositor layer.

Hosted image tools do not guarantee 2048×2048, sRGB, or straight alpha.
This importer is the only allowed path from those rasters into
collection/assets/layers or collection/assets/scenes.
"""
from __future__ import annotations

import argparse
import hashlib
import io
from pathlib import Path

from PIL import Image
from PIL.PngImagePlugin import PngInfo

from asset_manifest import ROOT

CANVAS = 2048
MAGENTA = (255, 0, 255)


class ImportError_(Exception):
    pass


def _png_bytes(im: Image.Image) -> bytes:
    info = PngInfo()
    info.add(b"sRGB", b"\x00")
    buf = io.BytesIO()
    im.save(buf, format="PNG", optimize=False, compress_level=6, pnginfo=info)
    return buf.getvalue()


def _center_square(im: Image.Image) -> Image.Image:
    w, h = im.size
    side = min(w, h)
    left = (w - side) // 2
    top = (h - side) // 2
    return im.crop((left, top, left + side, top + side))


def knockout_edge_black(im: Image.Image, threshold: int = 18) -> Image.Image:
    """Make the studio field transparent without punching interior cavities."""
    from collections import deque

    rgba = im.convert("RGBA")
    px = rgba.load()
    w, h = rgba.size
    seen = [[False] * w for _ in range(h)]
    q = deque()

    def dark(x, y):
        r, g, b, a = px[x, y]
        return a > 0 and r <= threshold and g <= threshold and b <= threshold

    for x in range(w):
        q.append((x, 0))
        q.append((x, h - 1))
    for y in range(h):
        q.append((0, y))
        q.append((w - 1, y))
    while q:
        x, y = q.popleft()
        if x < 0 or y < 0 or x >= w or y >= h or seen[y][x]:
            continue
        seen[y][x] = True
        if not dark(x, y):
            continue
        px[x, y] = (0, 0, 0, 0)
        q.extend(((x + 1, y), (x - 1, y), (x, y + 1), (x, y - 1)))
    return rgba


def _chroma_key(im: Image.Image, key=MAGENTA, threshold: int = 48) -> Image.Image:
    rgba = im.convert("RGBA")
    px = rgba.load()
    kr, kg, kb = key
    w, h = rgba.size
    for y in range(h):
        for x in range(w):
            r, g, b, a = px[x, y]
            if abs(r - kr) + abs(g - kg) + abs(b - kb) <= threshold * 3:
                px[x, y] = (0, 0, 0, 0)
    return rgba


def _zero_rgb_under_alpha(im: Image.Image) -> Image.Image:
    r, g, b, a = im.convert("RGBA").split()
    zero = Image.new("L", im.size, 0)
    r = Image.composite(r, zero, a)
    g = Image.composite(g, zero, a)
    b = Image.composite(b, zero, a)
    return Image.merge("RGBA", (r, g, b, a))


def import_layer(
    source: Path,
    dest: Path,
    *,
    opaque: bool = False,
    chroma_key: bool = False,
    knockout_black: bool = False,
    min_opaque_pixels: int = 800,
) -> dict:
    with Image.open(source) as src:
        if src.format and src.format != "PNG":
            # Allow JPEG/WebP references; they become PNG on write.
            pass
        im = src.convert("RGBA")
    if min(im.size) < 512:
        raise ImportError_(f"{source}: source too small {im.size}")
    im = _center_square(im)
    if chroma_key:
        im = _chroma_key(im)
    if knockout_black:
        im = knockout_edge_black(im)
    im = im.resize((CANVAS, CANVAS), Image.Resampling.LANCZOS)
    im = _zero_rgb_under_alpha(im)
    alpha = im.getchannel("A")
    bbox = alpha.getbbox()
    if bbox is None:
        raise ImportError_(f"{source}: blank after import")
    hist = alpha.histogram()
    opaque_count = sum(hist[9:])
    if opaque_count < min_opaque_pixels:
        raise ImportError_(f"{source}: too little subject ({opaque_count} opaque pixels)")
    if opaque:
        bg = Image.new("RGB", (CANVAS, CANVAS), (0, 0, 0))
        bg.paste(im.convert("RGB"), mask=alpha)
        im = bg.convert("RGBA")
        if im.getchannel("A").getextrema() != (255, 255):
            raise ImportError_(f"{source}: failed to flatten to opaque")
    dest.parent.mkdir(parents=True, exist_ok=True)
    data = _png_bytes(im)
    dest.write_bytes(data)
    return {
        "source": str(source),
        "dest": str(dest.relative_to(ROOT)) if dest.is_relative_to(ROOT) else str(dest),
        "sha256": hashlib.sha256(data).hexdigest(),
        "opaque_pixels": opaque_count,
        "bbox": list(bbox),
    }


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("source")
    ap.add_argument("dest")
    ap.add_argument("--opaque", action="store_true")
    ap.add_argument("--chroma-key", action="store_true")
    ap.add_argument("--knockout-black", action="store_true")
    args = ap.parse_args()
    record = import_layer(
        Path(args.source),
        Path(args.dest),
        opaque=args.opaque,
        chroma_key=args.chroma_key,
        knockout_black=args.knockout_black,
    )
    print(record)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
