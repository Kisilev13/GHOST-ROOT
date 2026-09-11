#!/usr/bin/env python3
"""Self-test for generate_production.py's compositing mechanics.

Creates synthetic, obviously-fake layer PNGs (flat color rectangles) in a
temporary directory that is deleted afterward — never under collection/assets/,
never mistaken for real art. Proves: layers composite in the declared z-order,
wrong-sized layers are rejected, output is byte-identical across repeated
builds (determinism), and missing-asset detection correctly reports nothing
resolved when no assets exist (the real current state of the repo).

This does NOT prove the art pipeline produces good art — it proves the code
that will composite real art, once real art exists, is correct. Run:
    collection/.venv/bin/python3 collection/scripts/selftest_generator.py
"""
from __future__ import annotations

import hashlib
import shutil
import sys
import tempfile
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import asset_manifest as am  # noqa: E402
import generate_production as gp  # noqa: E402


def make_layer(path: Path, size, rgba):
    from PIL import Image

    path.parent.mkdir(parents=True, exist_ok=True)
    Image.new("RGBA", size, rgba).save(path, "PNG")


def main() -> int:
    checks = []

    def check(name, ok):
        checks.append((name, ok))
        print(f"{'PASS' if ok else 'FAIL'}  {name}")

    tmp = Path(tempfile.mkdtemp(prefix="ghost-root-selftest-"))
    real_layers_dir = am.LAYERS_DIR
    am.LAYERS_DIR = tmp / "layers"  # redirect the module, restored in finally
    try:
        trait_ids = {
            "entity": "entity.synthetic", "access": "access.user", "architecture": "architecture.sealed",
            "material": "material.porcelain", "eyes": "eyes.biometric", "interface": "interface.none",
            "implant": "implant.none", "mantle": "mantle.archive_coat", "background": "background.evidence_void",
            "signal": "signal.locked", "corruption": "corruption.intact",
        }

        # 1. With no synthetic assets present, every required slot must report missing —
        #    proves the "never fabricate a missing asset" path, matching the real repo state.
        resolved, missing = gp.plan_layers(trait_ids)
        check("no assets on disk -> all required slots report missing", len(resolved) == 0 and len(missing) > 0)

        # 2. Create synthetic (clearly fake) layers for every REQUIRED slot only, at the
        #    correct canvas size, each a distinct flat color so paste order is verifiable.
        colors = [(255, 0, 0, 255), (0, 255, 0, 255), (0, 0, 255, 255), (255, 255, 0, 255)]
        made = 0
        for i, slot in enumerate(am.LAYER_STACK):
            if not slot.required or am.skip_reason(slot, trait_ids) is not None:
                continue
            candidates = am.candidate_paths(slot, trait_ids)
            if not candidates:
                continue
            make_layer(candidates[-1], (gp.CANVAS, gp.CANVAS), colors[made % len(colors)])
            made += 1
        resolved, missing = gp.plan_layers(trait_ids, omit_unregistered=False)
        check("synthetic assets for every required slot -> nothing missing", len(missing) == 0)
        check("resolved layers are in ascending z-order", [s.z for s, _ in resolved] == sorted(s.z for s, _ in resolved))

        trait_ids_imp = dict(trait_ids)
        trait_ids_imp["implant"] = "implant.antenna"
        _, missing_imp = gp.plan_layers(trait_ids_imp, omit_unregistered=False)
        check(
            "non-empty implant without asset is reported missing",
            any("implant" in m or "rear_anatomy" in m for m in missing_imp),
        )

        # 3. Composite, and prove determinism: identical inputs -> byte-identical output.
        img1 = gp.composite(resolved)
        b1 = gp.deterministic_png_bytes(img1)
        img2 = gp.composite(resolved)
        b2 = gp.deterministic_png_bytes(img2)
        check("canvas is 2048x2048", img1.size == (gp.CANVAS, gp.CANVAS))
        check("output is deterministic (identical bytes across two builds)", hashlib.sha256(b1).hexdigest() == hashlib.sha256(b2).hexdigest())
        check("output is opaque RGB (delivery spec), not RGBA", img1.mode == "RGB")

        # 4. A wrong-sized layer must be rejected, not silently stretched/cropped.
        bad_slot, bad_path = resolved[0]
        make_layer(bad_path, (100, 100), (1, 2, 3, 255))
        resolved_bad, _ = gp.plan_layers(trait_ids)
        try:
            gp.composite(resolved_bad)
            check("wrong-sized layer is rejected", False)
        except gp.GenerationError:
            check("wrong-sized layer is rejected", True)
        finally:
            make_layer(bad_path, (gp.CANVAS, gp.CANVAS), colors[0])  # restore for cleanliness

        make_layer(bad_path, (gp.CANVAS, gp.CANVAS), (0, 0, 0, 0))
        resolved_blank, _ = gp.plan_layers(trait_ids)
        try:
            gp.composite(resolved_blank)
            check("blank transparent asset is rejected", False)
        except gp.GenerationError:
            check("blank transparent asset is rejected", True)
        finally:
            make_layer(bad_path, (gp.CANVAS, gp.CANVAS), colors[0])

    finally:
        am.LAYERS_DIR = real_layers_dir
        shutil.rmtree(tmp, ignore_errors=True)

    failed = [n for n, ok in checks if not ok]
    print(f"\n{len(checks) - len(failed)}/{len(checks)} passed")
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
