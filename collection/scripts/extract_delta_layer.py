#!/usr/bin/env python3
"""Extract a registered overlay: pixels that changed between a naked base and an edited frame."""
from __future__ import annotations

import argparse
import hashlib
import io
from pathlib import Path

from PIL import Image, ImageChops, ImageDraw, ImageFilter, ImageOps
from PIL.PngImagePlugin import PngInfo

from asset_manifest import ROOT

CANVAS = 2048


def extract_delta(base: Path, changed: Path, dest: Path, threshold: int = 28, region: tuple[int, int, int, int] | None = None) -> dict:
    a = Image.open(base).convert("RGB").resize((CANVAS, CANVAS), Image.Resampling.LANCZOS)
    b = Image.open(changed).convert("RGB").resize((CANVAS, CANVAS), Image.Resampling.LANCZOS)
    diff = ImageChops.difference(a, b).convert("L")
    mask = diff.point(lambda v: 255 if v >= threshold else 0)
    mask = mask.filter(ImageFilter.MaxFilter(3))
    if region:
        band = Image.new("L", (CANVAS, CANVAS), 0)
        ImageDraw.Draw(band).rectangle(region, fill=255)
        mask = ImageChops.multiply(mask, band)
    rgba = b.convert("RGBA")
    rgba.putalpha(mask)
    # Zero RGB under transparent.
    r, g, bch, al = rgba.split()
    z = Image.new("L", rgba.size, 0)
    rgba = Image.merge("RGBA", (Image.composite(r, z, al), Image.composite(g, z, al), Image.composite(bch, z, al), al))
    if al.getbbox() is None:
        raise SystemExit(f"empty delta: {changed}")
    dest.parent.mkdir(parents=True, exist_ok=True)
    info = PngInfo()
    info.add(b"sRGB", b"\x00")
    buf = io.BytesIO()
    rgba.save(buf, format="PNG", optimize=False, compress_level=6, pnginfo=info)
    dest.write_bytes(buf.getvalue())
    hist = al.histogram()
    return {
        "dest": str(dest.resolve().relative_to(ROOT) if dest.resolve().is_relative_to(ROOT) else dest),
        "opaque_pixels": sum(hist[9:]),
        "bbox": list(al.getbbox()),
        "sha256": hashlib.sha256(buf.getvalue()).hexdigest()[:12],
    }


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("base")
    ap.add_argument("changed")
    ap.add_argument("dest")
    ap.add_argument("--threshold", type=int, default=28)
    ap.add_argument("--region", help="x0,y0,x1,y1")
    args = ap.parse_args()
    region = tuple(int(x) for x in args.region.split(",")) if args.region else None
    print(extract_delta(Path(args.base), Path(args.changed), Path(args.dest), args.threshold, region))


if __name__ == "__main__":
    main()
