/**
 * Configuration + secret hygiene.
 *
 * Load order (later overrides earlier):
 *   1. process.env
 *   2. <repo>/.secrets/opensea.env      (project convention — holds the live key)
 *   3. opensea/.env                     (local overrides, gitignored)
 *
 * Nothing here ever prints a secret. `redact()` is the only sanctioned way to
 * put credential-adjacent strings near a log line.
 */

import { readFileSync, existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
export const TOOLKIT_ROOT = resolve(HERE, '..');
export const REPO_ROOT = resolve(TOOLKIT_ROOT, '..');

function parseEnvFile(path: string): Record<string, string> {
  const out: Record<string, string> = {};
  if (!existsSync(path)) return out;
  for (const raw of readFileSync(path, 'utf8').split('\n')) {
    const line = raw.trim();
    if (!line || line.startsWith('#')) continue;
    const eq = line.indexOf('=');
    if (eq === -1) continue;
    const key = line.slice(0, eq).trim();
    let val = line.slice(eq + 1).trim();
    if (
      (val.startsWith('"') && val.endsWith('"')) ||
      (val.startsWith("'") && val.endsWith("'"))
    ) {
      val = val.slice(1, -1);
    }
    out[key] = val;
  }
  return out;
}

/** Later layers override earlier ones, but an EMPTY value never clobbers a set one. */
function layer(target: Record<string, string>, src: Record<string, string>): void {
  for (const [k, v] of Object.entries(src)) {
    if (v !== '' || !(k in target)) target[k] = v;
  }
}

const merged: Record<string, string> = {};
layer(merged, parseEnvFile(resolve(REPO_ROOT, '.secrets/opensea.env')));
layer(merged, parseEnvFile(resolve(TOOLKIT_ROOT, '.env')));
layer(
  merged,
  Object.fromEntries(
    Object.entries(process.env).filter(([, v]) => v !== undefined) as [string, string][],
  ),
);

function str(key: string, fallback = ''): string {
  return (merged[key] ?? fallback).trim();
}
function int(key: string, fallback: number): number {
  const n = Number.parseInt(merged[key] ?? '', 10);
  return Number.isFinite(n) ? n : fallback;
}

export interface Config {
  apiKey: string;
  apiKeyExpiresAt: string;
  scopedToken: string;
  baseUrl: string;
  chain: string;
  env: 'production' | 'testnet';
  collectionSlug: string;
  collectionId: string;
  solana: {
    collectionAddress: string;
    candyMachineAddress: string;
    updateAuthority: string;
    treasury: string;
    metadataRoot: string;
    artworkRoot: string;
    deploymentHash: string;
  };
  facts: {
    name: string;
    supply: number;
    projectUrl: string;
    royaltyBps: number;
  };
  cache: {
    dir: string;
    ttl: Record<'collection' | 'stats' | 'floor' | 'activity' | 'traits' | 'verification', number>;
  };
  timeoutMs: number;
  maxRetries: number;
  logLevel: 'debug' | 'info' | 'warn' | 'error';
}

export function loadConfig(): Config {
  const env = str('OPENSEA_ENV', 'production') === 'testnet' ? 'testnet' : 'production';
  return {
    apiKey: str('OPENSEA_API_KEY'),
    apiKeyExpiresAt: str('OPENSEA_API_KEY_EXPIRES_AT'),
    scopedToken: str('OPENSEA_SCOPED_TOKEN'),
    baseUrl:
      env === 'testnet'
        ? 'https://testnets-api.opensea.io/api/v2'
        : 'https://api.opensea.io/api/v2',
    chain: str('OPENSEA_CHAIN', 'solana'),
    env,
    collectionSlug: str('OPENSEA_COLLECTION_SLUG'),
    collectionId: str('OPENSEA_COLLECTION_ID'),
    solana: {
      collectionAddress: str('SOLANA_COLLECTION_ADDRESS'),
      candyMachineAddress: str('CANDY_MACHINE_ADDRESS'),
      updateAuthority: str('SOLANA_UPDATE_AUTHORITY'),
      treasury: str('SOLANA_TREASURY'),
      metadataRoot: str('METADATA_ROOT'),
      artworkRoot: str('ARTWORK_ROOT'),
      deploymentHash: str('DEPLOYMENT_HASH'),
    },
    facts: {
      name: str('COLLECTION_NAME', 'GHOST//ROOT'),
      supply: int('COLLECTION_SUPPLY', 3333),
      projectUrl: str('PROJECT_URL', 'https://ghostroot.site'),
      royaltyBps: int('ROYALTY_BPS', 500),
    },
    cache: {
      dir: resolve(TOOLKIT_ROOT, str('OPENSEA_CACHE_DIR', '.cache')),
      ttl: {
        collection: int('OPENSEA_CACHE_TTL_COLLECTION', 3600),
        stats: int('OPENSEA_CACHE_TTL_STATS', 300),
        floor: int('OPENSEA_CACHE_TTL_FLOOR', 120),
        activity: int('OPENSEA_CACHE_TTL_ACTIVITY', 60),
        traits: int('OPENSEA_CACHE_TTL_TRAITS', 21600),
        verification: int('OPENSEA_CACHE_TTL_VERIFICATION', 86400),
      },
    },
    timeoutMs: int('OPENSEA_TIMEOUT_MS', 20000),
    maxRetries: int('OPENSEA_MAX_RETRIES', 4),
    logLevel: (str('OPENSEA_LOG_LEVEL', 'info') as Config['logLevel']) || 'info',
  };
}

const SECRET_PATTERNS: RegExp[] = [
  /(api[_-]?key|bearer|authorization|token|secret|private[_-]?key|seed|mnemonic|jwt|signature|cookie)/i,
];

/** Collapse any secret-shaped value to a short fingerprint. Safe to log. */
export function redact(value: unknown): string {
  if (value == null) return '';
  const s = String(value);
  if (s.length === 0) return '';
  if (s.length < 8) return '***';
  return `${s.slice(0, 3)}…${s.slice(-2)}(${s.length})`;
}

/** Redact known secret keys inside an object before logging it. */
export function sanitizeForLog(obj: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const [k, v] of Object.entries(obj)) {
    out[k] = SECRET_PATTERNS.some((re) => re.test(k)) ? redact(v) : v;
  }
  return out;
}

/** True if a string looks like credential material that must never be persisted. */
export function looksLikeSecret(s: string): boolean {
  const t = s.trim();
  if (/^[0-9a-f]{64,}$/i.test(t)) return true; // hex key / hash-length blob
  if (/^(\S+\s+){11,23}\S+$/.test(t)) return true; // 12–24 word phrase
  if (/^ey[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\./.test(t)) return true; // JWT
  return false;
}
