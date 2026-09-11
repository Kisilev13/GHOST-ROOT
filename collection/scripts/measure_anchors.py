#!/usr/bin/env python3
"""Measure true composition anchors from approved entity_base plates.

The sculptural bases were framed per-piece, so spec-canvas anchors
(eyes y=737 etc.) do not land on their sockets. This script measures, per
base file, the anchors the fitted-overlay builder consumes:

- eye_l / eye_r : socket centers (dark-cluster detection on faced bases;
  void-opening geometry on hollow void heads)
- face_cx       : face horizontal center
- crown         : top of head (alpha bbox)
- neck_y / neck_hw : neck constriction row (narrowest opaque run)
- shoulder_y / shoulder_hw : widest opaque row in lower third
- void_bbox     : bounding box of the facial void opening (hollow only)

Heuristics are deterministic; every value lands in
collection/assets/attachment-anchors.json with its method. A crosshair
montage verifier is provided by --verify (writes
collection/reports/anchor-verification.png).

Usage:
    collection/.venv/bin/python collection/scripts/measure_anchors.py
    collection/.venv/bin/python collection/scripts/measure_anchors.py --verify
"""
from __future__ import annotations

import json
import sys
from collections import deque
from pathlib import Path

from PIL import Image, ImageDraw

from asset_manifest import COLLECTION, LAYERS_DIR

N = 2048
BASES = sorted((LAYERS_DIR / "40_entity_base").glob("*.png"))
OUT = COLLECTION / "assets" / "attachment-anchors.json"


def _alpha_rows(im: Image.Image) -> list[tuple[int, int]]:
    a = im.getchannel("A")
    px = a.load()
    runs = []
    for y in range(N):
        x0 = x1 = -1
        for x in range(N):
            if px[x, y] > 128:
                if x0 < 0:
                    x0 = x
                x1 = x
        runs.append((x0, x1))
    return runs


def _components(dark) -> list[dict]:
    """Connected components of a binary PIL image (>0). Returns dicts."""
    px = dark.load()
    w, h = dark.size
    seen = bytearray(w * h)
    out = []
    for y in range(h):
        for x in range(w):
            if px[x, y] and not seen[y * w + x]:
                n = 0
                x0 = y0 = 10**9
                x1 = y1 = -1
                q = deque([(x, y)])
                seen[y * w + x] = 1
                while q:
                    cx, cy = q.popleft()
                    n += 1
                    if cx < x0:
                        x0 = cx
                    if cx > x1:
                        x1 = cx
                    if cy < y0:
                        y0 = cy
                    if cy > y1:
                        y1 = cy
                    for nx, ny in ((cx - 1, cy), (cx + 1, cy), (cx, cy - 1), (cx, cy + 1)):
                        if 0 <= nx < w and 0 <= ny < h and px[nx, ny] and not seen[ny * w + nx]:
                            seen[ny * w + nx] = 1
                            q.append((nx, ny))
                out.append({"n": n, "bbox": [x0, y0, x1, y1],
                            "cx": (x0 + x1) / 2, "cy": (y0 + y1) / 2})
    return out


