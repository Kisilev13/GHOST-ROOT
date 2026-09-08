import { test } from 'node:test';
import assert from 'node:assert/strict';
import { OpenSeaClient } from '../src/client.ts';
import { verifyCollection } from '../src/verify.ts';
import { testConfig, stubFetch } from './helpers.ts';

const SOLANA_ADDR = 'GhostR00tCoLLeCtionMintAddr1111111111111111';

function collectionBody(over: Record<string, unknown> = {}) {
  return {
    collection: 'ghost-root-sol',
    name: 'GHOST//ROOT',
    description: '3,333 identities…',
    contracts: [{ address: SOLANA_ADDR, chain: 'solana' }],
    total_supply: 3333,
    project_url: 'https://ghostroot.site',
    fees: [
      { fee: 1.0, recipient: '0x0000a26b00c1f0df003000390027140000faa719', required: true },
      { fee: 5.0, recipient: 'GhostTreasury', required: false },
    ],
    ...over,
  };
}

test('AWAITING when no Solana address configured', async () => {
  const client = new OpenSeaClient(testConfig());
  const r = await verifyCollection(client, testConfig(), null);
  assert.equal(r.state, 'AWAITING_SOLANA_COLLECTION_DEPLOYMENT');
});

test('NOT_INDEXED when address set but no slug resolves', async () => {
  const cfg = testConfig({ solana: { ...testConfig().solana, collectionAddress: SOLANA_ADDR } });
  const client = new OpenSeaClient(cfg);
  const r = await verifyCollection(client, cfg, null);
  assert.equal(r.state, 'NOT_INDEXED');
});

test('VERIFIED when chain + address + name + supply + url all match', async () => {
  const cfg = testConfig({ solana: { ...testConfig().solana, collectionAddress: SOLANA_ADDR } });
  const { restore } = stubFetch((url) => {
    if (url.includes('/nfts')) return { body: { nfts: [{ identifier: '1', collection: 'ghost-root-sol' }], next: null } };
    return { body: collectionBody() };
  });
  const client = new OpenSeaClient(cfg);
  const r = await verifyCollection(client, cfg, 'ghost-root-sol');
  restore();
  assert.equal(r.state, 'VERIFIED');
});

test('MISMATCH when the slug is a different-chain collection (the ghost-root ETH trap)', async () => {
  const cfg = testConfig({ solana: { ...testConfig().solana, collectionAddress: SOLANA_ADDR } });
  const { restore } = stubFetch((url) => {
    if (url.includes('/nfts')) return { body: { nfts: [], next: null } };
    return {
      body: collectionBody({
        name: 'GHOST_ROOT',
        contracts: [{ address: '0x3ad41f5055926ff40d499196fc59f34e6f4230fb', chain: 'ethereum' }],
        total_supply: 19,
        project_url: '',
      }),
    };
  });
  const client = new OpenSeaClient(cfg);
  const r = await verifyCollection(client, cfg, 'ghost-root');
  restore();
  assert.equal(r.state, 'MISMATCH');
  const critical = r.checks.filter((c) => c.critical);
  assert.ok(critical.some((c) => c.field === 'chain' && !c.ok));
  assert.ok(critical.some((c) => c.field === 'collection_address' && !c.ok));
});

test('PARTIAL when collection matches but has no items indexed yet', async () => {
  const cfg = testConfig({ solana: { ...testConfig().solana, collectionAddress: SOLANA_ADDR } });
  const { restore } = stubFetch((url) => {
    if (url.includes('/nfts')) return { body: { nfts: [], next: null } };
    return { body: collectionBody() };
  });
  const client = new OpenSeaClient(cfg);
  const r = await verifyCollection(client, cfg, 'ghost-root-sol');
  restore();
  assert.equal(r.state, 'PARTIAL');
});
