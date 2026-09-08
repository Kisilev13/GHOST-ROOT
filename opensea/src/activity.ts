/**
 * Collection activity (events) + best listings.
 * Path verified live 2026-09-08: GET /events/collection/{slug}
 *   -> { asset_events: [...], next: string|null }
 */

import type { OpenSeaClient } from './client.ts';
import { OpenSeaError } from './client.ts';
import type { AssetEvent, Listing } from './types.ts';

const EVENT_TYPES = ['sale', 'listing', 'offer', 'transfer', 'cancel'] as const;
export type EventType = (typeof EVENT_TYPES)[number];

export async function getActivity(
  client: OpenSeaClient,
  slug: string,
  opts: { types?: EventType[]; limit?: number } = {},
): Promise<AssetEvent[]> {
  const limit = Math.min(50, opts.limit ?? 25);
  const query: Record<string, string | number> = { limit };
  // OpenSea accepts repeated event_type params; the client flattens the last,
  // so request the common set and filter client-side when several are wanted.
  if (opts.types?.length === 1) query.event_type = opts.types[0]!;

  try {
    const page = await client.request<{ asset_events: AssetEvent[]; next: string | null }>(
      `/events/collection/${encodeURIComponent(slug)}`,
      { query },
    );
    let events = page.asset_events ?? [];
    if (opts.types && opts.types.length !== 1) {
      const set = new Set<string>(opts.types);
      events = events.filter((e) => set.has(e.event_type));
    }
    return events.slice(0, limit);
  } catch (err) {
    if (err instanceof OpenSeaError && err.status === 404) return [];
    throw err;
  }
}

export async function getBestListings(
  client: OpenSeaClient,
  slug: string,
  limit = 20,
): Promise<Listing[]> {
  try {
    const page = await client.request<{ listings: Listing[]; next?: string | null }>(
      `/listings/collection/${encodeURIComponent(slug)}/best`,
      { query: { limit } },
    );
    return page.listings ?? [];
  } catch (err) {
    if (err instanceof OpenSeaError && err.status === 404) return [];
    throw err;
  }
}

export interface ActivityRow {
  type: string;
  identity: string | null;
  price: string | null;
  from: string | null;
  to: string | null;
  timestamp: string | null;
  url: string | null;
}

export function toRows(events: AssetEvent[]): ActivityRow[] {
  return events.map((e) => ({
    type: e.event_type.toUpperCase(),
    identity: e.nft?.name ?? e.nft?.identifier ?? null,
    price: e.payment
      ? `${fmtAmount(e.payment.quantity, e.payment.decimals)} ${e.payment.symbol}`
      : null,
    from: e.from_address ?? e.seller ?? null,
    to: e.to_address ?? e.buyer ?? null,
    timestamp: e.event_timestamp ? new Date(e.event_timestamp * 1000).toISOString() : null,
    url: e.nft?.opensea_url ?? null,
  }));
}

function fmtAmount(raw: string, decimals: number): string {
  try {
    const v = Number(BigInt(raw)) / 10 ** decimals;
    return v < 1 ? v.toFixed(4).replace(/0+$/, '') : v.toFixed(3);
  } catch {
    return raw;
  }
}
