import { test } from 'node:test';
import assert from 'node:assert/strict';
import { OpenSeaClient, OpenSeaError } from '../src/client.ts';
import { testConfig, stubFetch } from './helpers.ts';

test('auth header: x-api-key is sent, Authorization only with an explicit bearer', async () => {
  const { restore, requests } = stubFetch(() => ({ body: { ok: true } }));
  const client = new OpenSeaClient(testConfig());
  await client.request('/chains');
  await client.request('/accounts/profile/settings', { method: 'PATCH', body: {}, bearer: 'JWT123' });
  restore();

  assert.equal(requests[0]!.headers['x-api-key'], 'TEST_KEY_do_not_log');
  assert.equal(requests[0]!.headers['authorization'], undefined);
  assert.equal(requests[1]!.headers['authorization'], 'Bearer JWT123');
  // the PAT / api key is never placed in Authorization
  assert.ok(!String(requests[1]!.headers['authorization']).includes('TEST_KEY'));
});

test('404 surfaces as OpenSeaError with status, not a retry storm', async () => {
  let calls = 0;
  const { restore } = stubFetch(() => {
    calls++;
    return { status: 404, body: { errors: ['not found'] } };
  });
  const client = new OpenSeaClient(testConfig());
  await assert.rejects(client.request('/collections/nope'), (e: unknown) => {
    assert.ok(e instanceof OpenSeaError);
    assert.equal((e as OpenSeaError).status, 404);
    return true;
  });
  restore();
  assert.equal(calls, 1, '4xx (non-429) must not be retried');
});

test('429 is retried with backoff, then succeeds', async () => {
  const { restore } = stubFetch((_u, n) =>
    n < 2 ? { status: 429, headers: { 'retry-after': '0' } } : { body: { recovered: true } },
  );
  const client = new OpenSeaClient(testConfig({ maxRetries: 3 }));
  const res = await client.request<{ recovered: boolean }>('/x');
  restore();
  assert.equal(res.recovered, true);
});

test('500 is retried then throws after maxRetries', async () => {
  let calls = 0;
  const { restore } = stubFetch(() => {
    calls++;
    return { status: 500, body: { errors: ['boom'] } };
  });
  const client = new OpenSeaClient(testConfig({ maxRetries: 2 }));
  await assert.rejects(client.request('/x'), OpenSeaError);
  restore();
  assert.equal(calls, 3, '1 try + 2 retries');
});

test('malformed JSON throws a clear error', async () => {
  const { restore } = stubFetch(() => ({ raw: '<html>not json' }));
  const client = new OpenSeaClient(testConfig());
  await assert.rejects(client.request('/x'), /Malformed JSON/);
  restore();
});

test('network outage retried then wrapped', async () => {
  const original = globalThis.fetch;
  let calls = 0;
  globalThis.fetch = (async () => {
    calls++;
    throw new Error('ECONNRESET');
  }) as typeof fetch;
  const client = new OpenSeaClient(testConfig({ maxRetries: 1 }));
  await assert.rejects(client.request('/x'), (e: unknown) => e instanceof OpenSeaError);
  globalThis.fetch = original;
  assert.equal(calls, 2);
});

test('rate-limit headers are observed from the response', async () => {
  const { restore } = stubFetch(() => ({
    body: {},
    headers: { 'x-ratelimit-remaining': '7', 'x-ratelimit-limit': '120' },
  }));
  const client = new OpenSeaClient(testConfig());
  await client.request('/x');
  restore();
  assert.equal(client.rate.snapshot.remaining, 7);
  assert.equal(client.rate.snapshot.limit, 120);
});

test('identical in-flight GETs are de-duplicated', async () => {
  let calls = 0;
  const { restore } = stubFetch(async () => {
    calls++;
    await new Promise((r) => setTimeout(r, 20));
    return { body: { n: calls } };
  });
  const client = new OpenSeaClient(testConfig());
  const [a, b] = await Promise.all([
    client.request<{ n: number }>('/same'),
    client.request<{ n: number }>('/same'),
  ]);
  restore();
  assert.equal(a.n, b.n);
  assert.equal(calls, 1);
});
