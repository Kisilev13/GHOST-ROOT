/**
 * Tiny CLI presentation helpers — GHOST//ROOT terminal aesthetic, no deps.
 */

import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname } from 'node:path';

export function heading(title: string): string {
  const bar = '─'.repeat(Math.max(8, title.length));
  return `\n${title}\n${bar}`;
}

export function kvBlock(rows: [string, string][]): string {
  const w = Math.max(...rows.map(([k]) => k.length));
  return rows.map(([k, v]) => `  ${k.toUpperCase().padEnd(w)}   ${v}`).join('\n');
}

export function statusLine(label: string, value: string): string {
  return `${label.toUpperCase().padEnd(20)} ${value}`;
}

export const mark = {
  ok: '[ OK ]',
  fail: '[FAIL]',
  warn: '[WARN]',
  skip: '[ -- ]',
  pending: '[ .. ]',
};

export function checkbox(ok: boolean | null): string {
  if (ok === null) return mark.pending;
  return ok ? mark.ok : mark.fail;
}

export function writeReport(path: string, body: string): void {
  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, body, 'utf8');
}

export function nowStamp(): string {
  return new Date().toISOString().replace(/\.\d+Z$/, 'Z');
}
