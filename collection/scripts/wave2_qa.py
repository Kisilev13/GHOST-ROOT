#!/usr/bin/env python3
"""Wave-2 QA: technical validation + SHA manifest + duplicate analysis + recount.
Offline; reads image bytes only. Writes:
  collection/reports/wave2-validation.json / .md
  collection/manifests/wave2-sha256.json
  collection/reports/wave2-duplicates.json
"""
from __future__ import annotations
import hashlib, json, collections
from pathlib import Path
import numpy as np
from PIL import Image, ImageFile
ImageFile.LOAD_TRUNCATED_IMAGES = False

COLLECTION = Path(__file__).resolve().parents[1]
ROOT = COLLECTION.parent
L = COLLECTION / "assets" / "layers"
INV = json.loads((COLLECTION / "reports" / "wave2-inventory.json").read_text())
SLOT_DIR = {"mantle": "30_mantle", "implant_front": "60_implant_front",
            "rear_anatomy": "20_rear_anatomy", "interface": "60_interface", "corruption": "80_corruption"}
CANVAS = 2048


def entity_of(fn):
    return fn.split("entity__")[1].split("__")[0] if "entity__" in fn else None


def kind_of(slot, fn):
    s = fn.replace(".png", "")
    return s.split("implant__")[1].split("__")[0] if slot == "rear_anatomy" else s.split("__")[1]


def validate(p: Path, slot: str) -> dict:
    rec = {"slot": slot, "filename": p.name, "kind": kind_of(slot, p.name),
           "entity": entity_of(p.name), "issues": []}
    rec["size_bytes"] = p.stat().st_size
    if rec["size_bytes"] == 0:
        rec["issues"].append("zero-byte")
    try:
        with Image.open(p) as im:
            rec.update(format=im.format, mode=im.mode, width=im.size[0], height=im.size[1],
                       animated=bool(getattr(im, "n_frames", 1) > 1),
                       text_chunks=sorted(k for k in im.info if isinstance(im.info.get(k), str)))
            if im.format != "PNG": rec["issues"].append(f"not PNG ({im.format})")
            if im.size != (CANVAS, CANVAS): rec["issues"].append(f"dims {im.size}")
            if im.mode != "RGBA": rec["issues"].append(f"mode {im.mode} (need RGBA)")
            if rec["animated"]: rec["issues"].append("animated")
            im.load()
            a = np.asarray(im.getchannel("A"))
            op = float((a > 10).mean())
            rec["opaque_pct"] = round(op * 100, 2)
            rec["bbox"] = im.getchannel("A").getbbox()
            if op <= 0.003: rec["issues"].append("near-empty alpha")
            if op >= 0.985: rec["issues"].append("near-full-frame opaque")
            # transparent pixels must be zeroed RGB (valid RGBA where transparent)
            rgb = np.asarray(im.convert("RGBA"))[..., :3]
            leak = int(((a == 0) & (rgb.max(axis=2) > 8)).sum())
            if leak > 0: rec["issues"].append(f"{leak} transparent px with nonzero RGB")
    except Exception as e:  # noqa: BLE001
        rec["issues"].append(f"decode error: {type(e).__name__}: {e}")
    rec["ok"] = not rec["issues"]
    return rec


def dhash_alpha(p: Path, size=16) -> int:
    """Alpha-aware dHash: crop to alpha bbox, composite on black, then hash — so
    transparency does not dominate the comparison."""
    with Image.open(p) as im:
        rgba = im.convert("RGBA")
        bb = rgba.getchannel("A").getbbox() or (0, 0, rgba.width, rgba.height)
        crop = rgba.crop(bb)
        bg = Image.new("RGB", crop.size, (0, 0, 0))
        bg.paste(crop, mask=crop.split()[3])
        g = bg.convert("L").resize((size + 1, size), Image.LANCZOS)
    d = np.asarray(g, dtype=np.int16)
    bits = 0
    for v in (d[:, 1:] > d[:, :-1]).flatten():
        bits = (bits << 1) | int(v)
    return bits


