/**
 * Filesystem TTL cache with stale-while-revalidate.
 *
 * The toolkit is a CLI, so a small on-disk cache is enough to keep repeated
 * `discover` / `verify` / `health` runs from hammering OpenSea. The WordPress
 * side has its own transient cache (src/OpenSea/Cache.php); this one only
 * shields the operator scripts.
 */

import { mkdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { join } from 'node:path';

interface Entry<T> {
  key: string;
  stored_at: number;
  ttl: number;
  value: T;
}

export interface CacheGet<T> {
  value: T;
  fresh: boolean;
  age: number;
}

export class Cache {
  private readonly dir: string;

  constructor(dir: string) {
    this.dir = dir;
    try {
      mkdirSync(dir, { recursive: true });
    } catch {
      /* read-only fs — cache silently disabled */
    }
  }

  private path(key: string): string {
    return join(this.dir, `${createHash('sha256').update(key).digest('hex').slice(0, 24)}.json`);
  }

  read<T>(key: string): CacheGet<T> | null {
    const p = this.path(key);
    if (!existsSync(p)) return null;
    try {
      const entry = JSON.parse(readFileSync(p, 'utf8')) as Entry<T>;
      const age = Math.round((Date.now() - entry.stored_at) / 1000);
      return { value: entry.value, fresh: age <= entry.ttl, age };
    } catch {
      return null;
    }
  }

  write<T>(key: string, value: T, ttl: number): void {
    const entry: Entry<T> = { key, stored_at: Date.now(), ttl, value };
    try {
      writeFileSync(this.path(key), JSON.stringify(entry), 'utf8');
    } catch {
      /* ignore */
    }
  }

  /**
   * Return fresh cache, else fetch. On fetch failure, fall back to stale cache
   * (stale-while-error) so a transient OpenSea outage never breaks a report.
   */
  async wrap<T>(key: string, ttl: number, fetcher: () => Promise<T>): Promise<CacheGet<T>> {
    const hit = this.read<T>(key);
    if (hit?.fresh) return hit;
    try {
      const value = await fetcher();
      this.write(key, value, ttl);
      return { value, fresh: true, age: 0 };
    } catch (err) {
      if (hit) return { ...hit, fresh: false };
      throw err;
    }
  }
}
