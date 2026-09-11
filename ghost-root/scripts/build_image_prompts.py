#!/usr/bin/env python3
"""Condense each <dest>/<class>/<id>/traits.json into a compact image-prompt.txt
(the literal string sent to the image API). Cloudflare Workers AI caps /prompt at
2048 chars including any appended negative text.

v2 — production-prompt-delta applied (see visual-tests/reports/production-prompt-delta.md):
  GLOBAL     : frontal + near-black + bare-head merged into sentence 1; TIER_SIGNATURE clause.
  COMPOSITION: single opening sentence carries camera lock + background.
  LIGHTING   : hard upper-left key, deep blacks, no fill, shadow-side rim separation.
  MATERIAL   : matte manufactured only; vermilion/violet accents only.
  NEGATIVE   : hood family front-loaded; text/glyph family; rhinestones/glitter/jewelry;
               mirror-chrome/gloss; photoreal human skin; elongated-alien/bulbous-eye.
  STANDARD   : desirability floor.
  CORRUPTED  : closed defect vocabulary, one fault, still an entity that is damaged.
  ADMIN      : interface complexity + machined seams + bilateral symmetry.
  ROOT       : head partly network infrastructure; exposed spinal data-column; forehead
               sigil; gaunt/asymmetric/less-human; NOT the ADMIN circular ear module.
  GENESIS    : bespoke non-procedural signature; drop the shared skull+ear-disc kit.

v3 — self-contradiction fixes from the v2 REPEAT_VISUAL_PROTOTYPE_STAGE review
  (visual-tests-v2/reports/visual-review.md, "Prompt implementation gaps"). Each
  item below is a literal prompt-vs-negative or prompt-vs-signature contradiction
  found by diffing the sentence builder against NEGATIVE_CORE and genesis.yaml;
  none of these change trait vocabulary or metadata, only prompt wording:
    - s2 asserted "fine craquelure" on every entity while s4 told intact (corruption
      NONE) entities "no damage, cracks" — craquelure is now scoped as a permitted
      manufactured surface microtexture, explicitly distinct from structural cracks.
    - CORRUPTION_VISUAL["PACKET GHOSTING"] requests a duplicated jaw while
      NEGATIVE_CORE banned "duplicate face" outright — the negative now targets
      unintended extra heads/eyes, not the named single-defect duplication.
    - ADMIN's TIER_SIGNATURE claimed one-sided exposed architecture AND strict
      bilateral symmetry in the same breath — now the *head/face geometry* is
      symmetric while the interior reveal is named as the asymmetric element.
    - ROOT's "forehead sigil" reads as a glyph, which NEGATIVE_CORE bans — now
      described as a non-symbolic raised mechanical boss, explicitly not a mark.
    - genesis.yaml's ghost-0001 signature includes "heavy break collar" (a rigid
      structural element) while NEGATIVE_CORE banned the bare word "collar" (aimed
      at fabric neckwear) — the negative now only bans fabric/soft collars.
    - s5 stated the `background` trait as a bare noun (e.g. "environment SERVER"),
      which the review found rendering as literal scene props (root-01's visible
      server racks) despite s1's near-black/no-busy-background requirement — it is
      now scoped explicitly to tone/lighting only, never physical set dressing.
    - GENESIS pieces still received the generic per-trait face/eye sentences (s2,
      part of s5) on top of their bespoke signature (s3), so a signature declaring
      a *missing* face (ghost-0002 "THE NULL") was directly contradicted by the
      shared template asserting a complete face and named eyes. GENESIS prompts now
      drop the generic face-anatomy clause and the eyes/implant clause, since s3 —
      "outside the procedural trait system" — is the sole authority on the head.

  Not fixed here (design decisions, not prompt bugs — see the diagnosis report):
  CORRUPTED tier still draws its face material from the full per-piece Face
  Material trait rather than one shared "corrupted" material family (the
  review's CR4 item); resolving that changes trait semantics and needs a
  trait-architecture decision, not a wording change.

The full prompt.txt remains the authoritative human spec; image-prompt.txt is the
literal API string.
"""
import argparse
import json
from pathlib import Path

MAX_LEN = 2020  # hard budget below the 2048 API cap

# --- NEGATIVE (N1..N5), front-loaded per the delta; terse (flux-schnell weights
#     early tokens and this string is appended to the prompt, not a real neg field) ----
NEGATIVE_CORE = (
    "hood, hoodie, cowl, balaclava, ski mask, scarf, fabric collar, turtleneck, fabric, cloth, hair; "
    "text, letters, numbers, glyphs, engraved writing, watermark, readable symbol; "
    "rhinestones, glitter, gems, sequins, jewelry, beaded texture; "
    "mirror chrome, high-gloss, wet look, glossy plastic, showroom render; "
    "photoreal human skin, real person portrait; "
    "elongated alien skull, bulbous eyes, spiral iris; "
    "neon city, Matrix rain, skull mascot, superhero armor, generic android, busy background, "
    "visible scene props or set dressing; "
    "rainbow lighting, extra limbs, deformed hands, extra heads, extra eyes, glitch overlay, "
    "RGB split, scanlines, profile view, three-quarter angle, turned head, flat even lighting, "
    "washed-out blacks"
)

