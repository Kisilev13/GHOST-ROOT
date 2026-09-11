#!/usr/bin/env python3
"""Fit an authored transparent component to a reviewed full-canvas rectangle.

No pixel-difference extraction: preserve the artist's alpha and straight RGB.
The rectangle and threshold must be recorded with the source provenance.
Import through the production importer before fitting. Not an art approval.
"""
import argparse
import tempfile
from pathlib import Path

from PIL import Image
from import_production_layer import import_layer, _png_bytes


def fit(source, dest, box):
    with tempfile.TemporaryDirectory() as tmp:
        normalized = Path(tmp) / 'normalized.png'
        import_layer(source, normalized)
        im = Image.open(normalized).convert('RGBA')
        alpha = im.getchannel('A')
        # Reject imperceptible floating export residue, not connected paint.
        alpha = alpha.point(lambda x: x if x > 8 else 0)
        im.putalpha(alpha)
        bounds = alpha.getbbox()
        if bounds is None or alpha.getextrema() == (255, 255):
            raise ValueError('Expected nonempty genuinely transparent source')
        x0, y0, x1, y1 = box
        component = im.crop(bounds).resize((x1-x0, y1-y0), Image.Resampling.LANCZOS)
        canvas = Image.new('RGBA', (2048, 2048))
        canvas.alpha_composite(component, (x0, y0))
        # Preserve straight RGB for all nonzero alpha pixels.
        channels = canvas.split()
        visible = channels[3].point(lambda x: 255 if x else 0)
        zero = Image.new('L', canvas.size)
        canvas = Image.merge('RGBA', tuple(Image.composite(c, zero, visible) for c in channels[:3]) + (channels[3],))
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_bytes(_png_bytes(canvas))


if __name__ == '__main__':
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument('source', type=Path)
    ap.add_argument('dest', type=Path)
    ap.add_argument('--box', required=True, help='Reviewed x0,y0,x1,y1 on 2048 canvas')
    args = ap.parse_args()
    fit(args.source, args.dest, tuple(map(int, args.box.split(','))))
