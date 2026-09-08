/**
 * Collection read + metadata-write helpers.
 *
 * Reads work with just the API key. The metadata write (`PATCH
 * /collections/{slug}/metadata`, scope `write:collections`) is provided but
 * ALWAYS runs through a dry-run diff first (see scripts/opensea-collection-sync.ts).
 */

import type { OpenSeaClient } from './client.ts';
import { OpenSeaError } from './client.ts';
import type { CollectionDetailed, Holder } from './types.ts';

export async function getCollection(
  client: OpenSeaClient,
  slug: string,
): Promise<CollectionDetailed | null> {
  try {
    return await client.request<CollectionDetailed>(`/collections/${encodeURIComponent(slug)}`);
  } catch (err) {
    if (err instanceof OpenSeaError && err.status === 404) return null;
    throw err;
  }
}

export async function getCollectionTraits(
  client: OpenSeaClient,
  slug: string,
): Promise<Record<string, unknown> | null> {
  try {
    return await client.request(`/collections/${encodeURIComponent(slug)}/traits`);
  } catch (err) {
    if (err instanceof OpenSeaError && err.status === 404) return null;
    throw err;
  }
}

export async function getHolders(
  client: OpenSeaClient,
  slug: string,
  limit = 50,
): Promise<Holder[]> {
  const out: Holder[] = [];
  let next: string | undefined;
  do {
    const page = await client.request<{ holders: Holder[]; next: string | null }>(
      `/collections/${encodeURIComponent(slug)}/holders`,
      { query: { limit, next } },
    );
    out.push(...(page.holders ?? []));
    next = page.next ?? undefined;
  } while (next && out.length < 1000);
  return out;
}

/** List collections on a chain (cursor-paginated). Used by discovery fallback. */
export async function listCollections(
  client: OpenSeaClient,
  chain: string,
  limit = 100,
  next?: string,
): Promise<{ collections: CollectionDetailed[]; next: string | null }> {
  return client.request(`/collections`, { query: { chain, limit, next } });
}

export interface CollectionMetadataPatch {
  about?: unknown;
  hero?: unknown;
  overview?: unknown;
  logo_image_token?: string;
}

/**
 * PATCH collection metadata. Requires a wallet JWT with `write:collections`.
 * Returns { success }. Caller MUST have shown a diff and confirmed authority.
 */
export async function patchCollectionMetadata(
  client: OpenSeaClient,
  slug: string,
  patch: CollectionMetadataPatch,
  bearer: string,
): Promise<{ success: boolean }> {
  return client.request(`/collections/${encodeURIComponent(slug)}/metadata`, {
    method: 'PATCH',
    body: patch,
    bearer,
    noRetry: true,
  });
}

/** The contract ref matching a given chain, if the collection has one. */
export function contractForChain(
  col: CollectionDetailed,
  chain: string,
): { address: string; chain: string } | null {
  return col.contracts?.find((c) => c.chain === chain) ?? null;
}