# --- CORRUPTION defect vocabulary (CR2) — physically describable faults ----------------
CORRUPTION_VISUAL = {
    "NONE": "",
    "MEMORY BLEED": (
        "one vertical fracture runs through a cheek; dark viscous ink-like fluid wells up inside "
        "the crack and runs downward in a single thin track across the jaw"
    ),
    "FRAGMENTATION": (
        "the facial and cranial surface is broken into four to six angular plates, each displaced "
        "and rotated a few millimetres out of alignment, with thin dark structural gaps between them"
    ),
    "SIGNAL LOSS": (
        "one hard-edged region of the face is flat, dead, matte black — missing material entirely, "
        "no texture, no detail, no granularity, no beads; a clean void with a sharp boundary"
    ),
    "PACKET GHOSTING": (
        "the entire lower face is duplicated as a second solid opaque copy offset about three "
        "centimetres to one side, the same matte material, fused into the jaw — not transparent, "
        "not glowing, not blurred"
    ),
    "GLITCH": (
        "several thin horizontal bands of the face are shifted a few millimetres left or right from "
        "the rows above and below, like a torn strip of photograph, a hairline dark gap at each shift"
    ),
    "BURN": (
        "one side of the face is charred: blistered, blackened, partially collapsed material, crazed "
        "with heat-fracture lines, ending at a sharp boundary against clean surface"
    ),
}

# --- TIER_SIGNATURE (G3 / per-class sections) -----------------------------------------
TIER_SIGNATURE = {
    "STANDARD": (
        "STANDARD tier: a complete composed face, minimal hardware, no exposed interior; still "
        "intentional and collectible — never a blank bust, mannequin, unfinished sculpt, or closed "
        "featureless eyes."
    ),
    "CORRUPTED": (
        "CORRUPTED tier: an otherwise complete composed face carrying exactly one physical fault "
        "(below); still clearly an entity that is damaged, not a decorative object, shoulders visible."
    ),
    "ADMIN": (
        "ADMIN tier: visible interface complexity. Head and facial geometry stay bilaterally "
        "symmetric; the one asymmetric feature is exposed illuminated internal architecture opened "
        "along a single side, framed by precise machined panel seams. More engineered than STANDARD."
    ),
    "ROOT": (
        "ROOT tier: the head is partly network infrastructure — cranial volume partly replaced by "
        "structured conduit and lattice, an exposed segmented spinal data-column at the throat, one "
        "small raised forehead boss of plain unmarked machined metal, not a sigil or glyph; gaunt, "
        "asymmetric, less human. No circular ear or headphone module."
    ),
}


