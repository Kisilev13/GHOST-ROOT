/**
 * NFT reads + sample-identity / trait validation (task sections 19–20).
 * Paths verified live 2026-09-08:
 *   GET /collection/{slug}/nfts        -> { nfts: [...], next }
 *   GET /chain/{chain}/account/{addr}/nfts
 *   GET /chain/{chain}/nfts/{identifier}  (EVM) — Solana items resolve by mint.
 */

import type { OpenSeaClient } from './client.ts';
import { OpenSeaError } from './client.ts';
import type { Nft, NftTrait } from './types.ts';

export async function listCollectionNfts(
  client: OpenSeaClient,
  slug: string,
  opts: { limit?: number; next?: string } = {},
): Promise<{ nfts: Nft[]; next: string | null }> {
  return client.request(`/collection/${encodeURIComponent(slug)}/nfts`, {
    query: { limit: Math.min(200, opts.limit ?? 50), next: opts.next },
  });
}

/** Walk the whole collection in pages. Never one-request-per-item (task 18). */
export async function allCollectionNfts(
  client: OpenSeaClient,
  slug: string,
  cap = 3333,
): Promise<Nft[]> {
  const out: Nft[] = [];
  let next: string | undefined;
  do {
    const page = await listCollectionNfts(client, slug, { limit: 200, next });
    out.push(...page.nfts);
    next = page.next ?? undefined;
  } while (next && out.length < cap);
  return out;
}

export async function getNft(
  client: OpenSeaClient,
  chain: string,
  identifier: string,
): Promise<Nft | null> {
  try {
    const res = await client.request<{ nft: Nft }>(
      `/chain/${encodeURIComponent(chain)}/nfts/${encodeURIComponent(identifier)}`,
    );
    return res.nft ?? null;
  } catch (err) {
    if (err instanceof OpenSeaError && err.status === 404) return null;
    throw err;
  }
}

// ---- validation ------------------------------------------------------------

/** Canonical GHOST//ROOT trait categories (collection/metadata/schema.json). */
export const EXPECTED_TRAIT_TYPES = [
  'Entity',
  'Access',
  'Architecture',
  'Face Material',
  'Eyes',
  'Interface',
  'Implant',
  'Mantle',
  'Background',
  'Signal',
  'Origin Corruption',
  'State',
  'Rarity Band',
] as const;

export interface NftFinding {
  identifier: string;
  name: string | null;
  problems: string[];
  ok: boolean;
}

const NAME_RE = /^GHOST\/\/\d{4}$/;

export function validateNft(nft: Nft, sourceUrlHost = 'ghostroot.site'): NftFinding {
  const problems: string[] = [];

  if (!nft.name) problems.push('missing name');
  else if (!NAME_RE.test(nft.name)) problems.push(`name "${nft.name}" != GHOST//NNNN`);

  if (!nft.description) problems.push('missing description');
  if (!nft.display_image_url && !nft.image_url) problems.push('no image URI');
  if (!nft.metadata_url) problems.push('no metadata_url exposed by OpenSea');
  if (nft.is_disabled) problems.push('item disabled on OpenSea');

  const traits = nft.traits ?? [];
  const present = new Set(traits.map((t) => t.trait_type));
  for (const want of EXPECTED_TRAIT_TYPES) {
    if (!present.has(want)) problems.push(`missing trait: ${want}`);
  }
  for (const t of traits) {
    if (t.value === null || t.value === undefined || String(t.value).trim() === '') {
      problems.push(`null value for trait ${t.trait_type}`);
    }
  }
  // duplicate trait_type
  const counts = new Map<string, number>();
  for (const t of traits) counts.set(t.trait_type, (counts.get(t.trait_type) ?? 0) + 1);
  for (const [k, n] of counts) if (n > 1) problems.push(`duplicate trait_type ${k} (x${n})`);

  return {
    identifier: nft.identifier,
    name: nft.name ?? null,
    problems,
    ok: problems.length === 0,
  };
}

export interface TraitAudit {
  trait_type: string;
  values: string[];
  issues: string[];
}

/** Aggregate trait ingestion across a sample (case / null / rarity consistency). */
export function auditTraits(nfts: Nft[]): TraitAudit[] {
  const byType = new Map<string, Map<string, number>>();
  for (const nft of nfts) {
    for (const t of nft.traits ?? []) {
      const m = byType.get(t.trait_type) ?? new Map<string, number>();
      m.set(String(t.value), (m.get(String(t.value)) ?? 0) + 1);
      byType.set(t.trait_type, m);
    }
  }
  const out: TraitAudit[] = [];
  for (const [type, values] of byType) {
    const issues: string[] = [];
    const keys = [...values.keys()];
    // case collisions: same value differing only by case
    const lower = new Map<string, string[]>();
    for (const k of keys) {
      const arr = lower.get(k.toLowerCase()) ?? [];
      arr.push(k);
      lower.set(k.toLowerCase(), arr);
    }
    for (const [, variants] of lower) {
      if (variants.length > 1) issues.push(`case variants: ${variants.join(' / ')}`);
    }
    if (!(EXPECTED_TRAIT_TYPES as readonly string[]).includes(type)) {
      issues.push('trait_type not in GHOST//ROOT vocabulary');
    }
    out.push({ trait_type: type, values: keys.sort(), issues });
  }
  for (const want of EXPECTED_TRAIT_TYPES) {
    if (!byType.has(want)) out.push({ trait_type: want, values: [], issues: ['absent from sample'] });
  }
  return out;
}

export function traitList(nft: Nft): NftTrait[] {
  return nft.traits ?? [];
}
