import { test } from 'node:test';
import assert from 'node:assert/strict';
import { redact, sanitizeForLog, looksLikeSecret } from '../src/config.ts';

test('redact() never returns the full value', () => {
  // Synthetic 32-hex value shaped like an API key — NOT a real credential.
  const key = 'deadbeefcafef00d0123456789abcdef0';
  const out = redact(key);
  assert.ok(!out.includes(key));
  assert.ok(out.length < key.length);
  assert.match(out, /^\w{3}…\w{2}\(\d+\)$/);
});

test('redact() handles short + empty', () => {
  assert.equal(redact(''), '');
  assert.equal(redact('abc'), '***');
  assert.equal(redact(null), '');
});

test('sanitizeForLog redacts secret-shaped keys only', () => {
  const out = sanitizeForLog({
    endpoint: '/collections/x',
    status: 200,
    api_key: 'supersecretvalue123',
    authorization: 'Bearer abc.def.ghi',
    slug: 'ghost-root',
  });
  assert.equal(out.endpoint, '/collections/x');
  assert.equal(out.status, 200);
  assert.equal(out.slug, 'ghost-root');
  assert.ok(!String(out.api_key).includes('supersecretvalue'));
  assert.ok(!String(out.authorization).includes('abc.def.ghi'));
});

test('looksLikeSecret catches hex keys, seed phrases, JWTs', () => {
  assert.ok(looksLikeSecret('a'.repeat(64)));
  assert.ok(looksLikeSecret('word '.repeat(11) + 'word'));
  assert.ok(looksLikeSecret('eyJhbGciOi.eyJzdWIi.sIg'));
  assert.ok(!looksLikeSecret('GHOST//ROOT'));
  assert.ok(!looksLikeSecret('ghost-root'));
  assert.ok(!looksLikeSecret('So11111111111111111111111111111111111111112'));
});
