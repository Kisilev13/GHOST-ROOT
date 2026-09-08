/**
 * Creator profile read + changeset builder (task sections 6, 32).
 *
 * The GHOST//ROOT target profile is fixed here so `opensea:profile-sync` can
 * show a safe diff. Applying the diff needs a wallet JWT with `write:profile`
 * and is a deliberate, confirmed operation — never automatic.
 */

import type { OpenSeaClient } from './client.ts';
import { OpenSeaError } from './client.ts';
import type { OpenSeaAccount } from './types.ts';

export const TARGET_PROFILE = {
  username: 'ghostroot',
  usernameFallbacks: ['ghostrootnetwork', 'ghostrootprotocol', 'ghostrootarchive'],
  displayName: 'GHOST//ROOT',
  bio:
    '3,333 identities recovered from a network that was never supposed to exist.\n\n' +
    'The signal never stopped.\n\n' +
    'SOLANA // GHOST//ROOT',
  website: 'https://ghostroot.site',
} as const;

export async function getAccount(
  client: OpenSeaClient,
  identifier: string,
): Promise<OpenSeaAccount | null> {
  try {
    return await client.request<OpenSeaAccount>(
      `/accounts/${encodeURIComponent(identifier)}`,
    );
  } catch (err) {
    if (err instanceof OpenSeaError && err.status === 404) return null;
    throw err;
  }
}

export interface FieldChange {
  field: string;
  current: string;
  target: string;
  changed: boolean;
  writable: boolean;
  note?: string;
}

export function buildProfileChangeset(current: OpenSeaAccount | null): FieldChange[] {
  const c = current ?? {};
  const rows: FieldChange[] = [];

  rows.push(row('username', c.username ?? '', TARGET_PROFILE.username, true,
    (c.username && c.username !== TARGET_PROFILE.username)
      ? 'existing username present — do NOT rename if it legitimately belongs to this account'
      : undefined));
  rows.push(row('bio', (c.bio ?? '').trim(), TARGET_PROFILE.bio, true));
  rows.push(row('website', c.website ?? '', TARGET_PROFILE.website, true));
  rows.push(row('profile_image', c.profile_image_url ?? '', '<official GHOST//ROOT logomark>', true,
    'uploaded via POST /accounts/profile/image (upload_profile_image); supply the asset separately'));
  rows.push(row('banner_image', c.banner_image_url ?? '', '<GHOST//ROOT wide hero artwork>', true,
    'uploaded via the profile image upload flow with image_type=banner'));

  return rows;
}

function row(field: string, current: string, target: string, writable: boolean, note?: string): FieldChange {
  return {
    field,
    current: current || '—',
    target,
    changed: normalize(current) !== normalize(target),
    writable,
    note,
  };
}
function normalize(s: string): string {
  return s.trim().replace(/\s+/g, ' ').toLowerCase();
}

export interface ProfileSettingsPatch {
  username?: string;
  bio?: string;
  website?: string;
}

/** PATCH profile settings. Requires wallet JWT with write:profile. */
export async function updateProfileSettings(
  client: OpenSeaClient,
  patch: ProfileSettingsPatch,
  bearer: string,
): Promise<{ success: boolean }> {
  return client.request('/accounts/profile/settings', {
    method: 'PATCH',
    body: patch,
    bearer,
    noRetry: true,
  });
}
