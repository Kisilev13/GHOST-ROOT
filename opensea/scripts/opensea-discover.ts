/**
 * opensea:discover — is GHOST//ROOT on OpenSea, and under which slug?
 *
 * Prints the section-31 discovery block and writes reports/discovery.md.
 * Read-only. Never selects a collection by name — see src/discovery.ts.
 */

import { resolve } from 'node:path';
import { loadConfig, TOOLKIT_ROOT } from '../src/config.ts';
import { OpenSeaClient } from '../src/client.ts';
import { discoverSlug } from '../src/discovery.ts';
import { verifyCollection, verdictLine } from '../src/verify.ts';
import { heading, kvBlock, writeReport, nowStamp } from '../src/ui.ts';
import type { ChainInfo } from '../src/types.ts';

async function main(): Promise<void> {
  const cfg = loadConfig();
  const client = new OpenSeaClient(cfg);

  const chains = await client.request<{ chains: ChainInfo[] }>('/chains').catch(() => ({ chains: [] }));
  const solanaOk = chains.chains.some((c) => c.chain === 'solana');

  const disco = await discoverSlug(client, cfg);
  const verification = await verifyCollection(client, cfg, disco.slug);

  const status =
    verification.state === 'VERIFIED'
      ? 'VERIFIED'
      : verification.state === 'AWAITING_SOLANA_COLLECTION_DEPLOYMENT'
        ? 'AWAITING DEPLOYMENT'
        : verification.state === 'NOT_INDEXED'
          ? 'NOT INDEXED'
          : verification.state;

  console.log(heading('GHOST//ROOT OPENSEA DISCOVERY'));
  console.log(
    kvBlock([
      ['api', cfg.apiKey ? 'OK' : 'NO KEY'],
      ['chain', cfg.chain.toUpperCase()],
      ['chain supported', solanaOk ? 'YES' : 'NO'],
      ['collection address', cfg.solana.collectionAddress || '— (not deployed)'],
      ['opensea index', disco.slug ? 'CANDIDATE FOUND' : 'NOT FOUND'],
      ['slug', disco.slug ?? '—'],
      ['resolve method', disco.method],
      ['identity check', verdictLine(verification)],
      ['status', status],
      [
        'next action',
        verification.state === 'VERIFIED'
          ? 'copy slug to OPENSEA_COLLECTION_SLUG, run opensea:collection-sync --dry-run'
          : verification.state === 'AWAITING_SOLANA_COLLECTION_DEPLOYMENT'
            ? 'deploy the Metaplex Core collection, then re-run discover'
            : verification.state === 'MISMATCH'
              ? 'DO NOT TOUCH the candidate slug — see notes'
              : 'submit OpenSea indexing request once minted',
      ],
    ]),
  );

  if (disco.candidates.length) {
    console.log('\ncandidates (UNCONFIRMED — verified only by chain+address match):');
    for (const c of disco.candidates) {
      console.log(`  · ${c.slug}  "${c.name}"  chain=${c.chain}  [${c.reason}]`);
    }
  }
  for (const n of [...disco.notes, ...verification.notes]) console.log(`  note: ${n}`);

  writeReport(resolve(TOOLKIT_ROOT, 'reports/discovery.md'), renderMd(cfg, disco, verification, solanaOk));
  console.log('\nwrote reports/discovery.md');
}

function renderMd(
  cfg: ReturnType<typeof loadConfig>,
  disco: Awaited<ReturnType<typeof discoverSlug>>,
  v: Awaited<ReturnType<typeof verifyCollection>>,
  solanaOk: boolean,
): string {
  return `# GHOST//ROOT — OpenSea Discovery

_Generated ${nowStamp()} · read-only_

| Field | Value |
| --- | --- |
| OpenSea API | ${cfg.apiKey ? 'authenticated' : 'NO KEY'} |
| Target chain | ${cfg.chain} |
| Chain supported by OpenSea | ${solanaOk ? 'yes' : 'no'} |
| Authoritative Solana collection address | ${cfg.solana.collectionAddress || '_not deployed_'} |
| Candy Machine address | ${cfg.solana.candyMachineAddress || '_not deployed_'} |
| Update authority | ${cfg.solana.updateAuthority || '_not deployed_'} |
| Resolved OpenSea slug | ${disco.slug ?? '_none_'} |
| Resolution method | ${disco.method} |
| Verification state | **${v.state}** |

## Candidates considered

${
  disco.candidates.length
    ? disco.candidates
        .map((c) => `- \`${c.slug}\` — "${c.name}" — chain \`${c.chain}\` — ${c.reason}`)
        .join('\n')
    : '_none_'
}

> A candidate is the GHOST//ROOT collection **only** if \`chain === ${cfg.chain}\`
> **and** its contract address equals \`SOLANA_COLLECTION_ADDRESS\`. Name matches
> are ignored.

## Verification checks

${
  v.checks.length
    ? ['| Field | Expected | Actual | OK | Critical |', '| --- | --- | --- | --- | --- |']
        .concat(
          v.checks.map(
            (c) => `| ${c.field} | ${c.expected} | ${c.actual} | ${c.ok ? '✅' : '❌'} | ${c.critical ? 'yes' : 'no'} |`,
          ),
        )
        .join('\n')
    : '_none run_'
}

## Notes

${[...disco.notes, ...v.notes].map((n) => `- ${n}`).join('\n') || '_none_'}
`;
}

main().catch((err) => {
  console.error(`discover failed: ${(err as Error).message}`);
  process.exit(1);
});
