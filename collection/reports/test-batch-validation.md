# GHOST//ROOT — test-batch validation

_20 token IDs; see `manifests/test-batch-selection.json` for why each was chosen._

## Artwork

- 20/20 images present (0 missing; 20 required)
- issues: none
- exact duplicates: none
- near-duplicates (perceptual dhash, Hamming <= 4/81, flagged for human review, not auto-failed): [{'pair': [49, 366], 'hamming_distance': 1, 'note': 'flagged for human review, not auto-failed'}, {'pair': [49, 836], 'hamming_distance': 4, 'note': 'flagged for human review, not auto-failed'}]

## Metadata

- 20/20 valid JSON
- 20/20 pass schema (20 have the expected `image` failure — pending:// is not a real ipfs:// URI yet, by design)
- id mismatches: none
- name mismatches: none
- trait value mismatches vs design-witness.csv: none
- duplicate trait categories: none
- null required traits: none
- unexpected trait categories: none
- rarity band mismatches: none
- filesystem/secret leaks: none
- other issues: none

## Rarity

- legendary IDs in batch: [303, 803, 1503, 2803]
- legendary reservation consistency: OK
- band distribution: {1: '1/1', 2: '1/1', 3: '1/1', 4: 'COMMON', 10: 'COMMON', 49: 'COMMON', 97: 'COMMON', 112: 'UNCOMMON', 166: 'RARE', 192: 'EPIC', 303: 'LEGENDARY', 366: 'COMMON', 449: 'UNCOMMON', 493: 'RARE', 803: 'LEGENDARY', 836: 'EPIC', 1503: 'LEGENDARY', 1842: 'RARE', 2803: 'LEGENDARY', 3333: 'COMMON'}
