#!/usr/bin/env python3
"""Composite knockout figures onto archive backgrounds for authored scenes."""
from __future__ import annotations

import hashlib
import io
from pathlib import Path

from PIL import Image
from PIL.PngImagePlugin import PngInfo

from asset_manifest import COLLECTION, ROOT

N = 2048


def _png(im: Image.Image) -> bytes:
    info = PngInfo()
    info.add(b"sRGB", b"\x00")
    buf = io.BytesIO()
    im.convert("RGBA").save(buf, format="PNG", optimize=False, compress_level=6, pnginfo=info)
    return buf.getvalue()


def compose(bg: Path, figure: Path, dest: Path) -> None:
    base = Image.open(bg).convert("RGBA").resize((N, N), Image.Resampling.LANCZOS)
    fig = Image.open(figure).convert("RGBA").resize((N, N), Image.Resampling.LANCZOS)
    out = Image.alpha_composite(base, fig)
    if out.getchannel("A").getextrema() != (255, 255):
        flat = Image.new("RGBA", (N, N), (0, 0, 0, 255))
        out = Image.alpha_composite(flat, out)
    dest.parent.mkdir(parents=True, exist_ok=True)
    data = _png(out)
    dest.write_bytes(data)
    print(dest.relative_to(ROOT), hashlib.sha256(data).hexdigest()[:12])


def main() -> None:
    layers = COLLECTION / "assets/layers/00_background"
    refs = COLLECTION / "assets/references"
    scenes = COLLECTION / "assets/scenes"
    compose(layers / "background__evidence_void__v001.png", refs / "genesis-0001-figure.png",
            scenes / "genesis/0001__v001.png")
    compose(layers / "background__cold_storage__v001.png", refs / "genesis-0002-figure.png",
            scenes / "genesis/0002__v001.png")
    compose(layers / "background__relay_well__v001.png", refs / "genesis-0003-figure.png",
            scenes / "genesis/0003__v001.png")
    # Legendary prototype scenes: class masters on reserved environments.
    compose(layers / "background__rack_shadow__v001.png", refs / "entity-human-cutout-v001.png",
            scenes / "legendary/0303__v001.png")
    compose(layers / "background__relay_well__v001.png", refs / "entity-synthetic-cutout-v001.png",
            scenes / "legendary/0803__v001.png")
    compose(layers / "background__rack_shadow__v001.png", refs / "entity-hollow-cutout-v001.png",
            scenes / "legendary/1503__v001.png")
    # 2803 THE WITNESS uses the collarless naked framing on the same void field:
    # at thumbnail 1503/2803 read as duplicates when both wear the white collar.
    compose(layers / "background__evidence_void__v001.png", refs / "entity-hollow-naked-cutout-v001.png",
            scenes / "legendary/2803__v001.png")


if __name__ == "__main__":
    main()
