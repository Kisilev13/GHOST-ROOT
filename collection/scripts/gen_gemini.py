#!/usr/bin/env python3
"""Generate artwork via the Gemini image API (generativelanguage.googleapis.com).

Reads GEMINI_API_KEY from the environment (load it from the gitignored
.secrets/gemini.env; never hard-code or print the key). Text prompt, with an
optional reference image for image+text -> image conditioning. Writes the
returned PNG to --out. Does NOT touch the compositor or production assets.

Usage:
  GEMINI_API_KEY=... python3 gen_gemini.py --model gemini-3-pro-image \
      --prompt "..." [--ref path.png] --out out.png
"""
from __future__ import annotations
import argparse, base64, json, mimetypes, os, sys, urllib.request

API = "https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent"


def generate(model: str, prompt: str, out: str, ref: str | None) -> None:
    key = os.environ.get("GEMINI_API_KEY")
    if not key:
        sys.exit("GEMINI_API_KEY not set (source .secrets/gemini.env).")
    parts: list[dict] = [{"text": prompt}]
    if ref:
        mime = mimetypes.guess_type(ref)[0] or "image/png"
        parts.insert(0, {"inline_data": {"mime_type": mime,
                    "data": base64.b64encode(open(ref, "rb").read()).decode()}})
    gen_cfg: dict = {"responseModalities": ["IMAGE"]}
    if os.environ.get("GEMINI_IMAGE_SIZE"):
        gen_cfg["imageConfig"] = {"imageSize": os.environ["GEMINI_IMAGE_SIZE"]}
        if os.environ.get("GEMINI_ASPECT"):
            gen_cfg["imageConfig"]["aspectRatio"] = os.environ["GEMINI_ASPECT"]
    body = {
        "contents": [{"role": "user", "parts": parts}],
        "generationConfig": gen_cfg,
    }
    req = urllib.request.Request(
        API.format(model=model) + f"?key={key}",
        data=json.dumps(body).encode(), headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=180) as r:
        resp = json.load(r)
    # Find the first inline image part.
    for cand in resp.get("candidates", []):
        for p in cand.get("content", {}).get("parts", []):
            blob = p.get("inlineData") or p.get("inline_data")
            if blob and blob.get("data"):
                open(out, "wb").write(base64.b64decode(blob["data"]))
                print(f"wrote {out}")
                return
    # No image -> surface text / finishReason for debugging (never the key).
    fr = resp.get("candidates", [{}])[0].get("finishReason")
    txt = json.dumps(resp)[:600]
    sys.exit(f"No image returned (finishReason={fr}). Response head: {txt}")


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--model", default="gemini-3-pro-image")
    ap.add_argument("--prompt", required=True)
    ap.add_argument("--ref")
    ap.add_argument("--out", required=True)
    a = ap.parse_args()
    generate(a.model, a.prompt, a.out, a.ref)
