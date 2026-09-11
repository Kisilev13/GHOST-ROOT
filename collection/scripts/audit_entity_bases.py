#!/usr/bin/env python3
"""Entity-base production QA audit (offline, no network, no secrets).

Authoritative sources:
  - required set: collection/manifests/collection-required-assets.json (slot == entity_base)
  - present set:  collection/assets/layers/40_entity_base/*.png

Produces:
  - collection/manifests/entity-base-sha256.json     (canonical key, sha256, dims, size)
  - collection/reports/entity-base-validation.json    (per-file technical validation)
  - collection/reports/entity-base-validation.md       (human summary)
  - collection/reports/entity-base-near-duplicates.json (exact + perceptual dup analysis)

Pure analysis: reads image bytes only. No RPC, no keys, no chain, no writes outside
collection/manifests and collection/reports.
"""
from __future__ import annotations
import hashlib, json, collections
from pathlib import Path
import numpy as np
from PIL import Image, ImageFile
ImageFile.LOAD_TRUNCATED_IMAGES = False  # force hard failure on truncated PNGs

COLLECTION = Path(__file__).resolve().parents[1]
ROOT = COLLECTION.parent
BASEDIR = COLLECTION / "assets" / "layers" / "40_entity_base"
RESOLVER = COLLECTION / "manifests" / "collection-required-assets.json"
SHA_OUT = COLLECTION / "manifests" / "entity-base-sha256.json"
VAL_JSON = COLLECTION / "reports" / "entity-base-validation.json"
VAL_MD = COLLECTION / "reports" / "entity-base-validation.md"
DUP_OUT = COLLECTION / "reports" / "entity-base-near-duplicates.json"
CANVAS = 2048


def canonical_key(fn: str) -> str:
    p = fn.replace(".png", "").split("__")
    # entity__<e>__material__<m>__architecture__<a>__vNNN
    return f"{p[1]}/{p[3]}/{p[5]}"


def required_entity_bases() -> list[str]:
    d = json.loads(RESOLVER.read_text())
    return sorted(r["path"] for r in d["paths"] if r["slot"] == "entity_base")


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(1 << 20), b""):
            h.update(chunk)
    return h.hexdigest()


def validate_png(path: Path) -> dict:
    """Full technical validation. Returns a record with ok flag + reasons."""
    rec = {"file": path.name, "key": canonical_key(path.name), "issues": []}
    size = path.stat().st_size
    rec["size_bytes"] = size
    if size == 0:
        rec["issues"].append("zero-byte file")
    try:
        with Image.open(path) as im:
            rec["format"] = im.format
            rec["mode"] = im.mode
            rec["width"], rec["height"] = im.size
            rec["animated"] = bool(getattr(im, "n_frames", 1) > 1)
            if im.format != "PNG":
                rec["issues"].append(f"not PNG (format={im.format})")
            if im.size != (CANVAS, CANVAS):
                rec["issues"].append(f"not {CANVAS}x{CANVAS} (got {im.size[0]}x{im.size[1]})")
            if rec["animated"]:
                rec["issues"].append("unexpected animation (n_frames>1)")
            if im.mode not in ("RGBA", "LA"):
                rec["issues"].append(f"no alpha channel (mode={im.mode})")
            im.load()  # force full decode → raises on corrupt chunks
            # alpha sanity: cutout should have some transparent and some opaque pixels
            if im.mode == "RGBA":
                a = np.asarray(im.getchannel("A"))
                opaque = float((a > 10).mean())
                rec["opaque_pct"] = round(opaque * 100, 2)
                if opaque <= 0.0:
                    rec["issues"].append("fully transparent")
                elif opaque >= 0.999:
                    rec["issues"].append("no transparency (opaque_pct~100 — not a cutout)")
    except Exception as e:  # noqa: BLE001 — any decode failure is a validation failure
        rec["issues"].append(f"decode error: {type(e).__name__}: {e}")
    rec["ok"] = len(rec["issues"]) == 0
    return rec


def dhash(path: Path, size: int = 16) -> int:
    """Perceptual difference hash over the composited-on-black RGB, 16x16 -> 256-bit."""
    with Image.open(path) as im:
        rgba = im.convert("RGBA")
        bg = Image.new("RGB", rgba.size, (0, 0, 0))
        bg.paste(rgba, mask=rgba.split()[3])
        g = bg.convert("L").resize((size + 1, size), Image.LANCZOS)
    a = np.asarray(g, dtype=np.int16)
    diff = a[:, 1:] > a[:, :-1]
    bits = 0
    for v in diff.flatten():
        bits = (bits << 1) | int(v)
    return bits


def hamming(a: int, b: int) -> int:
    return bin(a ^ b).count("1")


