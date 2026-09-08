/**
 * Collection identity verification (task section 10).
 *
 * An OpenSea result is only accepted as GHOST//ROOT when the BLOCKCHAIN
 * identifiers match — never on name similarity. Any critical mismatch produces
 * state MISMATCH and disables every downstream mutation.
 */

import type { OpenSeaClient } from './client.ts';
import type { Config } from './config.ts';
import type { CollectionDetailed, VerificationReport, VerificationCheck } from './types.ts';
import { getCollection, contractForChain } from './collection.ts';
import { listCollectionNfts } from './nfts.ts';

function check(
  field: string,
  expected: string,
  actual: string,
  ok: boolean,
  critical = true,
): VerificationCheck {
  return { field, expected, actual, ok, critical };
}

export async function verifyCollection(
  client: OpenSeaClient,
  cfg: Config,
  slug: string | null,
): Promise<VerificationReport> {
  const now = new Date().toISOString();
  const notes: string[] = [];

  // Pre-condition: is there even a deployed Solana collection to verify against?
  if (!cfg.solana.collectionAddress) {
    return {
      state: 'AWAITING_SOLANA_COLLECTION_DEPLOYMENT',
      slug,
      chain: cfg.chain,
      checked_at: now,
      checks: [],
      notes: [
        'SOLANA_COLLECTION_ADDRESS is empty. The Metaplex Core collection has not ' +
          'been deployed, so there is nothing for OpenSea to index yet.',
        'All API scaffolding is in place; verification will run automatically once ' +
          'the address is set and the collection is live.',
      ],
    };
  }

  if (!slug) {
    return {
      state: 'NOT_INDEXED',
      slug: null,
      chain: cfg.chain,
      checked_at: now,
      checks: [
        check('opensea_index', 'indexed', 'not found', false),
      ],
      notes: [
        `No OpenSea collection resolves to ${cfg.chain} address ${cfg.solana.collectionAddress}.`,
        'Next action: submit an indexing request via OpenSea support once the collection is minted.',
      ],
    };
  }

  const col = await getCollection(client, slug);
  if (!col) {
    return {
      state: 'ERROR',
      slug,
      chain: cfg.chain,
      checked_at: now,
      checks: [check('collection_fetch', '200', '404', false)],
      notes: [`Slug "${slug}" did not resolve on OpenSea at verification time.`],
    };
  }

  const checks: VerificationCheck[] = [];

  // 1. chain
  const ref = contractForChain(col, cfg.chain);
  checks.push(
    check('chain', cfg.chain, ref?.chain ?? col.contracts?.map((c) => c.chain).join(',') ?? 'none', !!ref),
  );

  // 2. contract / collection address
  const gotAddr = (ref?.address ?? '').toLowerCase();
  const wantAddr = cfg.solana.collectionAddress.toLowerCase();
  checks.push(check('collection_address', cfg.solana.collectionAddress, ref?.address ?? 'none', gotAddr === wantAddr));

  // 3. name
  checks.push(
    check('name', cfg.facts.name, col.name, normname(col.name) === normname(cfg.facts.name), false),
  );

  // 4. supply
  const supply = col.total_supply ?? col.unique_item_count ?? 0;
  checks.push(
    check(
      'supply',
      String(cfg.facts.supply),
      String(supply),
      supply === 0 || Math.abs(supply - cfg.facts.supply) <= cfg.facts.supply * 0.02,
      false,
    ),
  );

  // 5. project url
  const url = (col.project_url ?? '').replace(/\/$/, '');
  checks.push(
    check('project_url', cfg.facts.projectUrl, col.project_url ?? 'none', hostEq(url, cfg.facts.projectUrl), false),
  );

  // 6. sample NFT structure
  try {
    const { nfts } = await listCollectionNfts(client, slug, { limit: 3 });
    const sampleOk = nfts.length > 0 && nfts.every((n) => n.collection === slug);
    checks.push(check('sample_nfts', 'belong to collection', nfts.length ? 'ok' : 'none', sampleOk, false));
    if (!nfts.length) notes.push('Collection resolves but has no items indexed yet (PARTIAL).');
  } catch (err) {
    checks.push(check('sample_nfts', 'reachable', (err as Error).message, false, false));
  }

  // 7. royalty (informational — on-chain is source of truth, task section 14)
  const royaltyPctFromFees = (col.fees ?? [])
    .filter((f) => !f.recipient.startsWith('0x0000a26b')) // exclude OpenSea's own fee
    .reduce((s, f) => s + f.fee, 0);
  checks.push(
    check(
      'royalty_pct',
      `${cfg.facts.royaltyBps / 100}%`,
      royaltyPctFromFees ? `${royaltyPctFromFees}%` : 'n/a',
      true, // never fail verification on royalty; report only
      false,
    ),
  );
  notes.push(
    'Royalty on OpenSea is informational for Solana; the Metaplex Core collection ' +
      'config is authoritative. See reports/royalty-verification.md.',
  );

  const criticalFail = checks.some((c) => c.critical && !c.ok);
  const anyFail = checks.some((c) => !c.ok);
  const partial = checks.find((c) => c.field === 'sample_nfts' && !c.ok);

  let state: VerificationReport['state'];
  if (criticalFail) {
    state = 'MISMATCH';
    notes.unshift(
      'CRITICAL MISMATCH — chain or contract address does not match the ' +
        'authoritative Solana collection. Automated mutation of this slug is DISABLED.',
    );
  } else if (partial) {
    state = 'PARTIAL';
  } else if (anyFail) {
    state = 'PARTIAL';
  } else {
    state = 'VERIFIED';
  }

  return { state, slug, chain: cfg.chain, checked_at: now, checks, notes };
}

function normname(s: string): string {
  // Case- and whitespace-insensitive, but punctuation-sensitive:
  // "GHOST//ROOT" must NOT equal "GHOST_ROOT" (a different, ETH collection).
  return s.trim().replace(/\s+/g, '').toUpperCase();
}
function hostEq(a: string, b: string): boolean {
  try {
    return new URL(a).host.replace(/^www\./, '') === new URL(b).host.replace(/^www\./, '');
  } catch {
    return false;
  }
}

export function verdictLine(r: VerificationReport): string {
  return `${r.state}${r.slug ? ` (${r.slug})` : ''} — ${r.checks.filter((c) => c.ok).length}/${r.checks.length} checks pass`;
}
