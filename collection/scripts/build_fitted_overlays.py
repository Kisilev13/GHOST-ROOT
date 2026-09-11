#!/usr/bin/env python3
"""Deterministic fitted-overlay builder (Prototype Pass 3).

Replaces the faint spec-anchor geometric overlays (which float ~160 px off
the sculptural bases) with hardware drawn from MEASURED per-base anchors
(`collection/assets/attachment-anchors.json`: supervised socket readings +
auto neck/shoulder geometry).

Fitting rule: each overlay file is shared by the tokens listed in
CONSUMERS; the drawing uses the AVERAGE anchor across those tokens' bases,
with spans wide enough to sit on-structure for every consumer. Single-base
files are drawn tight. Every number traces to measured anchors; no blind
canvas constants except documented style weights (line widths, alphas).

Palette discipline (PRODUCTION-VISUAL-SPEC): graphite/metal/bone hardware,
vermilion ONLY for checksum_burn. No neon, no glow, no text.

Originals are backed up once to `collection/assets/sources/pass2-orig/`.
Output overwrites the v001 layer files in place (they are
CREATED_UNREVIEWED prototype plates, not validated art) and prints sha256.

Usage:
    collection/.venv/bin/python collection/scripts/build_fitted_overlays.py
"""
from __future__ import annotations

import hashlib
import io
import json
import shutil
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter
from PIL.PngImagePlugin import PngInfo

from asset_manifest import COLLECTION, LAYERS_DIR

N = 2048
ANCHORS = json.loads((COLLECTION / "assets" / "attachment-anchors.json").read_text())["bases"]

# Base keys (attachment-anchors.json) consuming each overlay file.
# Overlay filenames are entity-qualified; several serve two bases.
H_POR_SEALED = "human__material__porcelain__architecture__sealed"
H_BIO_DOUBLE = "human__material__biosynth__architecture__double_plane"
HO_POR_SEALED = "hollow__material__porcelain__architecture__sealed"
HO_CAR_SUSP = "hollow__material__carbon__architecture__suspended_core"
SP_PHA_SEALED = "specter__material__phase_glass__architecture__sealed"
SP_PHA_INSET = "specter__material__phase_glass__architecture__inset"
SP_PHA_SUSP = "specter__material__phase_glass__architecture__suspended_core"
SP_REC_DOUBLE = "specter__material__reconstructed__architecture__double_plane"
SY_REC_INSET = "synthetic__material__reconstructed__architecture__inset"

GRAPHITE = (34, 36, 38, 255)
METAL = (170, 174, 178, 255)
METAL_DIM = (120, 124, 128, 220)
BONE = (200, 192, 180, 255)
VERM = (148, 28, 18, 255)

BACKUP_DIR = COLLECTION / "assets" / "sources" / "pass2-orig"


def _png(im: Image.Image) -> bytes:
    info = PngInfo()
    info.add(b"sRGB", b"\x00")
    buf = io.BytesIO()
    r, g, b, a = im.convert("RGBA").split()
    z = Image.new("L", im.size, 0)
    im = Image.merge("RGBA", (Image.composite(r, z, a), Image.composite(g, z, a),
                              Image.composite(b, z, a), a))
    im.save(buf, format="PNG", optimize=False, compress_level=6, pnginfo=info)
    return buf.getvalue()


def avg(bases: list[str]) -> dict:
    """Mean anchor across consuming bases + derived facial landmarks."""
    els = [ANCHORS[b]["eye_l"] for b in bases]
    ers = [ANCHORS[b]["eye_r"] for b in bases]
    ex_l = sum(p[0] for p in els) / len(els)
    ex_r = sum(p[0] for p in ers) / len(ers)
    ey = sum(p[1] for p in els + ers) / (2 * len(els))
    fcx = sum(ANCHORS[b]["face_cx"] for b in bases) / len(bases)
    neck_y = sum(ANCHORS[b]["neck_y"] for b in bases) / len(bases)
    neck_hw = sum(ANCHORS[b]["neck_hw"] for b in bases) / len(bases)
    crown_y = sum(ANCHORS[b]["crown"] for b in bases) / len(bases)
    ipd = ex_r - ex_l
    vbs = [ANCHORS[b].get("void_bbox") for b in bases if ANCHORS[b].get("void_bbox")]
    if vbs:
        void_bbox = [sum(c[i] for c in vbs) / len(vbs) for i in range(4)]
    else:
        void_bbox = [640, 330, 1250, 1080]
    return {
        "eye_l": (ex_l, ey), "eye_r": (ex_r, ey), "face_cx": fcx,
        "neck_y": neck_y, "neck_hw": neck_hw, "crown_y": crown_y, "ipd": ipd,
        "void_bbox": void_bbox,
        "temple_l": (ex_l - 100, ey - 55), "temple_r": (ex_r + 100, ey - 55),
        "brow_y": ey - 100, "jaw_y": neck_y - 130, "chin_y": neck_y - 55,
    }