def measure(path: Path) -> dict:
    im = Image.open(path).convert("RGBA")
    rgb = im.convert("RGB")
    runs = _alpha_rows(im)
    present = [(y, x0, x1) for y, (x0, x1) in enumerate(runs) if x0 >= 0]
    crown = present[0][0]
    bottom = present[-1][0]
    # Neck: narrowest opaque run between 45% and 80% height, center-constrained.
    neck_y, neck_hw = None, None
    best = 10**9
    for y, x0, x1 in present:
        if int(N * 0.45) <= y <= int(N * 0.80):
            cx = (x0 + x1) / 2
            if abs(cx - N / 2) < N * 0.12 and (x1 - x0) < best:
                best = x1 - x0
                neck_y, neck_hw = y, (x1 - x0) / 2
    # Shoulders: widest run in lower third.
    shoulder_y, shoulder_hw = None, None
    best = -1
    for y, x0, x1 in present:
        if y >= int(N * 0.66) and (x1 - x0) > best:
            best = x1 - x0
            shoulder_y, shoulder_hw = y, (x1 - x0) / 2
    # Dark clusters: sockets / void openings. Masked by alpha: transparent
    # studio surround flattens to black and must not count as darkness.
    # Hollow plates carry real void cavities (generous threshold, tall band);
    # faced plates carry small near-black sockets (tight threshold, eye strip).
    is_hollow = path.name.startswith("entity__hollow__")
    thresh = 60 if is_hollow else 45
    y0, y1 = (0.10, 0.62) if is_hollow else (0.16, 0.40)
    lum = rgb.convert("L")
    alp = im.getchannel("A")
    dark = Image.composite(
        lum.point(lambda v: 255 if v < thresh else 0),
        Image.new("L", (N, N), 0),
        alp.point(lambda v: 255 if v > 128 else 0),
    ).crop((0, int(N * y0), N, int(N * y1)))
    y_off = int(N * y0)
    comps = [c for c in _components(dark) if c["n"] >= 800]
    comps.sort(key=lambda c: -c["n"])
    rec = {"crown": crown, "bottom": bottom, "neck_y": neck_y,
           "neck_hw": neck_hw, "shoulder_y": shoulder_y,
           "shoulder_hw": shoulder_hw, "method": {}}
    if is_hollow:
        if not comps:
            rec["method"]["eyes"] = "none-found"
            return rec
        big = comps[0]
        bw = big["bbox"][2] - big["bbox"][0]
        # Dominant cavity: hollow void head. Eye line = cavity center.
        rec["void_bbox"] = [big["bbox"][0], big["bbox"][1] + y_off,
                            big["bbox"][2], big["bbox"][3] + y_off]
        vcx = big["cx"]
        vcy = big["cy"] + y_off
        rec["eye_l"] = [round(vcx - 0.21 * bw), round(vcy)]
        rec["eye_r"] = [round(vcx + 0.21 * bw), round(vcy)]
        rec["face_cx"] = round(vcx)
        rec["method"]["eyes"] = "void-cavity-geometry"
        return rec
    # Faced base: biggest dark cluster each side of canvas center = sockets.
    left = [c for c in comps if c["cx"] < N / 2]
    right = [c for c in comps if c["cx"] >= N / 2]
    if left and right:
        l = max(left, key=lambda c: c["n"])
        r = max(right, key=lambda c: c["n"])
        rec["eye_l"] = [round(l["cx"]), round(l["cy"] + y_off)]
        rec["eye_r"] = [round(r["cx"]), round(r["cy"] + y_off)]
        rec["face_cx"] = round((l["cx"] + r["cx"]) / 2)
        rec["method"]["eyes"] = "dark-socket-clusters"
    else:
        rec["method"]["eyes"] = "no-pair-found"
    return rec


def main() -> None:
    data = {"generator": "collection/scripts/measure_anchors.py",
            "canvas": N, "bases": {}}
    for path in BASES:
        key = path.name.replace("entity__", "").replace("__v001.png", "")
        data["bases"][key] = measure(path)
        print(key, data["bases"][key].get("eye_l"), data["bases"][key].get("eye_r"),
              data["bases"][key]["method"])
    OUT.write_text(json.dumps(data, indent=2))
    print("wrote", OUT.relative_to(COLLECTION.parents[0]))
    if "--verify" in sys.argv:
        sheet = Image.new("RGB", (3 * 682, 3 * 682), (20, 20, 20))
        d = ImageDraw.Draw(sheet)
        for i, path in enumerate(BASES):
            im = Image.open(path).convert("RGB").resize((682, 682), Image.Resampling.LANCZOS)
            x0, y0 = (i % 3) * 682, (i // 3) * 682
            sheet.paste(im, (x0, y0))
            key = path.name.replace("entity__", "").replace("__v001.png", "")
            a = data["bases"][key]
            s = 682 / N
            for tag, color in (("eye_l", (255, 80, 60)), ("eye_r", (255, 80, 60))):
                if tag in a:
                    ex, ey = a[tag][0] * s + x0, a[tag][1] * s + y0
                    d.ellipse((ex - 12, ey - 12, ex + 12, ey + 12), outline=color, width=3)
                    d.line((ex - 20, ey, ex + 20, ey), fill=color, width=2)
                    d.line((ex, ey - 20, ex, ey + 20), fill=color, width=2)
            if a.get("neck_y"):
                ny = a["neck_y"] * s + y0
                d.line((x0 + 40, ny, x0 + 642, ny), fill=(80, 200, 255), width=2)
            if a.get("void_bbox"):
                vb = a["void_bbox"]
                d.rectangle((vb[0] * s + x0, vb[1] * s + y0, vb[2] * s + x0, vb[3] * s + y0),
                            outline=(120, 255, 120), width=2)
            d.text((x0 + 8, y0 + 660), key[:44], fill=(220, 220, 220))
        out = COLLECTION / "reports" / "anchor-verification.png"
        sheet.save(out)
        print("wrote", out.relative_to(COLLECTION.parents[0]))


if __name__ == "__main__":
    main()
