/**
 * opensea:profile-sync — show a safe diff between the current OpenSea creator
 * profile and the GHOST//ROOT target, then (only with --apply and a wallet JWT)
 * push the writable text fields.
 *
 *   npm run opensea:profile-sync -- [--account <addr|username>] [--apply]
 *
 * Default = DRY RUN. Images are never uploaded here (they need the asset file);
 * the diff just flags that they differ.
 */

import { loadConfig } from '../src/config.ts';
import { OpenSeaClient } from '../src/client.ts';
import { getAccount, buildProfileChangeset, TARGET_PROFILE, updateProfileSettings } from '../src/profile.ts';
import { exchangeScopedToken, whoami } from '../src/auth.ts';
import { heading, mark } from '../src/ui.ts';

async function main(): Promise<void> {
  const args = process.argv.slice(2);
  const apply = args.includes('--apply');
  const acctIdx = args.indexOf('--account');
  const account = acctIdx >= 0 ? args[acctIdx + 1] : undefined;

  const cfg = loadConfig();
  const client = new OpenSeaClient(cfg);

  console.log(heading('GHOST//ROOT — OPENSEA PROFILE SYNC'));
  console.log(apply ? `${mark.warn} APPLY MODE` : `${mark.ok} DRY RUN (default) — no writes`);

  if (!account) {
    console.log(
      `\n${mark.skip} no --account given. Provide the wallet address or username that owns\n` +
        '   the GHOST//ROOT OpenSea creator profile once it exists. Showing the target only:\n',
    );
    printTarget();
    return;
  }

  const current = await getAccount(client, account).catch(() => null);
  if (!current) {
    console.log(`${mark.warn} account "${account}" not found on OpenSea — showing target only`);
    printTarget();
    return;
  }

  const changes = buildProfileChangeset(current);
  console.log('\nPROFILE CHANGESET\n');
  for (const c of changes) {
    const flag = c.changed ? '~' : '=';
    console.log(`${flag} ${c.field}`);
    console.log(`    current: ${truncate(c.current)}`);
    console.log(`    target:  ${truncate(c.target)}`);
    if (c.note) console.log(`    note:    ${c.note}`);
  }

  const writable = changes.filter((c) => c.changed && c.writable && ['username', 'bio', 'website'].includes(c.field));
  if (!writable.length) {
    console.log(`\n${mark.ok} no writable text changes — profile already matches target`);
    return;
  }

  if (!apply) {
    console.log(`\n${mark.ok} dry run complete. Re-run with --apply and OPENSEA_SCOPED_TOKEN set to push:`);
    for (const c of writable) console.log(`     ${c.field}`);
    return;
  }

  // APPLY path — needs a wallet JWT with write:profile
  if (!cfg.scopedToken) {
    console.log(`\n${mark.fail} --apply needs OPENSEA_SCOPED_TOKEN (a PAT from an interactive SIWX session).`);
    process.exitCode = 1;
    return;
  }
  const jwt = await exchangeScopedToken(client, cfg.scopedToken, ['write:profile']);
  const who = await whoami(client, jwt.token);
  console.log(`${mark.ok} authenticated as ${who?.username ?? who?.address ?? 'unknown'}`);
  if (who && current.address && who.address && who.address.toLowerCase() !== current.address.toLowerCase()) {
    console.log(`${mark.fail} authenticated wallet != target profile wallet — aborting`);
    process.exitCode = 2;
    return;
  }

  const patch: Record<string, string> = {};
  for (const c of writable) {
    if (c.field === 'username') patch.username = TARGET_PROFILE.username;
    if (c.field === 'bio') patch.bio = TARGET_PROFILE.bio;
    if (c.field === 'website') patch.website = TARGET_PROFILE.website;
  }
  const res = await updateProfileSettings(client, patch, jwt.token);
  console.log(res.success ? `${mark.ok} profile updated` : `${mark.fail} update returned success=false`);

  // read-back
  const after = await getAccount(client, account);
  const still = buildProfileChangeset(after).filter((c) => c.changed && ['username', 'bio', 'website'].includes(c.field));
  console.log(still.length ? `${mark.warn} still differ: ${still.map((c) => c.field).join(', ')}` : `${mark.ok} read-back matches target`);
}

function printTarget(): void {
  console.log(`  username:  ${TARGET_PROFILE.username}  (fallbacks: ${TARGET_PROFILE.usernameFallbacks.join(', ')})`);
  console.log(`  display:   ${TARGET_PROFILE.displayName}`);
  console.log(`  website:   ${TARGET_PROFILE.website}`);
  console.log(`  bio:\n${TARGET_PROFILE.bio.split('\n').map((l) => '    ' + l).join('\n')}`);
}
function truncate(s: string): string {
  const one = s.replace(/\n/g, ' ⏎ ');
  return one.length > 100 ? one.slice(0, 97) + '…' : one;
}

main().catch((err) => {
  console.error(`profile-sync failed: ${(err as Error).message}`);
  process.exit(1);
});
