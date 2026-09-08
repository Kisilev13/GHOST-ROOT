import { test } from 'node:test';
import assert from 'node:assert/strict';
import { OpenSeaClient } from '../src/client.ts';
import { getHolders } from '../src/collection.ts';
import { allCollectionNfts } from '../src/nfts.ts';
import { testConfig, stubFetch } from './helpers.ts';

test('getHolders follows the `next` cursor until exhausted', async () => {
  const { restore, requests } = stubFetch((url) => {
    const u = new URL(url);
    const cursor = u.searchParams.get('next');
    if (!cursor) return { body: { holders: [{ address: 'a', quantity: 2 }], next: 'CUR1' } };
    if (cursor === 'CUR1') return { body: { holders: [{ address: 'b', quantity: 1 }], next: 'CUR2' } };
    return { body: { holders: [{ address: 'c', quantity: 1 }], next: null } };
  });
  const client = new OpenSeaClient(testConfig());
  const holders = await getHolders(client, 'x', 10);
  restore();
  assert.deepEqual(holders.map((h) => h.address), ['a', 'b', 'c']);
  assert.equal(requests.length, 3);
});

test('allCollectionNfts pages in batches, never one request per item', async () => {
  let page = 0;
  const { restore, requests } = stubFetch(() => {
    page++;
    const nfts = Array.from({ length: 200 }, (_, i) => ({
      identifier: String((page - 1) * 200 + i + 1),
      collection: 'x',
      contract: 'c',
      token_standard: 'metaplex_core',
    }));
    return { body: { nfts, next: page < 3 ? `p${page}` : null } };
  });
  const client = new OpenSeaClient(testConfig());
  const all = await allCollectionNfts(client, 'x', 3333);
  restore();
  assert.equal(all.length, 600);
  assert.equal(requests.length, 3, '3 pages, not 600 requests');
});
