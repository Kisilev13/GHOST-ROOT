#!/usr/bin/env python3
"""Wave 3 authored-scene authoring: inventory, per-token briefs/prompts, and
native-2048 Gemini generation for the 26 missing legendary authored scenes.

Each legendary token (x03) has a full canonical trait vector in design-witness.csv.
An authored scene is ONE opaque 2048x2048 sRGB PNG at
  collection/assets/scenes/legendary/<id>__v001.png
(authored scenes bypass the layer stack). Every scene must VISUALLY HONOR its
canonical metadata and carry a DISTINCT severed_halo composition, matching the
approved legendary language (0303/0803/1503/2803).

Reads GEMINI_API_KEY from env (source ../.secrets/gemini.env). Never prints it.
Subcommands: inventory | briefs | gen (--only/--ids/--batch).
"""
from __future__ import annotations
import argparse, csv, hashlib, json, os, subprocess, sys, tempfile
from pathlib import Path
from PIL import Image

COLLECTION = Path(__file__).resolve().parents[1]
ROOT = COLLECTION.parent
SCENES = COLLECTION / "assets" / "scenes"
GEN = COLLECTION / "scripts" / "gen_gemini.py"
PY = COLLECTION / ".venv" / "bin" / "python3"
WITNESS = COLLECTION / "design-witness.csv"
RESOLVER = json.loads((COLLECTION / "manifests" / "collection-required-assets.json").read_text())

