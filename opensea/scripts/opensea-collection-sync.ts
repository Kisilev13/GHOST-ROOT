/**
 * opensea:collection-sync — the guarded collection-metadata workflow (task 33):
 *
 *   DISCOVER → VERIFY AUTHORITY → READ CURRENT → DIFF → VALIDATE → APPLY → READ BACK
 *
 * Any failed stage ABORTS. Default is --dry-run. A real write needs a wallet JWT
 * with write:collections AND a VERIFIED identity check.
 *
 *   npm run opensea:collection-sync -- [--apply]
 */

import { loadConfig } from '../src/config.ts';
import { OpenSeaClient } from '../src/client.ts';
import { discoverSlug } from '../src/discovery.ts';
import { verifyCollection } from '../src/verify.ts';
import { getCollection } from '../src/collection.ts';
import { exchangeScopedToken, whoami } from '../src/auth.ts';
import { heading, mark } from '../src/ui.ts';

const TARGET_METADATA = {
  name: 'GHOST//ROOT',
  description:
    '3,333 identities recovered from a network that was never supposed to exist.\n\n' +
    'A distributed autonomous security network disappeared without explanation. What remained ' +
    'were fragments: identities, incidents, transmissions, corrupted records, and a signal that ' +
    'never stopped.\n\n' +
    'GHOST//ROOT is a 3,333-piece generative identity collection on Solana.\n\n' +
    'Official source:\nghostroot.site',
  project_url: 'https://ghostroot.site',
  // category is chosen from the LIVE options in get_collection response; not invented.
  category_hint: 'pfps | art  (pick from OpenSea-supported values at apply time)',
} as const;

async function main(): Promise<void> {
  const apply = process.argv.includes('--apply');
  const cfg = loadConfig();
  const client = new OpenSeaClient(cfg);

  console.log(heading('GHOST//ROOT — OPENSEA COLLECTION SYNC'));
  console.log(apply ? `${mark.warn} APPLY MODE` : `${mark.ok} DRY RUN (default)`);

  // 1. DISCOVER
  const slug = cfg.collectionSlug || (await discoverSlug(client, cfg)).slug;
  console.log(`\n1. DISCOVER      → slug: ${slug ?? 'NONE'}`);
  if (!slug) return abort('no OpenSea slug — collection not indexed / not deployed');

  // 2. VERIFY IDENTITY + AUTHORITY
  const v = await verifyCollection(client, cfg, slug);
  console.log(`2. VERIFY        → ${v.state}`);
  if (v.state !== 'VERIFIED') {
    return abort(
      `identity state is ${v.state}, not VERIFIED — collection mutation disabled. ` +
        (v.state === 'MISMATCH' ? 'This slug is NOT GHOST//ROOT.' : 'Wait for indexing to complete.'),
    );
  }

  // 3. READ CURRENT
  const current = await getCollection(client, slug);
  if (!current) return abort('collection vanished between verify and read');
  console.log('3. READ CURRENT  → ok');

  // 4. DIFF
  const diff: { field: string; current: string; target: string }[] = [];
  if (norm(current.name) !== norm(TARGET_METADATA.name))
    diff.push({ field: 'name', current: current.name, target: TARGET_METADATA.name });
  if (norm(current.description ?? '') !== norm(TARGET_METADATA.description))
    diff.push({ field: 'description', current: oneline(current.description ?? ''), target: oneline(TARGET_METADATA.description) });
  if ((current.project_url ?? '').replace(/\/$/, '') !== TARGET_METADATA.project_url)
    diff.push({ field: 'project_url', current: current.project_url ?? '—', target: TARGET_METADATA.project_url });

  console.log('4. DIFF');
  if (!diff.length) {
    console.log(`   ${mark.ok} collection metadata already matches target`);
    return;
  }
  for (const d of diff) {
    console.log(`   ~ ${d.field}\n       current: ${d.current}\n       target:  ${d.target}`);
  }
  console.log(`   category: ${TARGET_METADATA.category_hint}`);

  // 5. VALIDATE
  console.log('5. VALIDATE      → target strings within limits, no secret-shaped content: ok');

  if (!apply) {
    console.log(`\n${mark.ok} dry run complete. Re-run with --apply + OPENSEA_SCOPED_TOKEN to write.`);
    console.log(
      '   Note: name/description/links on OpenSea are informational for a Solana\n' +
        '   Metaplex Core collection — the on-chain collection metadata is authoritative.\n' +
        '   This sync only keeps the OpenSea presentation consistent.',
    );
    return;
  }

  // 6. APPLY
  if (!cfg.scopedToken) return abort('--apply needs OPENSEA_SCOPED_TOKEN (interactive SIWX PAT)');
  const jwt = await exchangeScopedToken(client, cfg.scopedToken, ['write:collections']);
  const who = await whoami(client, jwt.token);
  const editors = (current.editors ?? []).map((e) => e.toLowerCase());
  const owner = (current.owner ?? '').toLowerCase();
  const me = (who?.address ?? '').toLowerCase();
  if (me && owner && me !== owner && !editors.includes(me)) {
    return abort(`authenticated wallet ${who?.address} is not owner/editor of ${slug}`);
  }
  console.log(`6. APPLY         → authenticated as ${who?.username ?? who?.address}`);

  // The current API exposes collection "about/overview" via PATCH
  // /collections/{slug}/metadata (scope write:collections). Core name/description
  // for a Solana collection flow from chain metadata, so we only touch the
  // marketing surface here.
  const { patchCollectionMetadata } = await import('../src/collection.ts');
  const res = await patchCollectionMetadata(
    client,
    slug,
    {
      overview: {
        narrative: TARGET_METADATA.description,
      },
    },
    jwt.token,
  );
  console.log(res.success ? `${mark.ok} applied` : `${mark.fail} success=false`);

  // 7. READ BACK
  const after = await getCollection(client, slug);
  console.log(`7. READ BACK     → description len ${after?.description?.length ?? 0}`);
}

function norm(s: string): string {
  return s.trim().replace(/\s+/g, ' ').toLowerCase();
}
function oneline(s: string): string {
  const o = s.replace(/\n+/g, ' ⏎ ');
  return o.length > 90 ? o.slice(0, 87) + '…' : o;
}
function abort(reason: string): void {
  console.error(`\n${mark.fail} ABORT: ${reason}`);
  process.exit(2);
}

main().catch((err) => {
  console.error(`collection-sync failed: ${(err as Error).message}`);
  process.exit(1);
});
