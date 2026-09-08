import { test } from 'node:test';
import assert from 'node:assert/strict';
import { normalise, EMPTY_STATS } from '../src/stats.ts';
import { validateNft, auditTraits, EXPECTED_TRAIT_TYPES } from '../src/nfts.ts';
import { toRows } from '../src/activity.ts';
import type { Nft } from '../src/types.ts';

test('stats: missing data never renders as zero', () => {
  const s = normalise(null);
  assert.equal(s.floor_price, null);
  assert.equal(s.volume_total, null);
  assert.equal(s.source, 'none');
});

test('stats: partial payload keeps present numbers, nulls the rest', () => {
  const s = normalise({
    total: { volume: 37.4, volume_symbol: 'SOL', sales: 12, num_owners: 1204, floor_price: null },
    intervals: [{ interval: 'one_day', sales: 3, volume: 4.1, volume_symbol: 'SOL' }],
  });
  assert.equal(s.volume_total, 37.4);
  assert.equal(s.volume_24h, 4.1);
  assert.equal(s.sales_24h, 3);
  assert.equal(s.num_owners, 1204);
  assert.equal(s.floor_price, null);
  assert.equal(s.source, 'opensea');
});

test('EMPTY_STATS is fully null / none', () => {
  for (const [k, v] of Object.entries(EMPTY_STATS)) {
    if (k === 'source') assert.equal(v, 'none');
    else if (k === 'fetched_at') assert.equal(typeof v, 'string');
    else assert.equal(v, null, `${k} should be null`);
  }
});

const base: Nft = {
  identifier: '1842',
  collection: 'ghost-root-sol',
  contract: 'GhostR00t',
  token_standard: 'metaplex_core',
  name: 'GHOST//1842',
  description: 'Recovered identity.',
  display_image_url: 'https://img/1842.png',
  metadata_url: 'https://ghostroot.site/meta/1842.json',
  traits: EXPECTED_TRAIT_TYPES.map((t) => ({ trait_type: t, value: 'X' })),
};

test('validateNft: a well-formed identity passes', () => {
  const f = validateNft(base);
  assert.equal(f.ok, true, f.problems.join('; '));
});

test('validateNft: flags bad name, missing traits, null values, duplicates', () => {
  const bad: Nft = {
    ...base,
    name: 'Ghost 1842',
    traits: [
      { trait_type: 'Entity', value: 'HUMAN' },
      { trait_type: 'Entity', value: 'SYNTHETIC' },
      { trait_type: 'Access', value: '' },
    ],
  };
  const f = validateNft(bad);
  assert.equal(f.ok, false);
  assert.ok(f.problems.some((p) => /name/.test(p)));
  assert.ok(f.problems.some((p) => /duplicate trait_type Entity/.test(p)));
  assert.ok(f.problems.some((p) => /null value for trait Access/.test(p)));
  assert.ok(f.problems.some((p) => /missing trait: State/.test(p)));
});

test('auditTraits: detects case variants and out-of-vocab trait types', () => {
  const nfts: Nft[] = [
    { ...base, traits: [{ trait_type: 'Entity', value: 'HUMAN' }, { trait_type: 'Vibe', value: 'cool' }] },
    { ...base, traits: [{ trait_type: 'Entity', value: 'human' }] },
  ];
  const audit = auditTraits(nfts);
  const entity = audit.find((a) => a.trait_type === 'Entity')!;
  assert.ok(entity.issues.some((i) => /case variants/.test(i)));
  const vibe = audit.find((a) => a.trait_type === 'Vibe')!;
  assert.ok(vibe.issues.some((i) => /not in GHOST\/\/ROOT vocabulary/.test(i)));
});

test('activity: toRows formats price from raw payment quantity', () => {
  const rows = toRows([
    {
      event_type: 'sale',
      event_timestamp: 1_757_000_000,
      seller: 'A',
      buyer: 'B',
      payment: { quantity: '420000000', token_address: 'So111', decimals: 9, symbol: 'SOL' },
      nft: { identifier: '31', name: 'GHOST//0031', image_url: null, opensea_url: 'https://opensea.io/x' },
    },
  ]);
  assert.equal(rows[0]!.type, 'SALE');
  assert.equal(rows[0]!.identity, 'GHOST//0031');
  assert.match(rows[0]!.price!, /SOL$/);
  assert.ok(rows[0]!.timestamp!.startsWith('20'));
});
