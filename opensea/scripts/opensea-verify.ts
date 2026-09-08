/**
 * opensea:verify — full collection-identity + sample-NFT + trait verification.
 * Writes reports/verification.md. Read-only. Exit code != 0 on MISMATCH/ERROR.
 */

import { resolve } from 'node:path';
import { loadConfig, TOOLKIT_ROOT } from '../src/config.ts';
import { OpenSeaClient } from '../src/client.ts';
import { discoverSlug } from '../src/discovery.ts';
import { verifyCollection } from '../src/verify.ts';
import { listCollectionNfts, validateNft, auditTraits } from '../src/nfts.ts';
import { heading, writeReport, nowStamp, checkbox } from '../src/ui.ts';

const SAMPLE_IDS = ['1', '2', '31', '303', '1842', '3333'];

async function main(): Promise<void> {
  const cfg = loadConfig();
  const client = new OpenSeaClient(cfg);

  const slug = cfg.collectionSlug || (await discoverSlug(client, cfg)).slug;
  const report = await verifyCollection(client, cfg, slug);

  console.log(heading('GHOST//ROOT — OPENSEA VERIFICATION'));
  console.log(`STATE: ${report.state}`);
  for (const c of report.checks) {
    console.log(`  ${checkbox(c.ok)} ${c.field.padEnd(20)} expected=${c.expected}  actual=${c.actual}`);
  }

  let nftFindings: ReturnType<typeof validateNft>[] = [];
  let traitAudit: ReturnType<typeof auditTraits> = [];

  if (slug && (report.state === 'VERIFIED' || report.state === 'PARTIAL')) {
    console.log('\nsample identities:');
    // One bulk page (<=50 items) drives both the sample check and the trait
    // audit — never one request per token (task section 18).
    const collected: Awaited<ReturnType<typeof listCollectionNfts>>['nfts'] = [];
    try {
      const { nfts } = await listCollectionNfts(client, slug, { limit: 50 });
      collected.push(...nfts);
    } catch (err) {
      console.log(`  could not list NFTs: ${(err as Error).message}`);
    }
    const wanted = new Set(SAMPLE_IDS);
    const sample = [
      ...collected.filter((n) => wanted.has(n.identifier)),
      ...collected.filter((n) => !wanted.has(n.identifier)),
    ].slice(0, 12);
    nftFindings = sample.map((n) => validateNft(n));
    for (const f of nftFindings) {
      console.log(`  ${checkbox(f.ok)} ${(f.name ?? f.identifier).padEnd(14)} ${f.problems.join('; ') || 'ok'}`);
    }
    traitAudit = auditTraits(collected);
    console.log('\ntrait audit:');
    for (const t of traitAudit) {
      console.log(`  ${checkbox(t.issues.length === 0)} ${t.trait_type.padEnd(18)} ${t.values.length} values  ${t.issues.join('; ')}`);
    }
  } else {
    console.log('\nsample-NFT + trait validation skipped — collection not verified/indexed yet.');
  }

  writeReport(
    resolve(TOOLKIT_ROOT, 'reports/verification.md'),
    renderMd(cfg, report, nftFindings, traitAudit),
  );
  console.log('\nwrote reports/verification.md');

  if (report.state === 'MISMATCH' || report.state === 'ERROR') process.exitCode = 2;
}

function renderMd(
  cfg: ReturnType<typeof loadConfig>,
  r: Awaited<ReturnType<typeof verifyCollection>>,
  findings: ReturnType<typeof validateNft>[],
  traits: ReturnType<typeof auditTraits>,
): string {
  return `# GHOST//ROOT — OpenSea Verification

_Generated ${nowStamp()} · read-only_

**State: \`${r.state}\`** · slug \`${r.slug ?? 'none'}\` · chain \`${r.chain}\`

## Identity checks

| Field | Expected | Actual | OK | Critical |
| --- | --- | --- | --- | --- |
${r.checks.map((c) => `| ${c.field} | ${c.expected} | ${c.actual} | ${c.ok ? '✅' : '❌'} | ${c.critical ? 'yes' : 'no'} |`).join('\n') || '| _none_ | | | | |'}

## Sample identity validation

${
  findings.length
    ? ['| Identity | OK | Problems |', '| --- | --- | --- |']
        .concat(findings.map((f) => `| ${f.name ?? f.identifier} | ${f.ok ? '✅' : '❌'} | ${f.problems.join('; ') || '—'} |`))
        .join('\n')
    : '_skipped — collection not indexed_'
}

## Trait audit vs collection/metadata/schema.json

${
  traits.length
    ? ['| Trait type | Values seen | Issues |', '| --- | --- | --- |']
        .concat(traits.map((t) => `| ${t.trait_type} | ${t.values.length} | ${t.issues.join('; ') || '—'} |`))
        .join('\n')
    : '_skipped — collection not indexed_'
}

## Notes

${r.notes.map((n) => `- ${n}`).join('\n') || '_none_'}

---

Royalty is verified separately (Solana on-chain config is authoritative):
see [royalty-verification.md](./royalty-verification.md). Target: ${cfg.facts.royaltyBps} bps.
`;
}

main().catch((err) => {
  console.error(`verify failed: ${(err as Error).message}`);
  process.exit(1);
});
