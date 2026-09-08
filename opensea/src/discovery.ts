/**
 * Slug discovery — resolve the OpenSea slug for the *authoritative* Solana
 * collection. Never picks by name alone (task section 9–10).
 *
 * Strategy, in order:
 *   1. If OPENSEA_COLLECTION_SLUG is set, use it (still verified downstream).
 *   2. If SOLANA_COLLECTION_ADDRESS is set, resolve slug from the on-chain
 *      address via /chain/solana/contract/{address} then /collections.
 *   3. Fall back to search + name candidates, returning them as *unconfirmed*.
 */

import type { OpenSeaClient } from './client.ts';
import { OpenSeaError } from './client.ts';
import type { Config } from './config.ts';
import type { CollectionDetailed } from './types.ts';
import { getCollection } from './collection.ts';

export interface DiscoveryResult {
  slug: string | null;
  method: 'configured' | 'address' | 'search' | 'none';
  confirmed: boolean;
  candidates: { slug: string; name: string; chain: string; reason: string }[];
  notes: string[];
}

export async function discoverSlug(
  client: OpenSeaClient,
  cfg: Config,
): Promise<DiscoveryResult> {
  const notes: string[] = [];
  const candidates: DiscoveryResult['candidates'] = [];

  // 1. configured
  if (cfg.collectionSlug) {
    const col = await getCollection(client, cfg.collectionSlug);
    if (col) {
      return {
        slug: cfg.collectionSlug,
        method: 'configured',
        confirmed: false,
        candidates: [
          { slug: col.collection, name: col.name, chain: col.contracts?.[0]?.chain ?? '?', reason: 'from OPENSEA_COLLECTION_SLUG' },
        ],
        notes: ['Slug came from config — still run full identity verification.'],
      };
    }
    notes.push(`Configured slug "${cfg.collectionSlug}" 404s on OpenSea.`);
  }

  // 2. by on-chain address
  if (cfg.solana.collectionAddress) {
    const bySlug = await resolveByAddress(client, cfg.chain, cfg.solana.collectionAddress, notes);
    if (bySlug) {
      return {
        slug: bySlug.collection,
        method: 'address',
        confirmed: true, // address match is the strong signal
        candidates: [
          { slug: bySlug.collection, name: bySlug.name, chain: cfg.chain, reason: 'contract address match' },
        ],
        notes,
      };
    }
    notes.push(
      `No OpenSea collection is indexed for ${cfg.chain} address ${cfg.solana.collectionAddress}.`,
    );
  } else {
    notes.push(
      'SOLANA_COLLECTION_ADDRESS is not set — the collection has not been deployed, ' +
        'so OpenSea cannot have indexed it. Status: AWAITING_SOLANA_COLLECTION_DEPLOYMENT.',
    );
  }

  // 3. search / name candidates (UNCONFIRMED)
  for (const q of ['ghost root', 'ghostroot', cfg.facts.name]) {
    try {
      const res = await client.request<{ results?: { collection?: string; name?: string; chain?: string }[] }>(
        '/search',
        { query: { query: q } },
      );
      for (const r of res.results ?? []) {
        if (r.collection) {
          candidates.push({
            slug: r.collection,
            name: r.name ?? '?',
            chain: r.chain ?? '?',
            reason: `search "${q}"`,
          });
        }
      }
    } catch {
      /* search is best-effort */
    }
  }
  for (const slug of ['ghost-root', 'ghostroot', 'ghost-root-network', 'ghostroot-official']) {
    try {
      const col = await getCollection(client, slug);
      if (col) {
        candidates.push({
          slug: col.collection,
          name: col.name,
          chain: col.contracts?.[0]?.chain ?? '?',
          reason: 'name-candidate slug exists',
        });
      }
    } catch (err) {
      if (!(err instanceof OpenSeaError && err.status === 404)) throw err;
    }
  }

  if (candidates.length) {
    notes.push(
      'Name-based candidates found but NOT confirmed. A candidate is only the ' +
        'GHOST//ROOT collection if chain === solana AND contract address === ' +
        'SOLANA_COLLECTION_ADDRESS. Do not mutate any of these.',
    );
  }

  return { slug: null, method: candidates.length ? 'search' : 'none', confirmed: false, candidates, notes };
}

async function resolveByAddress(
  client: OpenSeaClient,
  chain: string,
  address: string,
  notes: string[],
): Promise<CollectionDetailed | null> {
  // /chain/{chain}/contract/{address} returns { collection: "<slug>", ... }
  try {
    const c = await client.request<{ collection?: string }>(
      `/chain/${encodeURIComponent(chain)}/contract/${encodeURIComponent(address)}`,
      { noRetry: true },
    );
    if (c.collection) return getCollection(client, c.collection);
  } catch (err) {
    if (err instanceof OpenSeaError && err.status === 404) return null;
    notes.push(`contract lookup error: ${(err as Error).message}`);
  }
  return null;
}
