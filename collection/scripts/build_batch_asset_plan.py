#!/usr/bin/env python3
"""Exact witness-driven inventory and production specifications; never renders art."""
import hashlib
import json
from collections import Counter

from asset_manifest import COLLECTION, ROOT, LAYER_STACK, candidate_paths, skip_reason
from generate_production import load_design_witness, trait_ids_for, special_scene

ANCHORS = {'head_center': [1004, 922], 'crown': [1004, 266],
           'eye_left': [823, 737], 'eye_right': [1168, 737], 'chin': [1004, 1311],
           'collar_left': [651, 1454], 'collar_gap_viewer_right': [1400, 1495],
           'implant_left_temple': [645, 675], 'implant_right_temple': [1393, 690],
           'rear_neck': [1024, 1430]}


def entries(ids, witness):
    assets = {}
    for tid in ids:
        traits = trait_ids_for(witness[tid])
        special = special_scene(tid)
        slots = [special] if special else [(s, candidate_paths(s, traits)[0]) for s in LAYER_STACK
                                          if s.required and not skip_reason(s, traits)]
        for slot, path in slots:
            rel = str(path.relative_to(ROOT))
            record = assets.setdefault(rel, {'path': rel, 'slot': slot.slot, 'z': slot.z,
                'trait_ids': set(), 'token_ids': [], 'contexts': [],
                'canvas': [2048, 2048], 'color_space': 'sRGB', 'format': 'PNG',
                'alpha': 'opaque 255' if slot.slot in ('background', 'authored_scene') else 'straight RGBA; zero RGB at alpha zero; no matte',
                'anchors_px': ANCHORS,
                'safe_bounds_px': [0, 0, 2048, 2048] if slot.slot in ('background', 'authored_scene') else [164, 164, 1884, 2048],
                'avatar_safe_circle': {'center': [1024, 1024], 'radius': 860},
                'blend_mode': 'source_over', 'opacity': 1.0,
                'overlap_requirements': 'Keep face/collar opening in avatar circle; break collar on viewer-right. Follow PRODUCTION-VISUAL-SPEC.md for slot-specific occlusion. No untested generic anatomy.',
                'permitted_neighbor_layers': set()})
            record['token_ids'].append(tid)
            record['contexts'].append({'token_id': tid, 'traits': traits})
            record['trait_ids'].update(traits.values() if special else [traits[c] for c in slot.categories])
            record['permitted_neighbor_layers'].update(str(p.relative_to(ROOT)) for s, p in slots if p != path)
    for r in assets.values():
        r['trait_ids'] = sorted(r['trait_ids'])
        r['permitted_neighbor_layers'] = sorted(r['permitted_neighbor_layers'])
        p = ROOT / r['path']
        r['present'] = p.is_file()
        r['status'] = 'CREATED_UNREVIEWED' if r['present'] else 'NEEDS ART GENERATION'
        r['sha256'] = hashlib.sha256(p.read_bytes()).hexdigest() if r['present'] else None
    return sorted(assets.values(), key=lambda r: (r['z'], r['path']))


def main():
    witness = load_design_witness()
    ids = [s['token_id'] for s in json.loads((COLLECTION/'manifests/test-batch-selection.json').read_text())['selected']]
    assets = entries(ids, witness)
    full = entries(sorted(witness), witness)
    present = sum(r['present'] for r in assets)
    plan = {'schema_version':'1.1', 'legacy_generic_inventory_rows':105,
        'legacy_inventory_status':'SUPERSEDED_FOR_PRODUCTION: included optional, NONE/INTACT and illegal cross-products; omitted anatomical contexts and authored scenes',
        'full_collection_required_export_paths':len(full),
        'batch_size':len(ids), 'token_ids':ids, 'unique_assets_required':len(assets),
        'present':present, 'missing':len(assets)-present,
        'count_definition':'Exact mandatory export paths for current resolver. Includes authored complete scenes. Optional depth/grain may be baked into background/source layers. Editable masters, depth masks and collision verification remain additional art-production deliverables.',
        'witness_sha256':hashlib.sha256((COLLECTION/'design-witness.csv').read_bytes()).hexdigest(),
        'by_slot':dict(Counter(r['slot'] for r in assets)), 'assets':assets}
    (COLLECTION/'manifests/test-batch-required-assets.json').write_text(json.dumps(plan,indent=2)+'\n')
    (COLLECTION/'manifests/collection-required-assets.json').write_text(json.dumps({
        'count':len(full), 'count_definition':plan['count_definition'],
        'paths':[{'path':r['path'], 'slot':r['slot'], 'token_ids':r['token_ids']} for r in full]},indent=2)+'\n')
    md = ['# Test-batch required assets', '', 'The historical **105** is a generic inventory row count, not a valid production minimum.', '',
        '```text', 'LEGACY COLLECTION INVENTORY: 105 (SUPERSEDED_FOR_PRODUCTION)',
        f'TOTAL COLLECTION REQUIRED EXPORT PATHS: {len(full)}',
        f'UNIQUE ASSETS REQUIRED FOR 20-BATCH: {len(assets)}', f'PRESENT: {present}',
        f'MISSING: {len(assets)-present}', '```', '', plan['count_definition'], '',
        'Counts use the actual selected witness rows and the same path resolver as the compositor. No edition rendering occurred.', '',
        '| Slot | Unique exports |', '| --- | ---: |']
    md += [f'| {s} | {n} |' for s,n in plan['by_slot'].items()]
    md += ['', '| Exact asset path | Trait IDs | Used by token IDs | Status |', '| --- | --- | --- | --- |']
    md += [f"| `{r['path']}` | {', '.join(r['trait_ids'])} | {', '.join(map(str,r['token_ids']))} | {r['status']} |" for r in assets]
    md += ['', 'All per-file anchors, alpha, bounds, overlap, blend, opacity and allowed neighboring paths are recorded in `manifests/test-batch-required-assets.json`. A present file is not an art approval.']
    (COLLECTION/'reports/test-batch-required-assets.md').write_text('\n'.join(md)+'\n')
    print(f'{len(assets)} batch exports: {present} present, {len(assets)-present} missing; {len(full)} full-collection exports')


if __name__ == '__main__':
    main()
