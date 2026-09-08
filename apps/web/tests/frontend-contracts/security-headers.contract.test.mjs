import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const config = readFileSync('next.config.ts', 'utf8');

test('browser security baseline remains minimal and Next-safe', () => {
  assert.match(config, /Referrer-Policy["'], value:\s*["']no-referrer/);
  assert.match(config, /X-Content-Type-Options["'], value:\s*["']nosniff/);
  assert.match(config, /X-Frame-Options["'], value:\s*["']DENY/);
  assert.match(config, /Permissions-Policy["'], value:\s*["']camera=\(\), microphone=\(\), geolocation=\(\)/);
  assert.match(config, /frame-ancestors 'none'; base-uri 'self'; object-src 'none'/);
  assert.doesNotMatch(config, /script-src|style-src|connect-src|unsafe-inline/);
});

test('HSTS remains production-only and explicit opt-in', () => {
  assert.match(config, /process\.env\.NODE_ENV !== "production"/);
  assert.match(config, /envFlag\("STORVIA_HSTS_ENABLED"\)/);
  assert.match(config, /STORVIA_HSTS_MAX_AGE/);
  assert.match(config, /STORVIA_HSTS_INCLUDE_SUBDOMAINS/);
  assert.doesNotMatch(config, /\bpreload\b/i);
});

test('Starter has no public-share browser route policy', () => {
  assert.doesNotMatch(config, /source:\s*"\/s\/:token"/);
  assert.doesNotMatch(config, /publicShareHeaders/);
});