def base_alpha(key: str) -> Image.Image:
    """Alpha channel of an entity_base plate (body mask source)."""
    for p in (LAYERS_DIR / "40_entity_base").glob(f"entity__{key}__v001.png"):
        return Image.open(p).convert("RGBA").getchannel("A")
    raise KeyError(key)


def finish(im: Image.Image, rel: str) -> str:
    im = im.filter(ImageFilter.GaussianBlur(0.6))
    data = _png(im)
    dest = LAYERS_DIR / rel
    dest.parent.mkdir(parents=True, exist_ok=True)
    dest.write_bytes(data)
    return hashlib.sha256(data).hexdigest()[:12]


def backup(rel: str) -> None:
    src = LAYERS_DIR / rel
    if not src.is_file():
        return
    dst = BACKUP_DIR / rel
    if dst.is_file():
        return
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(src, dst)


# ---------------------------------------------------------------------------
# 70_architecture — one structural idea per class, silhouette-visible.
# ---------------------------------------------------------------------------

def arch_sealed(a: dict) -> Image.Image:
    """Collar-seat ring + grounding contact shadow + shoulder plinth ticks."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    cx, ny, hw = a["face_cx"], a["neck_y"], a["neck_hw"]
    # Contact shadow: soft dark ellipse under the bust mass.
    sh = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    ImageDraw.Draw(sh).ellipse((cx - hw - 260, ny + 40, cx + hw + 260, ny + 300),
                               fill=(0, 0, 0, 80))
    im = Image.alpha_composite(im, sh.filter(ImageFilter.GaussianBlur(60)))
    d = ImageDraw.Draw(im)
    # Collar-seat ring where the mantle break collar sits.
    d.ellipse((cx - hw - 60, ny - 90, cx + hw + 60, ny + 90),
              outline=METAL_DIM, width=5)
    # Shoulder plinth ticks.
    for sx in (cx - hw - 220, cx + hw + 220):
        d.line((sx, ny + 180, sx, ny + 330), fill=GRAPHITE, width=6)
    return im


def arch_inset(a: dict) -> Image.Image:
    """Recessed frame: rounded rect inset from the facial plane + corner ticks."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    el, er = a["eye_l"], a["eye_r"]
    x0, x1 = el[0] - 150, er[0] + 150
    y0, y1 = a["brow_y"] - 130, a["jaw_y"] + 60
    d.rounded_rectangle((x0, y0, x1, y1), radius=60, outline=METAL_DIM, width=5)
    d.rounded_rectangle((x0 + 26, y0 + 26, x1 - 26, y1 - 26), radius=44,
                        outline=GRAPHITE, width=3)
    for cx, cy, dx, dy in ((x0, y0, 1, 1), (x1, y0, -1, 1), (x0, y1, 1, -1), (x1, y1, -1, -1)):
        d.line((cx, cy, cx + dx * 70, cy), fill=METAL, width=5)
        d.line((cx, cy, cx, cy + dy * 70), fill=METAL, width=5)
    return im


