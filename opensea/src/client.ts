/**
 * OpenSea API v2 client — the only place an outbound OpenSea request is made.
 *
 * Guarantees:
 *  - `x-api-key` header on every request; never logged in clear (redact()).
 *  - Wallet-scoped writes use a short-lived JWT exchanged from the PAT at call
 *    time (see auth.ts). The PAT is never sent in Authorization.
 *  - Adapts to live `x-ratelimit-*` headers; exponential backoff + jitter on
 *    429/5xx/network; honours `Retry-After`.
 *  - Request de-duplication: identical in-flight GETs share one promise.
 *  - Structured, secret-free logging of endpoint, status, duration, rl-remaining.
 */

import type { Config } from './config.ts';
import { redact } from './config.ts';
import { Logger } from './logger.ts';
import { RateLimiter, sleep } from './ratelimit.ts';

export class OpenSeaError extends Error {
  readonly status: number;
  readonly endpoint: string;
  readonly body: unknown;

  constructor(message: string, status: number, endpoint: string, body?: unknown) {
    super(message);
    this.name = 'OpenSeaError';
    this.status = status;
    this.endpoint = endpoint;
    this.body = body;
  }
}

export interface RequestOptions {
  method?: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE';
  query?: Record<string, string | number | undefined>;
  body?: unknown;
  /** Attach a wallet JWT (already exchanged) for a scoped mutation. */
  bearer?: string;
  /** Bypass retry for idempotency-sensitive probes. */
  noRetry?: boolean;
}

export class OpenSeaClient {
  readonly rate = new RateLimiter();
  private readonly cfg: Config;
  private readonly log: Logger;
  private readonly inflight = new Map<string, Promise<unknown>>();
  lastRateRemaining: number | null = null;

  constructor(cfg: Config) {
    this.cfg = cfg;
    this.log = new Logger(cfg.logLevel);
    if (!cfg.apiKey) {
      this.log.warn('opensea_no_api_key', {
        hint: 'set OPENSEA_API_KEY (or run opensea:bootstrap)',
      });
    }
  }

  get baseUrl(): string {
    return this.cfg.baseUrl;
  }

  private url(path: string, query?: RequestOptions['query']): string {
    const u = new URL(this.cfg.baseUrl + (path.startsWith('/') ? path : `/${path}`));
    for (const [k, v] of Object.entries(query ?? {})) {
      if (v !== undefined && v !== '') u.searchParams.set(k, String(v));
    }
    return u.toString();
  }

  async request<T>(path: string, opts: RequestOptions = {}): Promise<T> {
    const method = opts.method ?? 'GET';
    const url = this.url(path, opts.query);
    const dedupeKey = method === 'GET' ? url : '';

    if (dedupeKey && this.inflight.has(dedupeKey)) {
      return this.inflight.get(dedupeKey) as Promise<T>;
    }

    const exec = this.execWithRetry<T>(method, url, path, opts);
    if (dedupeKey) {
      this.inflight.set(dedupeKey, exec);
      // Attach cleanup without spawning a floating rejected promise: the
      // rejection handler returns undefined so this derived promise settles.
      const cleanup = (): void => void this.inflight.delete(dedupeKey);
      exec.then(cleanup, cleanup);
    }
    return exec;
  }

  private async execWithRetry<T>(
    method: string,
    url: string,
    path: string,
    opts: RequestOptions,
  ): Promise<T> {
    const maxAttempts = opts.noRetry ? 1 : this.cfg.maxRetries + 1;
    let lastErr: unknown;

    for (let attempt = 0; attempt < maxAttempts; attempt++) {
      await this.rate.gate();
      const started = Date.now();
      let res: Response;

      try {
        res = await fetch(url, {
          method,
          headers: this.headers(opts),
          body: opts.body === undefined ? undefined : JSON.stringify(opts.body),
          signal: AbortSignal.timeout(this.cfg.timeoutMs),
        });
      } catch (err) {
        lastErr = err;
        this.log.warn('opensea_network_error', {
          endpoint: path,
          attempt,
          err: (err as Error).name,
        });
        if (attempt < maxAttempts - 1) await sleep(this.rate.backoffMs(attempt));
        continue;
      }

      this.rate.observe(res.headers);
      this.lastRateRemaining = this.rate.snapshot.remaining;
      const duration = Date.now() - started;

      this.log.info('opensea_request', {
        endpoint: path,
        method,
        status: res.status,
        ms: duration,
        rl_remaining: this.rate.snapshot.remaining ?? '?',
        key: redact(this.cfg.apiKey),
      });

      if (res.status === 429 || res.status >= 500) {
        lastErr = new OpenSeaError(`OpenSea ${res.status}`, res.status, path);
        if (attempt < maxAttempts - 1) {
          const wait = this.rate.backoffMs(attempt, res);
          this.log.warn('opensea_retry', { endpoint: path, status: res.status, wait_ms: wait });
          await sleep(wait);
          continue;
        }
      }

      const text = await res.text();
      let json: unknown;
      try {
        json = text ? JSON.parse(text) : {};
      } catch {
        throw new OpenSeaError('Malformed JSON from OpenSea', res.status, path, text.slice(0, 200));
      }

      if (!res.ok) {
        throw new OpenSeaError(
          `OpenSea ${res.status} on ${path}`,
          res.status,
          path,
          scrubBody(json),
        );
      }
      return json as T;
    }

    if (lastErr instanceof OpenSeaError) throw lastErr;
    throw new OpenSeaError(
      `OpenSea request failed after ${maxAttempts} attempts: ${(lastErr as Error)?.message ?? 'unknown'}`,
      0,
      path,
    );
  }

  private headers(opts: RequestOptions): Record<string, string> {
    const h: Record<string, string> = {
      accept: 'application/json',
      'user-agent': 'ghostroot-opensea/1.0 (+https://ghostroot.site)',
    };
    if (this.cfg.apiKey) h['x-api-key'] = this.cfg.apiKey;
    if (opts.body !== undefined) h['content-type'] = 'application/json';
    if (opts.bearer) h['authorization'] = `Bearer ${opts.bearer}`;
    return h;
  }
}

/** Remove anything credential-shaped from an error body before it can be logged. */
function scrubBody(body: unknown): unknown {
  if (body && typeof body === 'object') {
    const clone: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(body as Record<string, unknown>)) {
      clone[k] = /key|token|auth|secret|signature/i.test(k) ? redact(v) : v;
    }
    return clone;
  }
  return body;
}