# --- canonical trait -> concise visual phrase (drawn from art-bible vocabulary) ---
MATERIAL = {
    "porcelain": "glossy white porcelain with fine craquelure and gold kintsugi seams",
    "ceramic": "matte ivory ceramic",
    "carbon": "dark graphite carbon-fibre",
    "biosynth": "translucent wet bio-synthetic polymer with a subsurface sheen",
    "phase_glass": "transparent refractive phase-glass",
    "reconstructed": "cracked reconstructed ceramic rejoined with gold kintsugi",
}
EYES = {
    "biometric": "closed or downcast biometric scanner eyes with a faint amber iris ring",
    "thermal": "recessed thermal sensor oculi with deep-red emitter cores",
    "void": "empty black void eye sockets",
    "fractured": "broken fractured optical geometry in the eye sockets",
    "optical_array": "compound optical-array lens clusters in the sockets",
    "signal_burn": "sockets scorched with a restrained violet-white burn glow",
    "biosignal": "faint living biosignal glow behind the eyes",
}
INTERFACE = {
    "forensic_plate": "a hinged matte-steel forensic evidence plate clamped over the lower face",
    "neural_veil": "a fine chainmail neural veil draped over the upper face",
    "null_mask": "a blank featureless ceramic null-mask shell seated off the face",
    "respirator": "a fitted lower-face respirator with cheek filter canisters",
    "skeletal_interface": "an exposed steel jaw-and-cheek skeletal exoskeleton",
    "none": "a bare unmasked face",
}
IMPLANT = {
    "antenna": "a bead-blasted steel antenna stalk from the temple",
    "memory_spindle": "a ceramic-and-steel memory-spindle cartridge at the jaw",
    "neural_cable": "temple sockets with black cabling draping to the shoulders",
    "root_port": "a heavy circular bulkhead root-port set into the crown",
    "spinal_bus": "a segmented ceramic vertebral spinal-bus rising from the nape",
    "signal_crown": "a radiant crown ring of thin gold antenna signal-crown spikes",
}
MANTLE = {
    "archive_coat": "a tall structured ceramic archive-coat collar over the shoulders",
    "cable_shroud": "a shroud of black rubberized cables around the neck and shoulders",
    "ceramic_mantle": "a broken cracked ceramic crescent mantle collar with gold seams",
    "field_collar": "a tall smooth ceramic field-collar",
    "split_shell": "a segmented split-shell shoulder mantle standing proud",
    "unsupported_frame": "an unsupported open ceramic frame collar",
    "none": "bare shoulders",
}
CORRUPTION = {
    "packet_ghosting": "a faint doubled echo ghosting one edge of the face",
    "checksum_burn": "a charred etched checksum-burn band across one cheek",
    "scan_shear": "a clean horizontal scan-shear offset across the face",
    "memory_bleed": "a soft vertical memory-bleed trail from one eye",
    "signal_loss": "patches of signal-loss dropout on the surface",
    "fragmentation": "surface fragmentation flaking into fine shards",
    "intact": "an intact uncorrupted surface",
    "absence": "a clean-edged region bitten away into a dark hollow void",
}
BACKGROUND = {
    "cold_storage": "a cold-storage archive vault with recessed door jambs",
    "rack_shadow": "faint server-rack slats receding in shadow",
    "relay_well": "a dark elliptical relay-well vault",
    "evidence_void": "a near-black evidence void",
    "server": "a dim server hall",
    "blacksite": "a blacksite chamber",
    "signal_chamber": "a signal chamber",
    "anechoic_room": "an anechoic chamber of absorptive wedge walls",
    "service_tunnel": "a receding service tunnel",
    "uplink_black": "a near-black uplink void",
    "archive": "a deep archive",
    "satellite": "a cold satellite bay",
}
# Distinct severed_halo composition archetypes; assigned deterministically for variety.
COMPOSITIONS = [
    "the severed halo is a full broken ceramic ring suspended around empty space; the head sits small and low in the frame, negative space dominant.",
    "a wide asymmetric broken arc breaks the top-left of the frame; the head is low-right in deep negative space, the arc dwarfing it.",
    "a radiant crown of gold antenna spikes fans symmetrically from the skull like a reliquary saint, torso centred and frontal.",
    "the shell is cracked open like a reliquary to reveal internal archive machinery and a spinal column rising from the opened cavity, lit faintly from within.",
    "the figure is held in a curved severed-halo cradle arm on the right, the body shattering with fragments detaching outward.",
    "a vertical top-heavy column of internal structure and crown spikes breaks the upper edge; the shoulder mantle opens like vault doors.",
    "winged shell fragments sweep back from the shoulders around a broken halo vault, an internal glow orrery at the sternum.",
    "a low, wide throne-like composition: the head enthroned inside a broken archive halo arc, cabling terminating around it.",
    "the halo is a shattered double ring, two concentric broken ceramic arcs offset, the head between them in a shaft of hard light.",
    "a tall monolith silhouette: the figure fused into a standing severed-halo slab, only the face and crown emerging.",
]


def legendary_tokens():
    return sorted(int(Path(r["path"]).name.split("__")[0]) for r in RESOLVER["paths"]
                  if r["slot"] == "authored_scene" and "legendary" in r["path"])


def load_traits():
    rows = {}
    with WITNESS.open(newline="") as f:
        for r in csv.DictReader(f):
            rows[int(r["token_id"])] = r
    return rows


def val(row, cat):
    v = row.get(cat, "")
    return v.split(".", 1)[1] if "." in v else v


def scene_path(tid):
    sub = "genesis" if tid <= 3 else "legendary"
    return SCENES / sub / f"{tid:04d}__v001.png"


