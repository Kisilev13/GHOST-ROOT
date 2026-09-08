/**
 * Test scaffolding: a deterministic fetch stub so nothing in the suite touches
 * the network or the real API key.
 */

import type { Config } from '../src/config.ts';

export function testConfig(over: Partial<Config> = {}): Config {
  return {
    apiKey: 'TEST_KEY_do_not_log',
    apiKeyExpiresAt: '',
    scopedToken: '',
    baseUrl: 'https://api.opensea.io/api/v2',
    chain: 'solana',
    env: 'production',
    collectionSlug: '',
    collectionId: '',
    solana: {
      collectionAddress: '',
      candyMachineAddress: '',
      updateAuthority: '',
      treasury: '',
      metadataRoot: '',
      artworkRoot: '',
      deploymentHash: '',
    },
    facts: { name: 'GHOST//ROOT', supply: 3333, projectUrl: 'https://ghostroot.site', royaltyBps: 500 },
    cache: {
      dir: '/tmp/ghostroot-opensea-test-cache',
      ttl: { collection: 3600, stats: 300, floor: 120, activity: 60, traits: 21600, verification: 86400 },
    },
    timeoutMs: 5000,
    maxRetries: 2,
    logLevel: 'error',
    ...over,
  };
}

export interface StubResponse {
  status?: number;
  body?: unknown;
  raw?: string;
  headers?: Record<string, string>;
}

export interface RecordedRequest {
  url: string;
  method: string;
  headers: Record<string, string>;
  body: unknown;
}

/**
 * Install a fetch stub. `route` maps a substring of the URL to either a single
 * response or an array consumed one-per-call (to exercise retry paths).
 */
export function stubFetch(
  route: (url: string, calls: number) => StubResponse | Promise<StubResponse>,
): {
  restore: () => void;
  requests: RecordedRequest[];
} {
  const original = globalThis.fetch;
  const requests: RecordedRequest[] = [];
  let calls = 0;

  globalThis.fetch = (async (input: string | URL | Request, init?: RequestInit) => {
    const url = typeof input === 'string' ? input : input.toString();
    calls += 1;
    const headers: Record<string, string> = {};
    for (const [k, v] of Object.entries((init?.headers as Record<string, string>) ?? {})) {
      headers[k.toLowerCase()] = v;
    }
    requests.push({
      url,
      method: init?.method ?? 'GET',
      headers,
      body: init?.body ? JSON.parse(String(init.body)) : undefined,
    });
    const r = await route(url, calls);
    const text = r.raw ?? JSON.stringify(r.body ?? {});
    return new Response(text, {
      status: r.status ?? 200,
      headers: {
        'content-type': 'application/json',
        'x-ratelimit-limit': '120',
        'x-ratelimit-remaining': '119',
        'x-ratelimit-reset': String(Math.floor(Date.now() / 1000) + 30),
        ...r.headers,
      },
    });
  }) as typeof fetch;

  return { restore: () => void (globalThis.fetch = original), requests };
}
