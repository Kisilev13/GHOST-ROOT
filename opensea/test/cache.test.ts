import { test } from 'node:test';
import assert from 'node:assert/strict';
import { rmSync } from 'node:fs';
import { Cache } from '../src/cache.ts';

const DIR = '/tmp/ghostroot-opensea-cache-test';

test('write/read round-trips and reports freshness + age', () => {
  rmSync(DIR, { recursive: true, force: true });
  const c = new Cache(DIR);
  c.write('k', { v: 1 }, 100);
  const hit = c.read<{ v: number }>('k');
  assert.equal(hit?.value.v, 1);
  assert.equal(hit?.fresh, true);
  assert.ok(hit!.age >= 0);
});

test('wrap() serves fresh cache without calling fetcher', async () => {
  rmSync(DIR, { recursive: true, force: true });
  const c = new Cache(DIR);
  let calls = 0;
  const fetcher = async () => {
    calls++;
    return { n: calls };
  };
  await c.wrap('x', 100, fetcher);
  const second = await c.wrap('x', 100, fetcher);
  assert.equal(second.value.n, 1);
  assert.equal(calls, 1);
});

test('wrap() falls back to STALE cache when the fetcher throws (outage)', async () => {
  rmSync(DIR, { recursive: true, force: true });
  const c = new Cache(DIR);
  c.write('y', { stale: true }, -1); // already expired
  const res = await c.wrap<{ stale: boolean }>('y', 100, async () => {
    throw new Error('OpenSea down');
  });
  assert.equal(res.value.stale, true);
  assert.equal(res.fresh, false);
});

test('wrap() rethrows when there is no cache at all', async () => {
  rmSync(DIR, { recursive: true, force: true });
  const c = new Cache(DIR);
  await assert.rejects(
    c.wrap('z', 100, async () => {
      throw new Error('down');
    }),
  );
});