def arch_double(a: dict) -> Image.Image:
    """Second-plane echo: ONE offset facial contour + a single plane slash."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    el, er = a["eye_l"], a["eye_r"]
    cx = a["face_cx"]
    hw = (er[0] - el[0]) / 2 + 130
    y0, y1 = a["brow_y"] - 150, a["jaw_y"] + 40
    # Offset echo contour (the second plane), shifted viewer-right and up.
    d.ellipse((cx - hw + 64, y0 - 44, cx + hw + 64, y1 - 44),
              outline=METAL_DIM, width=4)
    # Single diagonal plane slash across the echo.
    d.line((cx - hw - 40, y1 - 100, cx + hw + 120, y0 + 60), fill=BONE, width=3)
    return im


def arch_suspended(a: dict) -> Image.Image:
    """Suspension rigging: neck-base core slit, shoulder clamps, hanger lines."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    cx, ny, hw = a["face_cx"], a["neck_y"], a["neck_hw"]
    # Core slit glowing cold (no neon: pale graphite, not color).
    d.rounded_rectangle((cx - 26, ny - 30, cx + 26, ny + 150), radius=12,
                        fill=(150, 156, 160, 255), outline=GRAPHITE, width=3)
    for sx in (cx - hw - 120, cx + hw + 120):
        d.line((sx, ny - 260, sx, ny + 120), fill=METAL_DIM, width=5)
        d.rounded_rectangle((sx - 26, ny + 60, sx + 26, ny + 150), radius=10,
                            outline=METAL, width=4)
    return im


ARCH = {
    "architecture__sealed__entity__human__v001.png": ([H_POR_SEALED], arch_sealed),
    "architecture__sealed__entity__hollow__v001.png": ([HO_POR_SEALED], arch_sealed),
    "architecture__sealed__entity__specter__v001.png": ([SP_PHA_SEALED], arch_sealed),
    "architecture__inset__entity__specter__v001.png": ([SP_PHA_INSET], arch_inset),
    "architecture__inset__entity__synthetic__v001.png": ([SY_REC_INSET], arch_inset),
    "architecture__double_plane__entity__human__v001.png": ([H_BIO_DOUBLE], arch_double),
    "architecture__double_plane__entity__specter__v001.png": ([SP_REC_DOUBLE], arch_double),
    "architecture__suspended_core__entity__hollow__v001.png": ([HO_CAR_SUSP], arch_suspended),
    "architecture__suspended_core__entity__specter__v001.png": ([SP_PHA_SUSP], arch_suspended),
}


def witness_seam() -> Image.Image:
    """One interrupted witness slash near the median face center, full height."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    x = 945
    for y0, y1 in ((300, 800), (860, 1250), (1310, 1560)):
        d.line((x, y0, x, y1), fill=BONE, width=3)
    d.line((x + 14, 320, x + 14, 1540), fill=METAL_DIM, width=1)
    return im


# ---------------------------------------------------------------------------
# 60_implant_front — sparse temple/neck hardware rooted at measured temples.
# ---------------------------------------------------------------------------

def implant_antenna(a: dict) -> Image.Image:
    """Temple saddle + single riser into the background (natural crossing)."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    (tx, ty), (ex, ey) = a["temple_l"], (a["eye_l"][0], a["eye_l"][1])
    # Saddle spans both consumers' temples; riser at the averaged temple.
    d.line((tx - 60, ty + 26, tx + 60, ty + 26), fill=GRAPHITE, width=12)
    d.line((tx - 60, ty + 26, tx + 60, ty + 26), fill=METAL, width=4)
    d.line((tx, ty + 20, tx - 30, 200), fill=GRAPHITE, width=9)
    d.line((tx, ty + 20, tx - 30, 200), fill=METAL, width=3)
    d.ellipse((tx - 20, ty + 6, tx + 20, ty + 46), fill=GRAPHITE, outline=METAL, width=3)
    d.ellipse((tx - 30, 185, tx - 30 + 12, 185 + 12), fill=METAL)
    return im


def implant_neural_cable(a: dict) -> Image.Image:
    """Temple port + cable draping down past the shoulder into the field."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    (tx, ty) = a["temple_r"]
    d.ellipse((tx - 24, ty - 24, tx + 24, ty + 24), fill=GRAPHITE, outline=METAL, width=4)
    pts = [(tx, ty + 20), (tx + 150, ty + 320), (tx + 90, ty + 700), (tx + 40, ty + 1050)]
    d.line(pts, fill=GRAPHITE, width=17, joint="curve")
    d.line(pts, fill=METAL_DIM, width=6, joint="curve")
    return im


def implant_spinal_bus(a: dict) -> Image.Image:
    """Twin bus lines down the neck into the torso."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    cx, ny = a["face_cx"], a["neck_y"]
    for dx in (-24, 24):
        d.line((cx + dx, ny - 260, cx + dx, ny + 420), fill=GRAPHITE, width=20)
        d.line((cx + dx, ny - 260, cx + dx, ny + 420), fill=METAL, width=6)
    for i, ry in enumerate((ny - 120, ny + 60, ny + 240)):
        d.line((cx - 60, ry, cx + 60, ry), fill=METAL_DIM, width=4)
    return im


