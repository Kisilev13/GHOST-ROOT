#!/usr/bin/env python3
"""Author four reusable archive environment plates; no characters or NFT stand-ins.

Geometric depth, lighting falloff and fixed material grain are part of the artwork.
No external textures. Fixed grain is shared across every export, never token uniqueness.
"""
import hashlib
import json
import math
import platform
import random
from pathlib import Path

from PIL import Image, ImageChops, ImageDraw, ImageFilter, __version__ as pillow_version
from PIL.PngImagePlugin import PngInfo

from asset_manifest import COLLECTION, ROOT

N = 2048


def plate(kind):
    # Continuous studio key from upper viewer-left; an intentionally quiet head field.
    small = Image.new('RGB', (512, 512))
    pix = []
    for y in range(512):
        for x in range(512):
            nx, ny = x/511, y/511
            key = math.exp(-(((nx-.28)/.55)**2 + ((ny-.19)/.74)**2))
            quiet = math.exp(-(((nx-.50)/.24)**2 + ((ny-.43)/.33)**2))
            value = 8 + 18*key - 3*quiet
            pix.append((int(value*.90), int(value*.97), int(value)))
    small.putdata(pix)
    im = small.resize((N,N), Image.Resampling.BICUBIC)
    geometry = Image.new('RGBA', (N,N))
    d = ImageDraw.Draw(geometry)
    if kind == 'cold_storage':
        # Thick recessed archive door jambs, lid seams and brushed-metal light strips.
        for left in (True, False):
            sign = 1 if left else -1
            outer = 0 if left else N
            inner = 414 if left else 1668
            d.polygon([(outer,0),(inner,105),(inner,2048),(outer,2048)],fill=(13,18,22,225))
            for y in range(-140,2200,274):
                points=[(outer,y),(inner,y+65),(inner,y+264),(outer,y+220)]
                d.polygon(points,fill=(19,25,30,150))
                d.line(points[:2],fill=(43,48,51,145),width=3)
                d.line([(inner,y+65),(inner,y+264)],fill=(3,6,8,210),width=9)
                d.line([(inner-sign*38,y+84),(inner-sign*38,y+230)],fill=(31,39,42,140),width=5)
            d.line([(inner,120),(inner,2048)],fill=(39,43,45,115),width=7)
    elif kind == 'rack_shadow':
        # Repeating depth planes and unlit rack slots, without LED/code decoration.
        for left in (True,False):
            for j in range(4):
                x = 40+j*126 if left else N-40-j*126
                width = 90-j*10
                d.polygon([(x,0),(x+width,55),(x+width,2048),(x,2048)],fill=(6+j*2,10+j*2,13+j*2,230))
                d.line([(x,0),(x,2048)],fill=(38-j*4,40-j*4,42-j*4,150),width=5)
                for y in range(40,2050,76):
                    d.line([(x+12,y),(x+width-10,y+4)],fill=(35,38,39,85),width=3)
                    d.line([(x+12,y+18),(x+width-10,y+22)],fill=(0,0,0,150),width=8)
    elif kind == 'relay_well':
        # Elliptical vault ribs recede into a dark vertical well. Cropped at edges.
        for i in range(7):
            inset = i*95
            box=(-900+inset,-1110+inset,2950-inset,2730-inset)
            d.arc(box,180,358,fill=(39-i*3,42-i*3,46-i*3,155-i*12),width=9)
            d.arc((box[0]+12,box[1]+12,box[2]-12,box[3]-12),180,358,fill=(0,0,0,115),width=16)
        for x in (170,310,1770,1900):
            d.line([(x,0),(x*.87+132,2048)],fill=(31,35,39,125),width=9)
    elif kind != 'evidence_void':
        raise ValueError(kind)
    im = Image.alpha_composite(im.convert('RGBA'), geometry.filter(ImageFilter.GaussianBlur(3.2))).convert('RGB')
    # Fixed, low-amplitude material grain, not random per token or render.
    noise = Image.frombytes('L',(N,N),random.Random(73019).randbytes(N*N))
    noise = noise.point(lambda v:127 if v < 64 else (129 if v > 191 else 128)).convert('RGB')
    im = ImageChops.add(im,noise,scale=1,offset=-128)
    return im.convert('RGBA')


def main():
    plan=json.loads((COLLECTION/'manifests/test-batch-required-assets.json').read_text())
    records=[]
    for asset in plan['assets']:
        if asset['slot']!='background':
            continue
        path=ROOT/asset['path']
        kind=asset['trait_ids'][0].split('.')[1]
        im=plate(kind)
        info=PngInfo();info.add(b'sRGB',b'\x00')
        import io
        buf=io.BytesIO();im.save(buf,'PNG',pnginfo=info,compress_level=6)
        data=buf.getvalue()
        if path.exists() and path.read_bytes()!=data:
            raise SystemExit(f'Refusing to overwrite a different asset: {path}')
        path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(data)
        records.append({'path':asset['path'],'trait_id':asset['trait_ids'][0],
            'sha256':hashlib.sha256(data).hexdigest(),'dimensions':[N,N],
            'status':'CREATED_UNREVIEWED','technique':'original deterministic procedural environment',
            'source':'collection/scripts/build_environment_layers.py',
            'source_sha256':hashlib.sha256(Path(__file__).read_bytes()).hexdigest(),
            'external_textures':False,'python':platform.python_version(),'pillow':pillow_version,
            'generation_settings':'native 2048 geometry; continuous key; fixed material grain; no token seed'})
        print(asset['path'])
    (COLLECTION/'assets/sources/environment-provenance.json').write_text(json.dumps(records,indent=2)+'\n')


if __name__=='__main__':
    main()
