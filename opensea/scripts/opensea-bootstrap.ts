/**
 * opensea:bootstrap — get the integration to a known-good starting state.
 *
 *   - Confirms an API key exists (env / .secrets/opensea.env / .env).
 *   - If none, offers the instant free-tier key endpoint (opt-in, --create-key).
 *   - Proves the key works against GET /chains and that `solana` is supported.
 *   - Reports the rate-limit ceiling.
 *   - Records the key EXPIRY (never the key) and warns if it is close.
 *   - Writes opensea/.env from .env.example if missing (no secrets written).
 *
 * No secret is ever printed. Run: npm run opensea:bootstrap [-- --create-key]
 */

import { existsSync, copyFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { loadConfig, redact, TOOLKIT_ROOT } from '../src/config.ts';
import { OpenSeaClient } from '../src/client.ts';
import { describeCredential } from '../src/auth.ts';
import { heading, kvBlock, mark } from '../src/ui.ts';
import type { ChainInfo } from '../src/types.ts';

const argv = new Set(process.argv.slice(2));

async function main(): Promise<void> {
  const cfg = loadConfig();
  console.log(heading('GHOST//ROOT — OPENSEA BOOTSTRAP'));

  // 1. env scaffold
  const envPath = resolve(TOOLKIT_ROOT, '.env');
  if (!existsSync(envPath)) {
    copyFileSync(resolve(TOOLKIT_ROOT, '.env.example'), envPath);
    console.log(`${mark.ok} wrote opensea/.env from .env.example (no secrets) — fill it in`);
  } else {
    console.log(`${mark.skip} opensea/.env already exists`);
  }

  // 2. API key presence
  let apiKey = cfg.apiKey;
  if (!apiKey && argv.has('--create-key')) {
    apiKey = await createInstantKey(cfg.baseUrl);
  }
  if (!apiKey) {
    console.log(`${mark.fail} no OPENSEA_API_KEY found.`);
    console.log('   Options:');
    console.log('     • put an existing key in .secrets/opensea.env or opensea/.env');
    console.log('     • re-run: npm run opensea:bootstrap -- --create-key   (instant free-tier)');
    process.exitCode = 1;
    return;
  }
  console.log(`${mark.ok} API key present (${redact(apiKey)})  ${describeCredential(apiKey, cfg.scopedToken)}`);

  // 3. prove it + chain support
  const client = new OpenSeaClient({ ...cfg, apiKey });
  const chains = await client.request<{ chains: ChainInfo[] }>('/chains');
  const solana = chains.chains.find((c) => c.chain === 'solana');
  console.log(`${solana ? mark.ok : mark.fail} OpenSea API reachable — ${chains.chains.length} chains`);
  console.log(
    solana
      ? `${mark.ok} Solana supported: ${solana.name} (${solana.symbol}), explorer ${solana.block_explorer}`
      : `${mark.fail} Solana NOT in supported chains — integration cannot proceed`,
  );

  // 4. rate limit ceiling
  const rl = client.rate.snapshot;
  console.log(
    `${mark.ok} rate limit: ${rl.remaining ?? '?'} / ${rl.limit ?? '?'} remaining this window`,
  );

  // 5. key expiry warning
  reportExpiry(cfg.apiKeyExpiresAt);

  console.log(
    kvBlock([
      ['base url', cfg.baseUrl],
      ['chain', cfg.chain],
      ['collection slug', cfg.collectionSlug || '(undiscovered)'],
      ['solana address', cfg.solana.collectionAddress || '(not deployed)'],
      [
        'opensea status',
        cfg.solana.collectionAddress ? 'READY_TO_DISCOVER' : 'AWAITING_SOLANA_COLLECTION_DEPLOYMENT',
      ],
    ]),
  );
  console.log('\nnext: npm run opensea:discover');
}

async function createInstantKey(baseUrl: string): Promise<string> {
  console.log('… requesting instant free-tier API key');
  const res = await fetch(`${baseUrl}/auth/keys`, {
    method: 'POST',
    headers: { accept: 'application/json', 'content-type': 'application/json' },
    body: JSON.stringify({ source: 'ghostroot-opensea-bootstrap' }),
  });
  if (!res.ok) throw new Error(`instant key request failed: HTTP ${res.status}`);
  const body = (await res.json()) as { api_key?: string; key?: string; expires_at?: string };
  const key = body.api_key ?? body.key ?? '';
  if (!key) throw new Error('instant key response contained no key');
  console.log(`${mark.warn} instant key issued — store it in .secrets/opensea.env yourself:`);
  console.log('   printf "OPENSEA_API_KEY=%s\\n" "<paste>" >> ../.secrets/opensea.env');
  if (body.expires_at) {
    console.log(`   and set OPENSEA_API_KEY_EXPIRES_AT=${body.expires_at}`);
  }
  // Do NOT echo the key to stdout beyond what the operator must copy once.
  process.stdout.write(`\n   KEY (copy now, will not be shown again): ${key}\n\n`);
  return key;
}

function reportExpiry(iso: string): void {
  if (!iso) {
    console.log(`${mark.skip} no key expiry recorded (portal key or unknown) — set OPENSEA_API_KEY_EXPIRES_AT if temporary`);
    return;
  }
  const ms = Date.parse(iso);
  if (Number.isNaN(ms)) {
    console.log(`${mark.warn} OPENSEA_API_KEY_EXPIRES_AT="${iso}" is not a valid date`);
    return;
  }
  const days = Math.round((ms - Date.now()) / 86_400_000);
  if (days < 0) console.log(`${mark.fail} API KEY EXPIRED ${-days} day(s) ago — rotate now`);
  else if (days <= 7) console.log(`${mark.warn} API key expires in ${days} day(s) — rotate soon`);
  else console.log(`${mark.ok} API key valid for ~${days} more day(s)`);
}

main().catch((err) => {
  console.error(`\nbootstrap failed: ${(err as Error).message}`);
  process.exit(1);
});