def implant_root_port(a: dict) -> Image.Image:
    """Sub-dermal port seated at the jaw hinge."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    cx = a["face_cx"]
    jy = a["jaw_y"]
    hw = a["ipd"] * 0.52
    d.rounded_rectangle((cx + hw - 90, jy - 40, cx + hw + 20, jy + 70), radius=14,
                        fill=GRAPHITE, outline=METAL, width=4)
    d.ellipse((cx + hw - 55, jy - 5, cx + hw - 15, jy + 35), outline=METAL, width=4)
    return im


def implant_memory_spindle(a: dict) -> Image.Image:
    """Vertical spindle cartridge seated at the left temple."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    (tx, ty) = a["temple_l"]
    d.rounded_rectangle((tx - 20, ty - 80, tx + 20, ty + 100), radius=10,
                        fill=GRAPHITE, outline=METAL, width=3)
    for i in range(4):
        ry = ty - 40 + i * 40
        d.line((tx - 20, ry, tx + 20, ry), fill=METAL_DIM, width=3)
    d.ellipse((tx - 26, ty - 26, tx + 26, ty + 26), fill=METAL_DIM, outline=METAL, width=3)
    return im


IMPLANT = {
    "implant__antenna__entity__human__v001.png": ([H_POR_SEALED, H_BIO_DOUBLE], implant_antenna),
    "implant__antenna__entity__hollow__v001.png": ([HO_POR_SEALED], implant_antenna),
    "implant__neural_cable__entity__human__v001.png": ([H_POR_SEALED], implant_neural_cable),
    "implant__neural_cable__entity__specter__v001.png": ([SP_PHA_SEALED, SP_PHA_INSET], implant_neural_cable),
    "implant__spinal_bus__entity__hollow__v001.png": ([HO_POR_SEALED], implant_spinal_bus),
    "implant__spinal_bus__entity__specter__v001.png": ([SP_REC_DOUBLE], implant_spinal_bus),
    "implant__root_port__entity__hollow__v001.png": ([HO_CAR_SUSP], implant_root_port),
    "implant__root_port__entity__specter__v001.png": ([SP_PHA_SUSP], implant_root_port),
    "implant__memory_spindle__entity__synthetic__v001.png": ([SY_REC_INSET], implant_memory_spindle),
}


# ---------------------------------------------------------------------------
# 20_rear_anatomy — behind-head structure: crown cables + neck bus.
# ---------------------------------------------------------------------------

