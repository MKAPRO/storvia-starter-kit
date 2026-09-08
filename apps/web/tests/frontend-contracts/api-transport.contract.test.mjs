import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

function filesUnder(root) {
  const result = [];
  for (const entry of readdirSync(root)) {
    const path = join(root, entry);
    if (statSync(path).isDirectory()) result.push(...filesUnder(path));
    else if (/\.(ts|tsx)$/.test(path)) result.push(path);
  }
  return result;
}

const apiRoot = 'src/lib/api';
const apiFiles = filesUnder(apiRoot);
const srcFiles = filesUnder('src');
const apiClient = readFileSync('src/lib/api/api-client.ts', 'utf8');
const fileManagerClient = readFileSync('src/lib/api/file-manager-client.ts', 'utf8');

test('api-client.ts remains the single direct fetch transport authority', () => {
  const hits = [];
  for (const file of apiFiles) {
    const source = readFileSync(file, 'utf8');
    const count = (source.match(/\bfetch\s*\(/g) ?? []).length;
    if (count > 0) hits.push([relative('.', file).replaceAll('\\', '/'), count]);
  }

  assert.deepEqual(hits, [['src/lib/api/api-client.ts', 1]]);
  assert.match(apiClient, /credentials:\s*"include"/);
  assert.match(apiClient, /headers\.set\("X-XSRF-TOKEN", token\)/);
  assert.match(apiClient, /response = await fetch\(buildApiUrl\(path\), \{[\s\S]*?\.\.\.init,/);
});

test('XHR specialization remains isolated to upload progress + CSRF retry', () => {
  const hits = [];
  for (const file of srcFiles) {
    const source = readFileSync(file, 'utf8');
    const count = (source.match(/new XMLHttpRequest\s*\(/g) ?? []).length;
    if (count > 0) hits.push([relative('.', file).replaceAll('\\', '/'), count]);
  }

  assert.deepEqual(hits, [['src/lib/api/file-manager-client.ts', 1]]);
  assert.match(fileManagerClient, /xhr\.upload\.onprogress/);
  assert.match(fileManagerClient, /xhr\.status === 419 && retryCsrf/);
  assert.match(fileManagerClient, /options\.signal\?\.addEventListener\("abort", abort/);
});

test('core file download uses the consolidated response transport', () => {
  assert.match(fileManagerClient, /downloadFileManagerFile[\s\S]*?apiFetchResponse\(/);
  assert.doesNotMatch(fileManagerClient, /\bfetch\s*\(/);
});