def brief_for(tid, row):
    comp = COMPOSITIONS[(tid // 100) % len(COMPOSITIONS)]
    ent = val(row, "entity"); mat = val(row, "material"); eye = val(row, "eyes")
    itf = val(row, "interface"); imp = val(row, "implant"); man = val(row, "mantle")
    cor = val(row, "corruption"); bg = val(row, "background"); sig = val(row, "signal")
    return {
        "token_id": tid, "name": f"GHOST//{tid:04d}", "rarity": "legendary",
        "entity": ent, "access": val(row, "access"), "state": sig,
        "material": mat, "architecture": "severed_halo", "eyes": eye,
        "interface": itf, "implant": imp, "mantle": man, "corruption": cor, "background": bg,
        "composition": comp,
        "prompt": build_prompt(tid, ent, mat, eye, itf, imp, man, cor, bg, sig, comp),
    }


STYLE = ("Museum-archival forensic sci-fi 1/1 collectible key art, single centred "
         "reliquary figure, hard directional key light from upper-left with deep "
         "shadow, near-black negative space, restrained gold/red signal accents only, "
         "controlled bloom, photoreal sculptural materials, precise silhouette, shallow "
         "editorial depth of field. No text, no letters, no numbers, no watermark, no "
         "logo, no neon, no cyberpunk city, no circuitry, no weapons, no fantasy armor. "
         "ABSOLUTELY NO stamped text, no barcodes, no serial numbers, no engraved letters "
         "or glyphs, no plaques or labels with writing, no captions, no name tags, no "
         "signage — all surfaces are blank.")


def build_prompt(tid, ent, mat, eye, itf, imp, man, cor, bg, sig, comp):
    parts = [
        f"GHOST//{tid:04d}, a legendary ROOT-access {ent} reliquary figure in the "
        f"GHOST//ROOT archive.",
        f"Material: {MATERIAL.get(mat, mat)}.",
        f"Architecture: a SEVERED HALO — {comp}",
        f"Eyes: {EYES.get(eye, eye)}.",
        f"Interface: {INTERFACE.get(itf, itf)}." if itf != "none" else "Face bare, no mask.",
        f"Hardware: {IMPLANT.get(imp, imp)}.",
        f"Mantle: {MANTLE.get(man, man)}." if man != "none" else "",
        f"Surface state: {CORRUPTION.get(cor, cor)}." if cor != "intact" else "Surface intact and pristine.",
        f"Environment: {BACKGROUND.get(bg, bg)}, deep shadow.",
        STYLE,
    ]
    return " ".join(p for p in parts if p)


def cmd_inventory(_):
    traits = load_traits()
    sc = [r for r in RESOLVER["paths"] if r["slot"] == "authored_scene"]
    rows = []
    for r in sorted(sc, key=lambda r: int(Path(r["path"]).name.split("__")[0])):
        tid = int(Path(r["path"]).name.split("__")[0])
        p = ROOT / r["path"]
        row = traits.get(tid, {})
        rec = {"token_id": tid, "path": r["path"], "filename": Path(r["path"]).name,
               "rarity": "genesis" if tid <= 3 else "legendary",
               "present": p.is_file(),
               "sha256": hashlib.sha256(p.read_bytes()).hexdigest() if p.is_file() else None,
               "entity": val(row, "entity"), "state": val(row, "signal"),
               "material": val(row, "material"), "architecture": val(row, "architecture"),
               "mantle": val(row, "mantle"), "implant": val(row, "implant"),
               "eyes": val(row, "eyes"), "interface": val(row, "interface"),
               "corruption": val(row, "corruption"), "background": val(row, "background"),
               "access": val(row, "access")}
        rows.append(rec)
    present = sum(1 for r in rows if r["present"])
    out = {"authored_scene_required": len(rows), "present": present,
           "missing": len(rows) - present, "scenes": rows}
    (COLLECTION / "reports" / "wave3-authored-scene-inventory.json").write_text(json.dumps(out, indent=2) + "\n")
    md = ["# Wave-3 authored-scene inventory", "",
          f"AUTHORED SCENE REQUIRED: {len(rows)}", f"PRESENT: {present}", f"MISSING: {len(rows)-present}", "",
          "| id | rarity | present | entity | material | eyes | interface | implant | mantle | corruption | background | state |",
          "| ---: | --- | :-: | --- | --- | --- | --- | --- | --- | --- | --- | --- |"]
    for r in rows:
        md.append(f"| {r['token_id']:04d} | {r['rarity']} | {'Y' if r['present'] else 'N'} | {r['entity']} | "
                  f"{r['material']} | {r['eyes']} | {r['interface']} | {r['implant']} | {r['mantle']} | "
                  f"{r['corruption']} | {r['background']} | {r['state']} |")
    (COLLECTION / "reports" / "wave3-authored-scene-inventory.md").write_text("\n".join(md) + "\n")
    print(f"AUTHORED SCENE REQUIRED: {len(rows)}\nPRESENT: {present}\nMISSING: {len(rows)-present}")


def missing_tokens():
    return [t for t in legendary_tokens() if not scene_path(t).is_file()]


def cmd_briefs(_):
    traits = load_traits()
    briefs = [brief_for(t, traits[t]) for t in missing_tokens()]
    (COLLECTION / "reports" / "wave3-scene-briefs.json").write_text(json.dumps(briefs, indent=2) + "\n")
    md = ["# Wave-3 scene briefs (26 missing legendary authored scenes)", "",
          "Each brief is derived from the token's canonical trait vector (design-witness.csv). "
          "Composition archetype is assigned deterministically for variety; all obey "
          "architecture.severed_halo and the approved legendary visual language.", ""]
    for b in briefs:
        md += [f"## {b['name']} — legendary",
               f"- entity **{b['entity']}** · material **{b['material']}** · state **{b['state']}** · access **{b['access']}**",
               f"- eyes {b['eyes']} · interface {b['interface']} · implant {b['implant']} · mantle {b['mantle']} · corruption {b['corruption']}",
               f"- environment {b['background']}",
               f"- composition: {b['composition']}",
               f"- prompt: {b['prompt']}", ""]
    (COLLECTION / "reports" / "wave3-scene-briefs.md").write_text("\n".join(md) + "\n")
    print(f"wrote {len(briefs)} briefs")


def resave_opaque_2048(src: Path, dst: Path):
    im = Image.open(src).convert("RGB").resize((2048, 2048), Image.LANCZOS)
    dst.parent.mkdir(parents=True, exist_ok=True)
    im.save(dst, "PNG")


def cmd_gen(a):
    if not os.environ.get("GEMINI_API_KEY"):
        sys.exit("GEMINI_API_KEY not set (source .secrets/gemini.env)")
    traits = load_traits()
    todo = missing_tokens()
    if a.ids:
        want = {int(x) for x in a.ids.split(",")}
        todo = [t for t in todo if t in want]
    if a.batch:
        todo = todo[: a.batch]
    print(f"{len(todo)} authored scenes to generate: {todo}")
    tmp = Path(tempfile.mkdtemp(prefix="w3-scenes-"))
    env = dict(os.environ, GEMINI_IMAGE_SIZE="2K", GEMINI_ASPECT="1:1")
    ok = 0
    for i, tid in enumerate(todo, 1):
        b = brief_for(tid, traits[tid])
        raw = tmp / f"{tid}.png"
        r = subprocess.run([str(PY), str(GEN), "--model", a.model, "--prompt", b["prompt"], "--out", str(raw)],
                           env=env, capture_output=True, text=True)
        if r.returncode != 0 or not raw.exists():
            print(f"[{i}/{len(todo)}] FAIL {tid:04d}: {r.stderr.strip()[:150]}"); continue
        resave_opaque_2048(raw, scene_path(tid))
        print(f"[{i}/{len(todo)}] wrote scenes/legendary/{tid:04d}__v001.png")
        ok += 1
    print(f"\n{ok}/{len(todo)} scenes written")


def main():
    ap = argparse.ArgumentParser()
    sub = ap.add_subparsers(dest="cmd", required=True)
    sub.add_parser("inventory")
    sub.add_parser("briefs")
    g = sub.add_parser("gen")
    g.add_argument("--ids"); g.add_argument("--batch", type=int)
    g.add_argument("--model", default="gemini-3-pro-image")
    a = ap.parse_args()
    {"inventory": cmd_inventory, "briefs": cmd_briefs, "gen": cmd_gen}[a.cmd](a)


if __name__ == "__main__":
    main()