def rear_crown_bus(a: dict, kinds: tuple[str, ...]) -> Image.Image:
    """Cables over the crown + (for bus kinds) a rear neck plate."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    cx = a["face_cx"]
    crown_y = a.get("crown_y", 300)
    if "antenna" in kinds or "neural_cable" in kinds:
        d.line([(cx - 260, crown_y - 60), (cx - 120, crown_y + 220)], fill=GRAPHITE, width=13)
        d.line([(cx + 260, crown_y - 60), (cx + 120, crown_y + 220)], fill=GRAPHITE, width=13)
        d.line([(cx - 260, crown_y - 60), (cx - 120, crown_y + 220)], fill=METAL_DIM, width=4)
        d.line([(cx + 260, crown_y - 60), (cx + 120, crown_y + 220)], fill=METAL_DIM, width=4)
    if "spinal_bus" in kinds or "root_port" in kinds or "memory_spindle" in kinds:
        ny = a["neck_y"]
        d.polygon([(cx - 70, ny - 60), (cx + 70, ny - 60), (cx + 110, ny + 480),
                   (cx - 110, ny + 480)], fill=GRAPHITE)
        d.line([(cx, ny - 60), (cx, ny + 480)], fill=METAL_DIM, width=5)
    return im


REAR = {
    "entity__hollow__implant__antenna__v001.png": ([HO_POR_SEALED], ("antenna",)),
    "entity__hollow__implant__root_port__v001.png": ([HO_CAR_SUSP], ("root_port",)),
    "entity__hollow__implant__spinal_bus__v001.png": ([HO_POR_SEALED], ("spinal_bus",)),
    "entity__human__implant__antenna__v001.png": ([H_POR_SEALED, H_BIO_DOUBLE], ("antenna",)),
    "entity__human__implant__neural_cable__v001.png": ([H_POR_SEALED], ("neural_cable",)),
    "entity__specter__implant__neural_cable__v001.png": ([SP_PHA_SEALED, SP_PHA_INSET], ("neural_cable",)),
    "entity__specter__implant__root_port__v001.png": ([SP_PHA_SUSP], ("root_port",)),
    "entity__specter__implant__spinal_bus__v001.png": ([SP_REC_DOUBLE], ("spinal_bus",)),
    "entity__synthetic__implant__memory_spindle__v001.png": ([SY_REC_INSET], ("memory_spindle",)),
}


# ---------------------------------------------------------------------------
# 60_interface — face-mounted hardware, fitted to facial thirds.
# ---------------------------------------------------------------------------

def interface_null_mask(a: dict) -> Image.Image:
    """Brow cowl: dark translucent band + metal rim (not a face box)."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    el, er = a["eye_l"], a["eye_r"]
    x0, x1 = el[0] - 130, er[0] + 130
    y0, y1 = a["brow_y"] - 60, a["eye_l"][1] + 30
    d.rounded_rectangle((x0, y0, x1, y1), radius=40, fill=(30, 32, 34, 175))
    d.rounded_rectangle((x0, y0, x1, y1), radius=40, outline=METAL, width=4)
    d.line((x0 + 40, y1 - 14, x1 - 40, y1 - 14), fill=METAL_DIM, width=3)
    return im


def interface_respirator(a: dict) -> Image.Image:
    """Lower-face mask from nasal bridge to chin + twin filter discs."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    cx = a["face_cx"]
    hw = a["ipd"] * 0.52
    y0, y1 = a["eye_l"][1] + 90, a["chin_y"] + 30
    d.rounded_rectangle((cx - hw, y0, cx + hw, y1), radius=44,
                        fill=(32, 34, 36, 235), outline=METAL, width=4)
    for sx in (cx - hw * 0.45, cx + hw * 0.45):
        d.ellipse((sx - 42, (y0 + y1) / 2 - 42, sx + 42, (y0 + y1) / 2 + 42),
                  fill=GRAPHITE, outline=METAL_DIM, width=4)
        d.ellipse((sx - 16, (y0 + y1) / 2 - 16, sx + 16, (y0 + y1) / 2 + 16),
                  outline=METAL_DIM, width=3)
    d.line((cx, y0 + 20, cx, y1 - 20), fill=METAL_DIM, width=3)
    return im


def interface_forensic_plate(a: dict) -> Image.Image:
    """Angular cheek plate on the viewer-left + etched witness line."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    (ex, ey) = a["eye_l"]
    cx = a["face_cx"]
    x0, x1 = ex - 150, cx - 40
    y0, y1 = ey + 40, a["jaw_y"] - 20
    d.polygon([(x0, y0), (x1, y0 + 20), (x1 - 20, y1), (x0 + 30, y1 - 30)],
              fill=(150, 154, 158, 230), outline=GRAPHITE, width=3)
    d.line((x0 + 30, y0 + 40, x1 - 40, y1 - 40), fill=GRAPHITE, width=3)
    for i in range(3):
        ry = y0 + 70 + i * 44
        d.line((x0 + 24, ry, x0 + 90, ry), fill=GRAPHITE, width=3)
    return im


def interface_skeletal(a: dict) -> Image.Image:
    """Rim-mounted jaw arc + struts for void-headed hollow."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    vb = a["void_bbox"]
    x0, y0, x1, y1 = vb
    d.arc((x0 - 30, y1 - 260, x1 + 30, y1 + 120), 20, 160, fill=METAL, width=9)
    for fx in (0.30, 0.50, 0.70):
        sx = x0 + (x1 - x0) * fx
        d.line((sx, y1 - 130, sx, y1 + 20), fill=METAL_DIM, width=6)
    return im


def interface_neural_veil(a: dict) -> Image.Image:
    """Concentric thin arcs following the facial oval."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    cx = a["face_cx"]
    cy = (a["brow_y"] + a["jaw_y"]) / 2
    rx = a["ipd"] / 2 + 170
    ry = (a["jaw_y"] - a["brow_y"]) / 2 + 150
    for i, (w, col) in enumerate(((7, METAL_DIM), (4, BONE), (3, METAL_DIM))):
        d.ellipse((cx - rx - i * 34, cy - ry - i * 34, cx + rx + i * 34, cy + ry + i * 34),
                  outline=col, width=w)
    return im


