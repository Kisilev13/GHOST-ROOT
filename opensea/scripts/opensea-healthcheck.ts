/**
 * opensea:health — the section-30 checklist. Read-only, secret-free output.
 * Exit code 0 if no hard failures, 1 otherwise. Writes reports/health.md.
 */

import { resolve } from 'node:path';
import { loadConfig, TOOLKIT_ROOT } from '../src/config.ts';
import { OpenSeaClient } from '../src/client.ts';
import { Cache } from '../src/cache.ts';
import { discoverSlug } from '../src/discovery.ts';
import { verifyCollection } from '../src/verify.ts';
import { getStats } from '../src/stats.ts';
import { listCollectionNfts } from '../src/nfts.ts';
import { heading, writeReport, nowStamp, mark } from '../src/ui.ts';
import type { ChainInfo } from '../src/types.ts';

type Row = { label: string; state: 'ok' | 'fail' | 'warn' | 'skip'; detail: string };

async function main(): Promise<void> {
  const cfg = loadConfig();
  const client = new OpenSeaClient(cfg);
  const rows: Row[] = [];
  const add = (label: string, state: Row['state'], detail = '') => rows.push({ label, state, detail });

  // 1. API key
  add('API key present', cfg.apiKey ? 'ok' : 'fail', cfg.apiKey ? 'from env/.secrets' : 'set OPENSEA_API_KEY');

  // 2. reachable + 3. solana supported
  let chains: ChainInfo[] = [];
  try {
    chains = (await client.request<{ chains: ChainInfo[] }>('/chains')).chains;
    add('OpenSea API reachable', 'ok', `${chains.length} chains, HTTP 200`);
  } catch (err) {
    add('OpenSea API reachable', 'fail', (err as Error).message);
  }
  add('Solana is a supported chain', chains.some((c) => c.chain === 'solana') ? 'ok' : 'fail');

  // 4. collection address known
  add(
    'Collection address known',
    cfg.solana.collectionAddress ? 'ok' : 'warn',
    cfg.solana.collectionAddress || 'AWAITING_SOLANA_COLLECTION_DEPLOYMENT',
  );

  // 5. discovery / slug
  const disco = await discoverSlug(client, cfg).catch(() => null);
  add('Collection found / indexed', disco?.slug ? 'ok' : 'warn', disco?.slug ?? 'not indexed');
  add('Collection slug resolved', disco?.slug ? 'ok' : 'skip', disco?.slug ?? '');

  // 6. metadata + 7. sample nfts
  let verifyState = 'SKIPPED';
  if (disco?.slug) {
    const v = await verifyCollection(client, cfg, disco.slug).catch((e) => ({ state: 'ERROR', notes: [(e as Error).message] }) as const);
    verifyState = v.state;
    add('Collection metadata correct', v.state === 'VERIFIED' ? 'ok' : v.state === 'MISMATCH' ? 'fail' : 'warn', v.state);
    try {
      const { nfts } = await listCollectionNfts(client, disco.slug, { limit: 3 });
      add('Sample NFTs resolve', nfts.length ? 'ok' : 'warn', `${nfts.length} of 3`);
    } catch (err) {
      add('Sample NFTs resolve', 'warn', (err as Error).message);
    }
  } else {
    add('Collection metadata correct', 'skip', 'no slug');
    add('Sample NFTs resolve', 'skip', 'no slug');
  }

  // 8. rate limit health
  const rl = client.rate.snapshot;
  add(
    'API rate limit healthy',
    rl.remaining === null ? 'warn' : rl.remaining > 5 ? 'ok' : 'warn',
    `${rl.remaining ?? '?'} / ${rl.limit ?? '?'} remaining`,
  );

  // 9. cache operational
  const cache = new Cache(cfg.cache.dir);
  cache.write('health:probe', { t: Date.now() }, 5);
  add('Cache operational', cache.read('health:probe') ? 'ok' : 'fail', cfg.cache.dir);

  // 10. profile detected (best effort — only if a scoped token exists)
  add(
    'Creator profile detected',
    cfg.scopedToken ? 'warn' : 'skip',
    cfg.scopedToken ? 'PAT present — run opensea:profile-sync --account <addr>' : 'no PAT configured',
  );

  // 11. verification data matches
  add(
    'Verification data matches',
    verifyState === 'VERIFIED' ? 'ok' : verifyState === 'MISMATCH' ? 'fail' : 'warn',
    verifyState,
  );

  // 12. stats endpoint alive (only meaningful with a slug)
  if (disco?.slug) {
    try {
      const s = await getStats(client, disco.slug);
      add('Stats endpoint alive', 'ok', s.source === 'opensea' ? 'data present' : 'no market data yet');
    } catch (err) {
      add('Stats endpoint alive', 'warn', (err as Error).message);
    }
  } else {
    add('Stats endpoint alive', 'skip', 'no slug');
  }

  // 13. key expiry
  if (cfg.apiKeyExpiresAt) {
    const days = Math.round((Date.parse(cfg.apiKeyExpiresAt) - Date.now()) / 86_400_000);
    add('API key not expiring', days < 0 ? 'fail' : days <= 7 ? 'warn' : 'ok', `~${days} day(s) left`);
  } else {
    add('API key not expiring', 'skip', 'no expiry recorded');
  }

  // render
  console.log(heading('GHOST//ROOT — OPENSEA HEALTH CHECK'));
  for (const r of rows) {
    const box = r.state === 'ok' ? mark.ok : r.state === 'fail' ? mark.fail : r.state === 'warn' ? mark.warn : mark.skip;
    console.log(`${box} ${r.label.padEnd(30)} ${r.detail}`);
  }
  const fails = rows.filter((r) => r.state === 'fail').length;
  const warns = rows.filter((r) => r.state === 'warn').length;
  console.log(`\n${fails} fail · ${warns} warn · ${rows.filter((r) => r.state === 'ok').length} ok`);

  writeReport(
    resolve(TOOLKIT_ROOT, 'reports/health.md'),
    `# GHOST//ROOT — OpenSea Health Check\n\n_Generated ${nowStamp()}_\n\n` +
      '| Check | Result | Detail |\n| --- | --- | --- |\n' +
      rows.map((r) => `| ${r.label} | ${r.state.toUpperCase()} | ${r.detail} |`).join('\n') +
      `\n\n**${fails} fail · ${warns} warn**\n`,
  );

  process.exitCode = fails > 0 ? 1 : 0;
}

main().catch((err) => {
  console.error(`health check failed: ${(err as Error).message}`);
  process.exit(1);
});