def main():
    required = required_entity_bases()
    expected_names = {Path(p).name for p in required}
    present_paths = sorted(BASEDIR.glob("*.png"))
    present_names = {p.name for p in present_paths}

    missing = sorted(expected_names - present_names)
    extra = sorted(present_names - expected_names)

    print(f"EXPECTED: {len(expected_names)}")
    print(f"PRESENT: {len(present_names)}")
    print(f"MISSING: {len(missing)}")
    print(f"EXTRA: {len(extra)}")
    if missing:
        print("  missing:", *missing, sep="\n    ")
    if extra:
        print("  extra:", *extra, sep="\n    ")

    # Validate + hash every present file, ordered deterministically.
    val = [validate_png(p) for p in present_paths]
    sha_map = {}
    for p, rec in zip(present_paths, val):
        sha_map[rec["key"]] = {
            "key": rec["key"], "filename": p.name, "sha256": sha256(p),
            "width": rec.get("width"), "height": rec.get("height"),
            "format": rec.get("format"), "size_bytes": rec.get("size_bytes"),
        }
    sha_sorted = dict(sorted(sha_map.items()))

    # Exact duplicates: identical sha256 across different keys.
    by_hash = collections.defaultdict(list)
    for k, v in sha_sorted.items():
        by_hash[v["sha256"]].append(k)
    exact_dups = {h: ks for h, ks in by_hash.items() if len(ks) > 1}

    # Perceptual near-duplicates: dhash hamming distance across all pairs.
    hashes = {p.name: dhash(p) for p in present_paths}
    names = sorted(hashes)
    pairs = []
    for i in range(len(names)):
        for j in range(i + 1, len(names)):
            d = hamming(hashes[names[i]], hashes[names[j]])
            pairs.append((d, names[i], names[j]))
    pairs.sort(key=lambda x: x[0])
    NEAR_THRESHOLD = 24  # <=24/256 bits differ → visually close; flag for human review
    near = [{"distance": d, "a": a, "b": b,
             "a_key": canonical_key(a), "b_key": canonical_key(b),
             "same_entity": a.split("__")[1] == b.split("__")[1]}
            for (d, a, b) in pairs if d <= NEAR_THRESHOLD]

    # Write reports.
    SHA_OUT.parent.mkdir(parents=True, exist_ok=True)
    VAL_JSON.parent.mkdir(parents=True, exist_ok=True)
    SHA_OUT.write_text(json.dumps(sha_sorted, indent=2, sort_keys=True) + "\n")

    validation = {
        "expected": len(expected_names), "present": len(present_names),
        "missing": missing, "extra": extra,
        "canvas": [CANVAS, CANVAS],
        "all_ok": all(r["ok"] for r in val) and not missing and not extra,
        "files": sorted(val, key=lambda r: r["key"]),
    }
    VAL_JSON.write_text(json.dumps(validation, indent=2) + "\n")

    dup_report = {
        "exact_duplicate_groups": exact_dups,
        "exact_duplicate_count": sum(len(v) for v in exact_dups.values()),
        "near_threshold_bits": NEAR_THRESHOLD,
        "hash": "dhash-16x16-256bit (composited on black)",
        "near_duplicate_pairs": near,
        "closest_20": [{"distance": d, "a": a, "b": b} for (d, a, b) in pairs[:20]],
    }
    DUP_OUT.write_text(json.dumps(dup_report, indent=2) + "\n")

    # Markdown summary.
    n_fail = sum(1 for r in val if not r["ok"])
    lines = [
        "# Entity-base technical validation",
        "",
        f"- Expected (resolver): **{len(expected_names)}**",
        f"- Present (filesystem): **{len(present_names)}**",
        f"- Missing: **{len(missing)}**  Extra: **{len(extra)}**",
        f"- Canvas: **{CANVAS}×{CANVAS}** PNG RGBA",
        f"- Technical validation failures: **{n_fail}**",
        f"- Exact duplicate groups: **{len(exact_dups)}**",
        f"- Near-duplicate pairs (≤{NEAR_THRESHOLD}/256 bits): **{len(near)}**",
        f"- Overall: **{'PASS' if validation['all_ok'] and n_fail == 0 else 'ATTENTION REQUIRED'}**",
        "",
        "| # | key | dims | mode | opaque% | size | issues |",
        "| ---: | --- | --- | --- | ---: | ---: | --- |",
    ]
    for i, r in enumerate(sorted(val, key=lambda r: r["key"]), 1):
        lines.append(
            f"| {i} | `{r['key']}` | {r.get('width')}×{r.get('height')} | {r.get('mode')} "
            f"| {r.get('opaque_pct','-')} | {r.get('size_bytes')} | {'; '.join(r['issues']) or 'OK'} |"
        )
    VAL_MD.write_text("\n".join(lines) + "\n")

    print(f"\ntechnical failures: {n_fail}")
    print(f"exact duplicate groups: {len(exact_dups)}")
    print(f"near-duplicate pairs (<= {NEAR_THRESHOLD} bits): {len(near)}")
    if near:
        print("  closest pairs:")
        for r in near[:12]:
            print(f"    d={r['distance']:>3}  {r['a_key']}  <->  {r['b_key']}  (same_entity={r['same_entity']})")
    print(f"\nwrote:\n  {SHA_OUT}\n  {VAL_JSON}\n  {VAL_MD}\n  {DUP_OUT}")
    ok = validation["all_ok"] and n_fail == 0 and len(exact_dups) == 0
    print(f"\nAUDIT {'CLEAN' if ok else 'NEEDS REVIEW'} (exact dups + tech validation + count)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
