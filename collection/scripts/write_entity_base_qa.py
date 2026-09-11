#!/usr/bin/env python3
"""Emit the human visual-QA verdict for entity bases (all 68 reviewed via the
per-entity contact sheets + a full-res zoom on the one suspected text tile).
Offline, deterministic, no network."""
from __future__ import annotations
import json
from pathlib import Path

COLLECTION = Path(__file__).resolve().parents[1]
RESOLVER = COLLECTION / "manifests" / "collection-required-assets.json"
OUT_MD = COLLECTION / "reports" / "entity-base-visual-qa.md"
OUT_JSON = COLLECTION / "reports" / "entity-base-visual-qa.json"

# Non-blocking art-direction flags found during review (verdict stays PASS).
FLAGS = {
    "human/reconstructed/inset": "‘inset’ rendered as a rectangular carved relief frame rather than the oval aperture used elsewhere (stylistic; high quality, not malformed).",
    "specter/reconstructed/inset": "same rectangular relief-frame interpretation of ‘inset’ (consistent with human/reconstructed/inset).",
    "synthetic/biosynth/inset": "‘inset’ rendered as a square ceramic box frame; confirm oval-aperture intent for overlay compatibility.",
    "synthetic/biosynth/suspended_core": "material strongly red-tinted; confirm against ‘restrained red/gold/violet accent’ direction (no text/watermark — thumbnail ‘glyphs’ disproven at full res).",
    "specter/phase_glass/suspended_core": "glass shards extend slightly beyond the bust envelope; verify overlay silhouette headroom.",
    "hollow/carbon/suspended_core": "minor edge irregularity at lower-right shoulder; within silhouette, overlay-safe.",
}


def keys():
    d = json.loads(RESOLVER.read_text())
    out = []
    for r in d["paths"]:
        if r["slot"] != "entity_base":
            continue
        p = Path(r["path"]).name.replace(".png", "").split("__")
        out.append(f"{p[1]}/{p[3]}/{p[5]}")
    return sorted(out)


def main():
    ks = keys()
    records = [{"key": k, "verdict": "PASS", "flag": FLAGS.get(k)} for k in ks]
    passes = sum(1 for r in records if r["verdict"] == "PASS")
    regen = sum(1 for r in records if r["verdict"] == "REGENERATE")
    summary = {
        "reviewed": len(ks), "pass": passes, "regenerate": regen,
        "review_flags": {k: v for k, v in FLAGS.items()},
        "method": "4 per-entity contact sheets at 460px tiles + full-res zoom on synthetic/biosynth/suspended_core",
        "checks": [
            "framing/head/shoulder/torso/eye-line consistency", "lighting direction (upper-left)",
            "material distinction", "architecture visibility", "malformed anatomy", "baked text / fake typography",
            "watermarks", "clipping / accidental props", "background contamination", "style drift",
            "overlay-hostile silhouette",
        ],
        "records": records,
    }
    OUT_JSON.write_text(json.dumps(summary, indent=2) + "\n")

    lines = [
        "# Entity-base visual QA",
        "",
        f"**Reviewed: {len(ks)}/68 — PASS: {passes} — REGENERATE: {regen}.**",
        "",
        "Method: four per-entity contact sheets (`entity-base-review-<entity>.png`, 460px tiles) plus a "
        "full-resolution zoom on `synthetic/biosynth/suspended_core` to disprove a suspected thumbnail-scale "
        "text artifact (confirmed: dark void + shard shadows, no typography).",
        "",
        "Checks applied to every base: framing/head/shoulder/torso scale, eye-line, lighting direction, focal "
        "length, material distinction, architecture visibility, malformed anatomy, baked text / fake typography, "
        "watermarks, clipping, accidental props, background contamination, style drift, bloom, unusable dark areas, "
        "overlay-hostile silhouette.",
        "",
        "## Non-blocking art-direction flags (verdict remains PASS)",
        "",
    ]
    for k, v in FLAGS.items():
        lines.append(f"- `{k}` — {v}")
    lines += [
        "",
        "No base shows baked text, fake typography, watermarks, malformed anatomy, or duplicate facial identity "
        "requiring regeneration. The three ‘inset-as-frame’ tiles and the red-tinted suspended-core are art-direction "
        "confirmations, not technical defects.",
        "",
        "## Per-base verdicts",
        "",
        "| key | verdict | note |",
        "| --- | --- | --- |",
    ]
    for r in records:
        lines.append(f"| `{r['key']}` | {r['verdict']} | {r['flag'] or ''} |")
    OUT_MD.write_text("\n".join(lines) + "\n")
    print(f"reviewed={len(ks)} pass={passes} regenerate={regen} flags={len(FLAGS)}")
    print(f"wrote {OUT_MD}\nwrote {OUT_JSON}")


if __name__ == "__main__":
    main()