INTERFACE = {
    "interface__null_mask__entity__human__v001.png": ([H_POR_SEALED], interface_null_mask),
    "interface__respirator__entity__human__v001.png": ([H_BIO_DOUBLE], interface_respirator),
    "interface__forensic_plate__entity__hollow__v001.png": ([HO_POR_SEALED, HO_CAR_SUSP], interface_forensic_plate),
    "interface__forensic_plate__entity__specter__v001.png": ([SP_PHA_SEALED], interface_forensic_plate),
    "interface__forensic_plate__entity__synthetic__v001.png": ([SY_REC_INSET], interface_forensic_plate),
    "interface__skeletal_interface__entity__hollow__v001.png": ([HO_POR_SEALED], interface_skeletal),
    "interface__neural_veil__entity__specter__v001.png": ([SP_REC_DOUBLE], interface_neural_veil),
}


# ---------------------------------------------------------------------------
# 80_corruption — bounded stencils clipped to the body mask of each base.
# ---------------------------------------------------------------------------

def _clip_to_body(im: Image.Image, base_key: str, grow: int = 24) -> Image.Image:
    alpha = base_alpha(base_key)
    if grow > 0:
        alpha = alpha.filter(ImageFilter.MaxFilter(grow * 2 + 1))
    blank = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    return Image.composite(im, blank, alpha)


def corruption_scan_shear(a: dict, base_key: str) -> Image.Image:
    """One displaced chest band: dark slice + bright fault edge."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    cx, ny = a["face_cx"], a["neck_y"]
    y0, y1 = ny + 260, ny + 360
    d.rectangle((cx - 350, y0, cx + 350, y1), fill=(10, 10, 12, 200))
    d.line((cx - 350, y0, cx + 350, y0), fill=BONE, width=4)
    d.line((cx - 350, y1 + 26, cx + 350, y1 + 26), fill=METAL_DIM, width=3)
    return _clip_to_body(im, base_key)


def corruption_packet_ghosting(a: dict, base_key: str) -> Image.Image:
    """One offset reconstruction contour around the head mass."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    from PIL import ImageChops
    alpha = base_alpha(base_key)
    edge = alpha.filter(ImageFilter.MaxFilter(9))
    inner = alpha.filter(ImageFilter.MinFilter(9))
    band = ImageChops.subtract(edge, inner).point(lambda v: 255 if v > 40 else 0)
    band_c = band.crop((0, 300, N, 1500))
    ghost = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    ghost.paste(Image.new("RGBA", (N, 1200), BONE), (0, 300), band_c)
    ghost = ghost.filter(ImageFilter.GaussianBlur(1.2))
    ghost = ghost.transform((N, N), Image.AFFINE, (1, 0, -34, 0, 1, -26))
    return _clip_to_body(ghost, base_key, grow=60)


