#!/usr/bin/env python3
"""Build/check the production schema lock without regenerating the design witness."""
import argparse
import hashlib
import json
from dataclasses import asdict
from pathlib import Path

from asset_manifest import COLLECTION, LAYER_STACK, METADATA_ONLY_CATEGORIES

OUT = COLLECTION / 'manifests/canonical-trait-schema.json'


def build():
    read = lambda name: json.loads((COLLECTION / name).read_text())
    traits, config = read('traits.yaml'), read('collection.yaml')
    categories = {}
    for category in traits['categories']:
        cid = category['id']
        categories[cid] = dict(category, art_visible=cid not in METADATA_ONLY_CATEGORIES,
            metadata_only=cid in METADATA_ONLY_CATEGORIES,
            values=[t for t in traits['traits'] if t['category'] == cid])
    sources = ['traits.yaml', 'collection.yaml', 'genesis.yaml', 'genesis.md',
               'legendary-reservations.yaml', 'design-witness.csv', 'art-bible.md',
               'scripts/asset_manifest.py']
    # No guessed semantic aliases. Migration replaces origin traits by stable token ID.
    wp = {
        'entity': ('Entity', 'entity', ['HUMAN', 'SYNTHETIC', 'HOLLOW', 'SPECTER']),
        'access': ('Access', 'access', ['USER', 'OPERATOR', 'ADMIN', 'SYSTEM', 'ROOT']),
        'architecture': (None, None, []),
        'material': ('Face', 'face', ['PORCELAIN', 'CARBON', 'CHROME', 'RECONSTRUCTED', 'CERAMIC', 'BIO-SYNTH']),
        'eyes': ('Eyes', 'eyes', ['THERMAL', 'VOID', 'BIOMETRIC', 'FRACTURED', 'OPTICAL ARRAY', 'SIGNAL-BURN', 'NULL APERTURE', 'REVOCATION LENS', 'WITNESS ARRAY']),
        'interface': ('Mask', 'mask', ['RESPIRATOR', 'FORENSIC PLATE', 'SKELETAL INTERFACE', 'NULL MASK', 'NEURAL VEIL', 'NONE']),
        'implant': ('Implant', 'implant', ['NEURAL CABLE', 'ANTENNA', 'OPTICAL ARRAY', 'SPINAL BUS', 'SIGNAL CROWN', 'ROOT PORT']),
        'mantle': (None, None, []),
        'background': ('Background', 'background', ['SERVER', 'BLACKSITE', 'SATELLITE', 'VOID', 'ARCHIVE', 'COLD STORAGE', 'SIGNAL CHAMBER']),
        'signal': ('Signal', 'signal', ['STABLE', 'DEGRADED', 'INTERMITTENT', 'CORRUPTED', 'NULL', 'UNKNOWN']),
        'corruption': ('Corruption', 'corruption', ['GLITCH', 'BURN', 'SIGNAL LOSS', 'FRAGMENTATION', 'PACKET GHOSTING', 'MEMORY BLEED', 'NONE']),
    }
    mappings = {}
    for cid, (label, key, values) in wp.items():
        canonical_values = {v['display_name']: v['id'] for v in categories[cid]['values']}
        mappings[cid] = {'wordpress_label': label, 'wordpress_meta_key': key,
            'production_label': categories[cid]['display_name'], 'production_field': cid,
            'identical_values': {v: canonical_values[v] for v in values if v in canonical_values},
            'unmapped_legacy_values': [v for v in values if v not in canonical_values],
            'new_values': [v for v in canonical_values if v not in values],
            'migration': 'replace_from_design_witness_by_ghost_id; never infer token traits from legacy values'}
    return {
        'schema_version': '1.1', 'status': 'CANONICAL_PRODUCTION_SCHEMA_LOCK',
        'authority': 'design-witness.csv fixes token assignments; this generated lock defines their interpretation. Change sources only through a reviewed design version, then regenerate this lock.',
        'source_sha256': {p: hashlib.sha256((COLLECTION/p).read_bytes()).hexdigest() for p in sources},
        'supply': config['supply'], 'category_order': traits['category_order'], 'categories': categories,
        'art_visible_categories': [c for c in traits['category_order'] if c not in METADATA_ONLY_CATEGORIES],
        'metadata_only_categories': list(METADATA_ONLY_CATEGORIES),
        'metadata_only_decision': 'Access is historical privilege expressed indirectly by allowed hardware/topology. Signal is fixed origin metadata, not current telemetry; no extra signal paint pass. Visual importance is not a layer requirement.',
        'additional_metadata': {'State': {'values': config['states'], 'default': 'DORMANT', 'mutable': True},
                                'Rarity Band': {'derived_from': 'architecture', 'mutable': False}},
        'layer_order': [asdict(s) for s in LAYER_STACK],
        'incompatibility_rules': {'semantics': traits['rule_semantics'],
             'rules': {t['id']: {'requires': t['requires'], 'excludes': t['excludes']} for t in traits['traits']},
             'asset_rule': 'No generic anatomical fallback; NONE/INTACT omit assets. Non-empty trait layers are required. Socket/depth validation is an additional visual gate.'},
        'rarity_bands': config['rarity_bands'],
        'rarity_band_from_architecture': {b['architecture']: b['id'] for b in config['rarity_bands']},
        'genesis_overrides': {'policy': 'Complete individually authored scene, including environment, seam and final transform. Metadata MUST retain every fixed Genesis trait. No generic singular overlay.', 'records': read('genesis.yaml')['characters']},
        'legendary_overrides': {'policy': 'Complete authored scenes selected by reserved token ID. Witness vectors remain fixed for this prototype; reservation fields remain enforced. Direction/approval pending, never replace the witness with random art traits.', 'records': read('legendary-reservations.yaml')['reservations']},
        'deprecated_wordpress_aliases': {'status': 'SUPERSEDED_FOR_PRODUCTION', 'categories': mappings,
            'rarity_band': {'old': {'STANDARD':2700,'CORRUPTED':500,'ADMIN':100,'ROOT':30,'GENESIS':3},
                'mapping': None, 'migration': 'derive canonical band from each witness row architecture; display labels COMMON/UNCOMMON/RARE/EPIC/LEGENDARY/1/1. Genesis may remain a special-edition badge, not an alternate rarity.'},
            'archetype': 'retain legacy lore only; never scored or imported as a canonical trait',
            'state': 'preserve separately; do not reset dynamic state during origin-trait migration',
            'deployment_status': 'NOT_MIGRATED; website still uses historical vocabulary'},
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--check', action='store_true')
    args = parser.parse_args()
    text = json.dumps(build(), indent=2) + '\n'
    if args.check:
        if not OUT.exists() or OUT.read_text() != text:
            raise SystemExit('STALE canonical trait schema; inspect source drift before regenerating')
        print('PASS canonical schema matches pinned design sources')
    else:
        OUT.write_text(text)
        print('Wrote', OUT.relative_to(COLLECTION))


if __name__ == '__main__':
    main()
