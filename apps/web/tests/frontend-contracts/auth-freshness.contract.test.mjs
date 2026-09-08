import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const context = readFileSync('src/lib/auth/auth-context.tsx', 'utf8');
const client = readFileSync('src/lib/auth/auth-client.ts', 'utf8');

test('auth freshness uses the lightweight endpoint and fixed heartbeat contract', () => {
  assert.match(client, /"\/api\/v1\/auth\/freshness"/);
  assert.match(client, /"\/api\/v1\/auth\/me"/);
  assert.match(context, /SESSION_FRESHNESS_INTERVAL_MS\s*=\s*30_000/);
  assert.match(context, /SESSION_FRESHNESS_MIN_GAP_MS\s*=\s*10_000/);
});

test('initial session request is single-flight and periodic checks are coalesced', () => {
  assert.match(
    context,
    /initialSessionRequestRef\.current\s*\?\?\s*fetchCurrentUser\(\)/,
  );
  assert.match(
    context,
    /if \(controller\) \{\s*refreshQueued = true;\s*return;\s*\}/s,
  );
  assert.match(context, /queueMicrotask\(refreshFreshSession\)/);
});

test('unchanged freshness revision does not refresh the full current-user resource', () => {
  const unchanged = context.indexOf('if (revision === freshnessRevisionRef.current)');
  assert.notEqual(unchanged, -1);

  const unchangedReturn = context.indexOf('return;', unchanged);
  const fullRefresh = context.indexOf(
    'const currentUser = await fetchCurrentUser(requestController.signal)',
    unchanged,
  );

  assert.ok(unchangedReturn > unchanged);
  assert.ok(fullRefresh > unchangedReturn);
});

test('freshness listeners and in-flight request are cleaned up on disposal', () => {
  assert.match(context, /window\.clearInterval\(intervalId\)/);
  assert.match(context, /window\.removeEventListener\("focus", refreshFreshSession\)/);
  assert.match(context, /document\.removeEventListener\("visibilitychange", onVisibilityChange\)/);
  assert.match(context, /controller\?\.abort\(\)/);
});
