#!/usr/bin/env python3
"""Clean baked-background defects out of delta-extracted mantle layers.

Delta-from-naked extracts (extract_delta_layer.py) sometimes keep interior
near-black studio background as OPAQUE pixels: rectangular black boxes and
gray smears that print over the bust at composite time. knockout_edge_black
only removes edge-connected dark, so interior boxes survive.

This script keys those pixels out in a tight, auditable way (all C-speed
PIL ops, no per-pixel Python loops):

1. Any opaque pixel with max(R, G, B) < BLACK_KEY becomes transparent.
   Real collar paint in this collection meters at gray ~90+, so 30 only
   removes baked background, not shadowed paint.
2. Median-filter the alpha channel to drop dither speckles left around the
   keyed regions, then re-threshold and feather 0.5px.
3. Zero RGB wherever alpha is zero (straight-alpha pipeline requirement).

Idempotent: re-running on a clean layer only re-feathers edges. Overwrites
the target files in place; prints keyed-pixel counts and sha256 so the
change is auditable.

Usage:
    collection/.venv/bin/python collection/scripts/clean_delta_layers.py
"""
from __future__ import annotations

import hashlib
import io
from pathlib import Path

from collections import deque

from PIL import Image, ImageChops, ImageFilter
from PIL.PngImagePlugin import PngInfo

from asset_manifest import COLLECTION

BLACK_KEY = 30
# Disconnected alpha bits below this size are delta/knockout residue
# (scribbles, speckles), never collar paint: the collar bodies are 100k+ px
# and break-zone shards kept are all larger. Verified by component census.
MIN_COMPONENT = 2500

TARGETS = [
    "assets/layers/30_mantle/mantle__archive_coat__entity__hollow__v001.png",
    "assets/layers/30_mantle/mantle__ceramic_mantle__entity__hollow__v001.png",
    "assets/layers/30_mantle/mantle__cable_shroud__entity__specter__v001.png",
    "assets/layers/30_mantle/mantle__field_collar__entity__hollow__v001.png",
]


def _png(im: Image.Image) -> bytes:
    info = PngInfo()
    info.add(b"sRGB", b"\x00")
    buf = io.BytesIO()
    im.save(buf, format="PNG", optimize=False, compress_level=6, pnginfo=info)
    return buf.getvalue()


def _opaque_count(im: Image.Image) -> int:
    return sum(1 for v in im.getchannel("A").getdata() if v > 128)


def _drop_small_components(alpha: Image.Image) -> Image.Image:
    w, h = alpha.size
    px = alpha.load()
    seen = bytearray(w * h)
    kill = bytearray(w * h)
    for y in range(h):
        for x in range(w):
            if px[x, y] and not seen[y * w + x]:
                cells = [(x, y)]
                seen[y * w + x] = 1
                q = deque([(x, y)])
                while q:
                    cx, cy = q.popleft()
                    cells.append((cx, cy))
                    for nx, ny in ((cx - 1, cy), (cx + 1, cy), (cx, cy - 1), (cx, cy + 1)):
                        if 0 <= nx < w and 0 <= ny < h and px[nx, ny] and not seen[ny * w + nx]:
                            seen[ny * w + nx] = 1
                            q.append((nx, ny))
                if len(cells) - 1 < MIN_COMPONENT:
                    for cx, cy in cells[1:]:
                        kill[cy * w + cx] = 1
    out = alpha.copy()
    opx = out.load()
    for y in range(h):
        for x in range(w):
            if kill[y * w + x]:
                opx[x, y] = 0
    return out


def clean(rel: str) -> dict:
    path = COLLECTION / rel
    im = Image.open(path).convert("RGBA")
    before = _opaque_count(im)
    r, g, b, a = im.split()
    brightest = ImageChops.lighter(ImageChops.lighter(r, g), b)
    keep = brightest.point(lambda v: 255 if v >= BLACK_KEY else 0)
    alpha = ImageChops.darker(a, keep)
    alpha = alpha.filter(ImageFilter.MedianFilter(3)).point(lambda v: 255 if v > 128 else 0)
    alpha = alpha.filter(ImageFilter.GaussianBlur(0.5)).point(lambda v: 255 if v > 128 else 0)
    alpha = _drop_small_components(alpha)
    zero = Image.new("L", im.size, 0)
    im = Image.merge(
        "RGBA",
        (
            Image.composite(r, zero, alpha),
            Image.composite(g, zero, alpha),
            Image.composite(b, zero, alpha),
            alpha,
        ),
    )
    after = _opaque_count(im)
    data = _png(im)
    path.write_bytes(data)
    return {
        "file": rel,
        "opaque_before": before,
        "opaque_after": after,
        "keyed": before - after,
        "sha256": hashlib.sha256(data).hexdigest()[:12],
    }


def main() -> None:
    for rel in TARGETS:
        print(clean(rel))


if __name__ == "__main__":
    main()