def corruption_checksum_burn(a: dict, base_key: str) -> Image.Image:
    """Restrained vermilion scorch at the collar crevices + one rim line."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    cx, ny, hw = a["face_cx"], a["neck_y"], a["neck_hw"]
    blob = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    db = ImageDraw.Draw(blob)
    for sx, sy, r in ((cx - hw, ny + 40, 130), (cx + hw, ny + 40, 130), (cx, ny + 200, 90)):
        db.ellipse((sx - r, sy - r, sx + r, sy + r), fill=(120, 22, 14, 95))
    im = Image.alpha_composite(im, blob.filter(ImageFilter.GaussianBlur(40)))
    d = ImageDraw.Draw(im)
    d.arc((cx - hw - 60, ny - 90, cx + hw + 60, ny + 90), 300, 360, fill=VERM, width=4)
    return _clip_to_body(im, base_key)


def corruption_absence(a: dict, base_key: str) -> Image.Image:
    """Deepen the cavity: inner vignette + faint rim lift inside the void.

    Hollow bases carry a measured void_bbox. Faced bases (specter) get the
    vignette from the void COVER's own dark interior, which is guaranteed
    to sit inside the pasted cavity.
    """
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    vb = a.get("void_bbox")
    base_anchors = ANCHORS.get(base_key, {})
    if base_anchors.get("void_bbox"):
        x0, y0, x1, y1 = base_anchors["void_bbox"]
    else:
        cover = Image.open(
            LAYERS_DIR / "50_eyes/eyes__void__entity__specter__v001.png").convert("RGBA")
        lum = cover.convert("L").point(lambda v: 255 if v < 80 else 0)
        mask = Image.composite(lum, Image.new("L", cover.size, 0),
                               cover.getchannel("A").point(lambda v: 255 if v > 128 else 0))
        bb = mask.getbbox()
        assert bb, "void cover has no dark interior"
        x0, y0, x1, y1 = bb[0] + 40, bb[1] + 40, bb[2] - 40, bb[3] - 40
    d = ImageDraw.Draw(im)
    d.ellipse((x0 + 40, y0 + 60, x1 - 40, y1 - 40), fill=(0, 0, 0, 110))
    d.arc((x0 + 18, y0 + 30, x1 - 18, y1 - 30), 200, 340, fill=(190, 184, 172, 130), width=5)
    return _clip_to_body(Image.alpha_composite(
        im, im.filter(ImageFilter.GaussianBlur(18))), base_key, grow=0)


CORRUPTION = {
    "corruption__scan_shear__entity__human__v001.png": ([H_BIO_DOUBLE], corruption_scan_shear, H_BIO_DOUBLE),
    "corruption__scan_shear__entity__specter__v001.png": ([SP_PHA_INSET], corruption_scan_shear, SP_PHA_INSET),
    "corruption__packet_ghosting__entity__hollow__v001.png": ([HO_CAR_SUSP], corruption_packet_ghosting, HO_CAR_SUSP),
    "corruption__checksum_burn__entity__human__v001.png": ([H_BIO_DOUBLE], corruption_checksum_burn, H_BIO_DOUBLE),
    "corruption__absence__entity__hollow__v001.png": ([HO_POR_SEALED], corruption_absence, HO_POR_SEALED),
    "corruption__absence__entity__specter__v001.png": ([SP_PHA_SEALED], corruption_absence, SP_PHA_SEALED),
}


# ---------------------------------------------------------------------------
# 50_eyes — FRACTURED redraws; VOID-human donut fit.
# ---------------------------------------------------------------------------

def eyes_fractured_faced(a: dict) -> Image.Image:
    """Crack across the left socket + luminous split sliver at the right."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    (lx, ly), (rx, ry) = a["eye_l"], a["eye_r"]
    d.line([(lx - 90, ly - 70), (lx - 20, ly - 10), (lx - 60, ly + 50), (lx + 30, ly + 90)],
           fill=BONE, width=4)
    d.line([(lx - 90, ly - 70), (lx - 10, ly - 60)], fill=METAL_DIM, width=3)
    d.ellipse((rx - 52, ry - 34, rx + 52, ry + 34), fill=(12, 12, 14, 255),
              outline=BONE, width=3)
    d.line([(rx - 52, ry - 34), (rx + 52, ry + 34)], fill=(170, 190, 200, 255), width=4)
    d.polygon([(rx + 60, ry - 60), (rx + 130, ry - 20), (rx + 70, ry + 50)],
              fill=(150, 154, 158, 235), outline=BONE, width=2)
    return im


def eyes_fractured_hollow() -> Image.Image:
    """Rim cracks + cold slit + one offset rim fragment, confined to the
    cavity zone SHARED by both consuming hollow voids (porcelain
    [640,330,1250,1080], carbon [666,204,1391,1268]) so every stroke sits
    on-structure for both tokens."""
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    lx, ty, rx, by = 666, 330, 1250, 1080
    cx = (lx + rx) / 2
    d.line([(lx - 26, ty + 120), (lx + 64, ty + 260), (lx + 24, ty + 420)], fill=BONE, width=5)
    d.line([(rx - 40, ty + 180), (rx - 120, ty + 330)], fill=BONE, width=4)
    d.line([(cx - 14, ty + 200), (cx + 14, by - 200)], fill=(150, 170, 180, 200), width=5)
    d.polygon([(lx + 60, ty + 90), (lx + 160, ty + 110), (lx + 100, ty + 210)],
              fill=(150, 154, 158, 235), outline=BONE, width=2)
    return im