def build(traits: dict, dest_root: Path) -> str:
    t = traits["traits"]
    cls = traits.get("class", "STANDARD")
    genesis = traits.get("genesis")
    is_genesis = cls == "GENESIS" and bool(genesis)
    face = t["face"].lower()
    mask_clause = (
        "the face is bare and unmasked"
        if t["mask"] == "NONE"
        else f"a rigid {t['mask'].lower()} interface is fused to the face"
    )

    # sentence 1 — camera lock + background + bare head, all front-loaded (G2/C1/N1)
    s1 = (
        "Frontal studio portrait, near-black background, camera at eye level: nose to the lens, "
        "shoulders square, both eyes symmetric — not a profile, not a three-quarter angle. "
        "Bare head and shoulders: no hood, no fabric, no collar, no hair."
    )

    # sentence 2 — material discipline (M1/M2). GENESIS pieces skip the face-anatomy
    # claim entirely: s3 (below) is the sole authority on head/face for a bespoke
    # signature, which may describe a face that is absent, split or reconfigured —
    # asserting "sculpted as anatomy" here would contradict that (v3 fix).
    if is_genesis:
        s2 = "The whole visible surface is matte manufactured material with a fine microtexture."
    else:
        s2 = (
            f"The whole visible surface is matte manufactured {face}, sculpted as anatomy with a "
            f"fine manufactured microtexture, not structural cracking; {mask_clause}."
        )

    # sentence 3 — tier signature (G3) or bespoke Genesis (GEN1..GEN3). `art_prompt_delta`
    # is the dossier's specific art-direction text (collection/genesis.md) — distinct from
    # `visual_signature`'s terser identity statement, and the actual source of the
    # distinguishing detail the v2 review found missing (e.g. ghost-0003's silhouette vs
    # ADMIN). Optional: falls back to visual_signature alone if a traits.json predates
    # the field, so the frozen v1/v2 batches still build.
    if is_genesis:
        delta = genesis.get("art_prompt_delta", "")
        s3 = (
            "This is a GENESIS character — unique, hand-authored, outside the procedural trait system. "
            f"Signature, exclusive to this entity and used by no other GHOST: {genesis['visual_signature']}"
            + (f" Art direction: {delta}" if delta else "")
            + " This signature is the sole authority on the head/face, even where it describes an absent "
            "or reconfigured face. Do not fall back on a plain circular ear disc or the standard "
            "segmented-skull kit."
        )
    else:
        s3 = TIER_SIGNATURE.get(cls, TIER_SIGNATURE["STANDARD"])

    # sentence 4 — corruption fault (CR1/CR2). GENESIS suppresses the generic
    # per-tier corruption visual for the same reason as s2/s5: a bespoke signature
    # is the sole authority on the head, and the procedural fault vocabulary is
    # written for a complete, ordinary face — e.g. "duplicated lower face" directly
    # contradicts a signature describing missing face volume. The corruption trait
    # still exists in metadata; the signature is trusted to already read as damaged/
    # compromised where the character calls for it (v3 fix; this is the exact failure
    # the review named for ghost-0002 — "duplicated jaw repeats an ordinary
    # corruption trait").
    corruption = t["corruption"]
    if is_genesis:
        s4 = ""
    elif corruption == "NONE":
        s4 = "The surface is otherwise fully intact — no structural damage or missing material."
    else:
        visual = CORRUPTION_VISUAL.get(corruption, "")
        s4 = (
            f"The single physical fault ({corruption.lower()}): {visual}."
            if visual
            else f"System corruption visible in the material itself: {corruption}."
        )

    # sentence 5 — remaining traits, compressed. GENESIS drops eyes/implant (head
    # hardware that s3's bespoke signature already owns and may contradict, e.g. a
    # signature describing a missing face volume) but keeps the non-facial context
    # traits. `background` is stated as a tone/lighting cue only, never as physical
    # set dressing — s1 already requires a near-black, non-busy background, so a bare
    # trait name here ("environment SERVER") was rendering as literal scene props
    # (v3 fix; the review's root-01 example: visible server racks).
    background_clause = (
        f"ambient tone evokes {t['background']} as background darkness/color temperature only, "
        f"no visible props or scene objects"
    )
    if is_genesis:
        s5 = f"Entity {t['entity']}; access {t['access']}; signal {t['signal']}; {background_clause}."
    else:
        s5 = (
            f"Entity {t['entity']}; eyes {t['eyes']}; skull-fused neural hardware {t['implant']}; "
            f"access {t['access']}; signal {t['signal']}; {background_clause}."
        )

    # sentence 6 — lighting law + rim separation (L1/L2)
    s6 = (
        "Hard key light from upper-left, deep true blacks, no fill, thin cool rim light along the "
        "shadow-side edge separating the head from the background."
    )

    # sentence 7 — style close + accent limit + no-text (M4/N2)
    s7 = (
        "Forensic evidence photograph, blacksite atmosphere. Vermilion and violet accents only. "
        "Head-and-shoulders crop, centred, museum-quality, readable silhouette at 64px. "
        "No text or watermark anywhere."
    )

    note = traits.get("note")
    # Load-bearing content sentences, never trimmed. s4 is "" for GENESIS (v3: the
    # bespoke signature is the sole authority on the head, see above) and must be
    # filtered out the same way in both the normal and over-budget paths below —
    # `" ".join(core)` without filtering left a double space where s4 used to be.
    core = [p.strip() for p in (s1, s2, s3, s4, s5, s6, s7) if p and p.strip()]
    parts = list(core)
    if note:
        parts.append(f"Prototype note: {note}")
    parts.append(f"Negative: {NEGATIVE_CORE}.")

    prompt = " ".join(parts)
    if len(prompt) > MAX_LEN:
        # trim the NEGATIVE tail (least load-bearing on flux-schnell), keep every core sentence.
        base = " ".join(core)
        budget = MAX_LEN - len(base) - len(" Negative: .")
        neg = NEGATIVE_CORE[:budget].rsplit(",", 1)[0].strip()
        prompt = f"{base} Negative: {neg}."
    return prompt


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument(
        "--dest",
        default="/home/hacker/NFT/Ghost/visual-tests",
        help="batch root containing <class>/<id>/traits.json",
    )
    args = ap.parse_args()
    dest = Path(args.dest)
    count = 0
    over = []
    for traits_path in sorted(dest.glob("*/*/traits.json")):
        traits = json.loads(traits_path.read_text())
        prompt = build(traits, dest)
        (traits_path.parent / "image-prompt.txt").write_text(prompt)
        count += 1
        if len(prompt) > 2048:
            over.append((traits_path.parent.name, len(prompt)))
    assert not over, f"prompts over the 2048 cap: {over}"
    print(f"wrote {count} image-prompt.txt files under {dest} (delta v2 applied)")


if __name__ == "__main__":
    main()