def hamming(a, b): return bin(a ^ b).count("1")


def main():
    new_by_slot = {s: INV["slots"][s]["missing_files"] for s in SLOT_DIR}
    val = []
    sha = {}
    for slot, dirn in SLOT_DIR.items():
        for fn in new_by_slot[slot]:
            p = L / dirn / fn
            rec = validate(p, slot)
            val.append(rec)
            sha[fn] = {"slot": slot, "trait": rec.get("kind"), "entity": rec.get("entity"),
                       "filename": fn, "sha256": hashlib.sha256(p.read_bytes()).hexdigest(),
                       "width": rec.get("width"), "height": rec.get("height"), "mode": rec.get("mode"),
                       "size_bytes": rec.get("size_bytes")}
    n_fail = sum(1 for r in val if not r["ok"])

    # Exact duplicates: across new set AND across each full slot.
    exact = {}
    for slot, dirn in SLOT_DIR.items():
        by_hash = collections.defaultdict(list)
        for p in sorted((L / dirn).glob("*.png")):
            by_hash[hashlib.sha256(p.read_bytes()).hexdigest()].append(p.name)
        dups = {h: names for h, names in by_hash.items() if len(names) > 1}
        exact[slot] = dups

    # Perceptual (alpha-aware) within each slot; flag close pairs.
    NEAR = 12
    near = {}
    for slot, dirn in SLOT_DIR.items():
        files = sorted((L / dirn).glob("*.png"))
        h = {p.name: dhash_alpha(p) for p in files}
        names = sorted(h)
        pairs = []
        for i in range(len(names)):
            for j in range(i + 1, len(names)):
                dd = hamming(h[names[i]], h[names[j]])
                if dd <= NEAR:
                    pairs.append({"distance": dd, "a": names[i], "b": names[j]})
        near[slot] = sorted(pairs, key=lambda x: x["distance"])

    (COLLECTION / "manifests" / "wave2-sha256.json").write_text(json.dumps(dict(sorted(sha.items())), indent=2) + "\n")
    validation = {"new_assets": len(val), "technical_failures": n_fail,
                  "all_ok": n_fail == 0, "files": sorted(val, key=lambda r: (r["slot"], r["filename"]))}
    (COLLECTION / "reports" / "wave2-validation.json").write_text(json.dumps(validation, indent=2) + "\n")
    dup = {"exact_by_slot": {s: exact[s] for s in exact},
           "exact_total_groups": sum(len(v) for v in exact.values()),
           "perceptual_threshold_bits": NEAR, "perceptual_by_slot": near}
    (COLLECTION / "reports" / "wave2-duplicates.json").write_text(json.dumps(dup, indent=2) + "\n")

    md = ["# Wave-2 technical validation", "",
          f"- New assets validated: **{len(val)}**",
          f"- Technical failures: **{n_fail}**",
          f"- Exact duplicate groups (per full slot): **{dup['exact_total_groups']}**", "",
          "| slot | new | fails | exact-dup groups | perceptual pairs (≤%d) |" % NEAR,
          "| --- | ---: | ---: | ---: | ---: |"]
    for slot in SLOT_DIR:
        nf = sum(1 for r in val if r["slot"] == slot and not r["ok"])
        md.append(f"| {slot} | {len(new_by_slot[slot])} | {nf} | {len(exact[slot])} | {len(near[slot])} |")
    (COLLECTION / "reports" / "wave2-validation.md").write_text("\n".join(md) + "\n")

    print(f"validated {len(val)} new assets; technical failures={n_fail}")
    print(f"exact duplicate groups (per slot): {dup['exact_total_groups']}")
    for slot in SLOT_DIR:
        print(f"  {slot:<14} fails={sum(1 for r in val if r['slot']==slot and not r['ok'])}  "
              f"exact-dups={len(exact[slot])}  perceptual<= {NEAR}: {len(near[slot])}")
    for r in val:
        if not r["ok"]:
            print("  FAIL", r["filename"], r["issues"])


if __name__ == "__main__":
    main()