EYES = {
    "eyes__fractured__entity__specter__v001.png": ([SP_PHA_SEALED], eyes_fractured_faced),
    "eyes__fractured__entity__hollow__v001.png": ([HO_POR_SEALED, HO_CAR_SUSP], eyes_fractured_hollow),
}


def void_human_fit() -> Image.Image:
    """Fit the void-human socket donuts onto 3333's measured sockets.

    The shipped void-human plate is framed for a different (larger) face, so
    only its two socket rings are reused: each is cropped with padding and
    similarity-fit (uniform scale + translate) onto the true socket centers
    of the human-porcelain-sealed base. No rotation: faces are frontal.
    """
    from PIL import ImageChops
    src = Image.open(
        LAYERS_DIR / "50_eyes/eyes__void__entity__human__v001.png").convert("RGBA")
    dark = src.convert("L").point(lambda v: 255 if v < 80 else 0)
    dark = Image.composite(dark, Image.new("L", src.size, 0),
                           src.getchannel("A").point(lambda v: 255 if v > 128 else 0))
    # Two largest dark masses = the socket donuts (with halos).
    import sys
    sys.path.insert(0, str(Path(__file__).resolve().parent))
    from measure_anchors import _components
    comps = sorted((c for c in _components(dark) if c["n"] > 3000),
                   key=lambda c: c["cx"])
    assert len(comps) >= 2, f"expected 2 donuts, found {len(comps)}"
    base = ANCHORS[H_POR_SEALED]
    dst = [base["eye_l"], base["eye_r"]]
    im = Image.new("RGBA", (N, N), (0, 0, 0, 0))
    for comp, (dx, dy) in zip(comps[:2], dst):
        x0, y0, x1, y1 = comp["bbox"]
        pad = 30
        crop = src.crop((max(0, x0 - pad), max(0, y0 - pad),
                         min(N, x1 + pad), min(N, y1 + pad)))
        w = x1 - x0 + 2 * pad
        scale = 150 / w  # true socket ring outer width ~150px
        new = crop.resize((max(1, round(crop.width * scale)), max(1, round(crop.height * scale))),
                          Image.Resampling.LANCZOS)
        im.alpha_composite(new, (round(dx - new.width / 2), round(dy - new.height / 2)))
    return im


# ---------------------------------------------------------------------------
# main
# ---------------------------------------------------------------------------

def _run_table(table: dict, subdir: str, kind: str) -> None:
    for name, spec in table.items():
        bases = spec[0]
        fn = spec[1]
        extra = spec[2:] if len(spec) > 2 else []
        a = avg(bases)
        backup(f"{subdir}/{name}")
        if kind == "corruption":
            im = fn(a, *extra)
        elif kind == "arch":
            im = fn(a)
        elif kind == "implant":
            im = fn(a)
        elif kind == "rear":
            im = rear_crown_bus(a, fn)
        elif kind == "interface":
            im = fn(a)
        elif kind == "fractured":
            im = fn(a) if fn is eyes_fractured_faced else fn()
        else:
            raise ValueError(kind)
        print(f"{subdir}/{name}", finish(im, f"{subdir}/{name}"))


def main() -> None:
    _run_table(ARCH, "70_architecture", "arch")
    print("--- seam")
    backup("90_witness_seam/witness_seam__v001.png")
    print("90_witness_seam/witness_seam__v001.png", finish(witness_seam(), "90_witness_seam/witness_seam__v001.png"))
    _run_table(IMPLANT, "60_implant_front", "implant")
    _run_table(REAR, "20_rear_anatomy", "rear")
    _run_table(INTERFACE, "60_interface", "interface")
    _run_table(CORRUPTION, "80_corruption", "corruption")
    _run_table(EYES, "50_eyes", "fractured")
    print("--- void-human fit")
    backup("50_eyes/eyes__void__entity__human__v001.png")
    print("50_eyes/eyes__void__entity__human__v001.png",
          finish(void_human_fit(), "50_eyes/eyes__void__entity__human__v001.png"))
    print("KEEP (fits natively): 50_eyes/eyes__void__entity__specter__v001.png")


if __name__ == "__main__":
    main()

