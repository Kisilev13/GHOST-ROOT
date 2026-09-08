/**
 * Client-side rate limiting + retry policy.
 *
 * OpenSea returns `x-ratelimit-limit` / `x-ratelimit-remaining` / `x-ratelimit-reset`
 * (unix seconds) on every response, and `Retry-After` (seconds) on a 429. We
 * never hard-code a limit — the throttle adapts to whatever the live headers say.
 */

export interface RateState {
  limit: number | null;
  remaining: number | null;
  resetAt: number | null; // unix seconds
}

export class RateLimiter {
  private state: RateState = { limit: null, remaining: null, resetAt: null };
  private queue: Promise<void> = Promise.resolve();

  /** Serialise requests and pre-emptively pause when the window is nearly spent. */
  async gate(): Promise<void> {
    const run = this.queue.then(async () => {
      const { remaining, resetAt } = this.state;
      if (remaining !== null && remaining <= 1 && resetAt) {
        const waitMs = Math.max(0, resetAt * 1000 - Date.now()) + jitter(250);
        if (waitMs > 0) await sleep(waitMs);
      } else {
        await sleep(40 + jitter(60)); // gentle spacing, ~10–15 rps ceiling
      }
    });
    this.queue = run.catch(() => undefined);
    return run;
  }

  observe(headers: Headers): void {
    const limit = num(headers.get('x-ratelimit-limit'));
    const remaining = num(headers.get('x-ratelimit-remaining'));
    const reset = num(headers.get('x-ratelimit-reset'));
    if (limit !== null) this.state.limit = limit;
    if (remaining !== null) this.state.remaining = remaining;
    if (reset !== null) this.state.resetAt = reset;
  }

  get snapshot(): RateState {
    return { ...this.state };
  }

  /** How long to wait before retrying, given attempt # and an optional response. */
  backoffMs(attempt: number, res?: Response): number {
    if (res?.status === 429) {
      const retryAfter = num(res.headers.get('retry-after'));
      if (retryAfter !== null) return retryAfter * 1000 + jitter(500);
      const reset = num(res.headers.get('x-ratelimit-reset'));
      if (reset !== null) return Math.max(1000, reset * 1000 - Date.now()) + jitter(500);
    }
    return Math.min(30_000, 2 ** attempt * 1000) + jitter(400); // exp backoff + jitter
  }
}

export function sleep(ms: number): Promise<void> {
  return new Promise((r) => setTimeout(r, ms));
}
function jitter(max: number): number {
  return Math.floor(Math.random() * max);
}
function num(v: string | null): number | null {
  if (v == null) return null;
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
}
