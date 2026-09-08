/**
 * Structured stderr logger. Safe fields only (task section 28):
 * timestamp, event, endpoint, status, duration, rate-limit remaining, slug,
 * public addresses. Never a key, Authorization header, JWT, PAT, or signature.
 */

import { sanitizeForLog } from './config.ts';

type Level = 'debug' | 'info' | 'warn' | 'error';
const ORDER: Record<Level, number> = { debug: 10, info: 20, warn: 30, error: 40 };

export class Logger {
  private readonly min: Level;

  constructor(min: Level = 'info') {
    this.min = min;
  }

  private emit(level: Level, event: string, ctx: Record<string, unknown>): void {
    if (ORDER[level] < ORDER[this.min]) return;
    const line = {
      t: new Date().toISOString(),
      level,
      event,
      ...sanitizeForLog(ctx),
    };
    const out = `${line.t} ${level.toUpperCase().padEnd(5)} ${event} ${kv(line)}`;
    process.stderr.write(out + '\n');
  }

  debug = (e: string, c: Record<string, unknown> = {}) => this.emit('debug', e, c);
  info = (e: string, c: Record<string, unknown> = {}) => this.emit('info', e, c);
  warn = (e: string, c: Record<string, unknown> = {}) => this.emit('warn', e, c);
  error = (e: string, c: Record<string, unknown> = {}) => this.emit('error', e, c);
}

function kv(obj: Record<string, unknown>): string {
  return Object.entries(obj)
    .filter(([k]) => !['t', 'level', 'event'].includes(k))
    .map(([k, v]) => `${k}=${typeof v === 'string' ? v : JSON.stringify(v)}`)
    .join(' ');
}
