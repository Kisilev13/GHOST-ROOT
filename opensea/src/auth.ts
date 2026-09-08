/**
 * Authentication helpers.
 *
 * Two tiers, per the current OpenSea model (docs.opensea.io/reference/auth):
 *
 *  1. PUBLIC READS  — `x-api-key` header only. Everything GHOST//ROOT needs for
 *     discovery, verification, stats, activity and NFT validation is in this tier.
 *
 *  2. WALLET-SCOPED WRITES — profile edits, collection "about" page edits.
 *     Flow:  SIWX session  ->  scoped Personal Access Token (PAT)  ->  short-lived
 *     wallet JWT  ->  `Authorization: Bearer <jwt>`.
 *     The PAT is NEVER sent in Authorization and NEVER stored in WordPress.
 *     Interactive wallet signing is a human step — this toolkit does not hold a
 *     private key or seed phrase and will not sign on your behalf.
 *
 * `discoverScopes()` and `exchangeScopedToken()` let an operator inspect what a
 * PAT can do and mint a JWT for a single sync run, without hard-coding scope
 * strings that OpenSea may change.
 */

import type { OpenSeaClient } from './client.ts';
import { redact } from './config.ts';

export interface ScopeInfo {
  scope: string;
  description?: string;
  endpoints?: string[];
}

/** GET /auth/scopes — the live scope catalogue. Requires only the API key. */
export async function discoverScopes(client: OpenSeaClient): Promise<ScopeInfo[]> {
  const res = await client.request<{ scopes?: ScopeInfo[] } | ScopeInfo[]>('/auth/scopes', {
    noRetry: true,
  });
  return Array.isArray(res) ? res : (res.scopes ?? []);
}

export interface WalletJwt {
  token: string;
  expiresAt: number | null;
  scopes: string[];
}

/**
 * Exchange a scoped PAT for a short-lived wallet JWT.
 * `scopedToken` comes from the environment only (OPENSEA_SCOPED_TOKEN).
 */
export async function exchangeScopedToken(
  client: OpenSeaClient,
  scopedToken: string,
  wantScopes: string[] = [],
): Promise<WalletJwt> {
  if (!scopedToken) {
    throw new Error(
      'No OPENSEA_SCOPED_TOKEN set. A wallet-scoped mutation needs a PAT created ' +
        'through an interactive SIWX session on opensea.io. This is a human step.',
    );
  }
  const body: Record<string, unknown> = { token: scopedToken };
  if (wantScopes.length) body.scopes = wantScopes;

  const res = await client.request<{
    access_token?: string;
    token?: string;
    expires_at?: number;
    expires_in?: number;
    scopes?: string[];
  }>('/auth/token', { method: 'POST', body, noRetry: true });

  const token = res.access_token ?? res.token ?? '';
  if (!token) throw new Error('Scoped-token exchange returned no JWT');

  const expiresAt = res.expires_at
    ? res.expires_at * 1000
    : res.expires_in
      ? Date.now() + res.expires_in * 1000
      : null;

  return { token, expiresAt, scopes: res.scopes ?? [] };
}

/**
 * Verify who the current credential authenticates as, before any mutation.
 * Returns the account tied to the JWT (or null if only an API key is present).
 */
export async function whoami(
  client: OpenSeaClient,
  bearer?: string,
): Promise<{ address?: string; username?: string | null } | null> {
  if (!bearer) return null;
  try {
    const res = await client.request<{ address?: string; username?: string | null }>(
      '/auth/whoami',
      { bearer, noRetry: true },
    );
    return res;
  } catch {
    return null;
  }
}

export function describeCredential(apiKey: string, scopedToken: string): string {
  const parts = [`api_key=${apiKey ? redact(apiKey) : 'MISSING'}`];
  parts.push(`scoped_token=${scopedToken ? redact(scopedToken) : 'none'}`);
  return parts.join(' ');
}
