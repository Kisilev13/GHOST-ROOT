/**
 * social:verify — confirms a claimed social URL actually resolves and
 * points at the expected platform host. Read-only: it never creates,
 * edits, or logs into any account, and it never guesses a handle — the
 * URL must be supplied by whoever created the account by hand (see
 * ../SETUP.md). Writes reports/verify.md.
 *
 * Usage:
 *   node --experimental-strip-types social-verify.ts --x https://x.com/ghostroot
 *   X_URL=https://x.com/ghostroot node --experimental-strip-types social-verify.ts
 */

import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const TOOLKIT_ROOT = new URL('..', import.meta.url).pathname;

type Platform = { key: string; label: string; envVar: string; flag: string; hosts: string[] };

const PLATFORMS: Platform[] = [
  { key: 'x', label: 'X (Twitter)', envVar: 'X_URL', flag: '--x', hosts: ['x.com', 'twitter.com'] },
  { key: 'discord', label: 'Discord', envVar: 'DISCORD_URL', flag: '--discord', hosts: ['discord.gg', 'discord.com'] },
  { key: 'telegram', label: 'Telegram', envVar: 'TELEGRAM_URL', flag: '--telegram', hosts: ['t.me', 'telegram.me'] },
  { key: 'instagram', label: 'Instagram', envVar: 'INSTAGRAM_URL', flag: '--instagram', hosts: ['instagram.com'] },
];

type State = 'ok' | 'fail' | 'warn' | 'skip';
type Row = { label: string; state: State; detail: string };

const mark: Record<State, string> = { ok: '[ OK ]', fail: '[FAIL]', warn: '[WARN]', skip: '[ -- ]' };

function heading(title: string): string {
  const bar = '─'.repeat(Math.max(8, title.length));
  return `\n${title}\n${bar}`;
}

function nowStamp(): string {
  return new Date().toISOString().replace(/\.\d+Z$/, 'Z');
}

function writeReport(path: string, body: string): void {
  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, body, 'utf8');
}

function argValue(flag: string): string | undefined {
  const i = process.argv.indexOf(flag);
  return i >= 0 ? process.argv[i + 1] : undefined;
}

async function checkUrl(url: string, hosts: string[]): Promise<{ state: State; detail: string }> {
  let parsed: URL;
  try {
    parsed = new URL(url);
  } catch {
    return { state: 'fail', detail: 'not a valid URL' };
  }
  if (parsed.protocol !== 'https:') {
    return { state: 'fail', detail: `expected https, got ${parsed.protocol}` };
  }
  const hostOk = hosts.some((h) => parsed.hostname === h || parsed.hostname.endsWith(`.${h}`));
  if (!hostOk) {
    return { state: 'warn', detail: `host "${parsed.hostname}" is not one of ${hosts.join(', ')} — wrong platform link?` };
  }

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 10_000);
  try {
    const res = await fetch(url, { method: 'GET', redirect: 'follow', signal: controller.signal });
    if (res.ok) return { state: 'ok', detail: `HTTP ${res.status}` };
    if (res.status === 999 || res.status === 403) {
      // Several platforms 403/999 bot-style GETs from non-browser clients even
      // when the page is genuinely live. Treat as inconclusive, not failure.
      return { state: 'warn', detail: `HTTP ${res.status} — platform may be blocking non-browser requests; check manually` };
    }
    return { state: 'fail', detail: `HTTP ${res.status}` };
  } catch (err) {
    return { state: 'fail', detail: (err as Error).message };
  } finally {
    clearTimeout(timeout);
  }
}

async function main(): Promise<void> {
  const rows: Row[] = [];

  for (const p of PLATFORMS) {
    const url = argValue(p.flag) ?? process.env[p.envVar];
    if (!url) {
      rows.push({ label: p.label, state: 'skip', detail: `no ${p.flag} / ${p.envVar} given` });
      continue;
    }
    const { state, detail } = await checkUrl(url, p.hosts);
    rows.push({ label: p.label, state, detail: `${url} — ${detail}` });
  }

  console.log(heading('GHOST//ROOT — SOCIAL LINK VERIFY'));
  for (const r of rows) console.log(`${mark[r.state]} ${r.label.padEnd(14)} ${r.detail}`);

  const checked = rows.filter((r) => r.state !== 'skip').length;
  const fails = rows.filter((r) => r.state === 'fail').length;
  const warns = rows.filter((r) => r.state === 'warn').length;
  console.log(`\n${checked} checked · ${fails} fail · ${warns} warn`);
  if (checked === 0) {
    console.log('\nNothing to check. Pass --x/--discord/--telegram/--instagram or set X_URL/DISCORD_URL/... first.');
  } else {
    console.log('\nA passing check here only means the link is live and on the right host — it does not confirm the');
    console.log('account belongs to the project. Verify ownership yourself before pasting into wp-admin.');
  }

  writeReport(
    resolve(TOOLKIT_ROOT, 'reports/verify.md'),
    `# GHOST//ROOT — Social Link Verify\n\n_Generated ${nowStamp()} · read-only, never registers or edits an account_\n\n` +
      '| Platform | Result | Detail |\n| --- | --- | --- |\n' +
      rows.map((r) => `| ${r.label} | ${r.state.toUpperCase()} | ${r.detail} |`).join('\n') +
      '\n',
  );

  process.exitCode = fails > 0 ? 1 : 0;
}

main().catch((err) => {
  console.error(`social:verify failed: ${(err as Error).message}`);
  process.exit(1);
});
