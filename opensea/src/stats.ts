/**
 * Collection stats — normalised so the site never has to show a fake zero.
 * Missing figures come back as `null`; the UI renders "—" / "NO MARKET DATA".
 */

import type { OpenSeaClient } from './client.ts';
import { OpenSeaError } from './client.ts';
import type { CollectionStats } from './types.ts';

export interface NormalisedStats {
  floor_price: number | null;
  floor_symbol: string | null;
  volume_total: number | null;
  volume_symbol: string | null;
  volume_24h: number | null;
  sales_total: number | null;
  sales_24h: number | null;
  num_owners: number | null;
  market_cap: number | null;
  source: 'opensea' | 'none';
  fetched_at: string;
}

export const EMPTY_STATS: NormalisedStats = {
  floor_price: null,
  floor_symbol: null,
  volume_total: null,
  volume_symbol: null,
  volume_24h: null,
  sales_total: null,
  sales_24h: null,
  num_owners: null,
  market_cap: null,
  source: 'none',
  fetched_at: new Date(0).toISOString(),
};

export async function getStats(
  client: OpenSeaClient,
  slug: string,
): Promise<NormalisedStats> {
  let raw: CollectionStats | null;
  try {
    raw = await client.request<CollectionStats>(
      `/collections/${encodeURIComponent(slug)}/stats`,
    );
  } catch (err) {
    if (err instanceof OpenSeaError && err.status === 404) {
      return { ...EMPTY_STATS, fetched_at: new Date().toISOString() };
    }
    throw err;
  }
  return normalise(raw);
}

export function normalise(raw: CollectionStats | null | undefined): NormalisedStats {
  const now = new Date().toISOString();
  if (!raw || !raw.total) return { ...EMPTY_STATS, fetched_at: now };

  const day =
    raw.intervals?.find((i) => i.interval === 'one_day') ??
    raw.intervals?.find((i) => /day|24/i.test(i.interval));

  const num = (v: unknown): number | null =>
    typeof v === 'number' && Number.isFinite(v) ? v : null;

  return {
    floor_price: num(raw.total.floor_price),
    floor_symbol: raw.total.floor_price_symbol ?? null,
    volume_total: num(raw.total.volume),
    volume_symbol: raw.total.volume_symbol ?? day?.volume_symbol ?? null,
    volume_24h: num(day?.volume),
    sales_total: num(raw.total.sales),
    sales_24h: num(day?.sales),
    num_owners: num(raw.total.num_owners),
    market_cap: num(raw.total.market_cap),
    source: 'opensea',
    fetched_at: now,
  };
}
