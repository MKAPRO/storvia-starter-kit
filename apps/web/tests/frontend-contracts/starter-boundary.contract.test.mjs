import test from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const read = (path) => readFileSync(path, 'utf8');

const forbiddenFiles = [
  'src/app/(workspace)/files/shared/page.tsx',
  'src/app/s/[token]/page.tsx',
  'src/components/file-manager/company-distributed-node-browser.tsx',
  'src/components/file-manager/company-folder-distribution-panel.tsx',
  'src/components/file-manager/public-share-links-panel.tsx',
  'src/components/file-manager/share-node-dialog.tsx',
  'src/components/file-manager/shared-node-browser.tsx',
  'src/components/file-manager/shared-with-me-browser.tsx',
  'src/components/public-share/public-share-viewer.tsx',
  'src/lib/api/company-distribution-client.ts',
  'src/lib/api/public-sharing-client.ts',
  'src/lib/api/sharing-client.ts',
  'src/components/dashboard/dashboard-analytics.tsx',
  'src/lib/api/dashboard-analytics-client.ts',
];

function sourceFilesUnder(root) {
  const result = [];
  for (const entry of readdirSync(root)) {
    const path = join(root, entry);
    if (statSync(path).isDirectory()) result.push(...sourceFilesUnder(path));
    else if (/\.(ts|tsx)$/.test(path)) result.push(path);
  }
  return result;
}

test('Starter omits collaboration modules and routes', () => {
  for (const file of forbiddenFiles) {
    assert.equal(existsSync(file), false, `${file} must not exist in Starter`);
  }

  const routes = readFileSync('src/lib/routing/workspace-routes.ts', 'utf8');
  const nextConfig = readFileSync('next.config.ts', 'utf8');

  assert.doesNotMatch(routes, /\/files\/shared|sharedRoute|sharedRouteNodeId/);
  assert.doesNotMatch(nextConfig, /\/s\/:token|publicShareHeaders/);
});

test('Starter production source contains no removed collaboration API surface', () => {
  const hits = [];
  const forbidden = /\/api\/v1\/public-shares|\/api\/v1\/file-manager\/shared|company-distributions|\/file-manager\/distributed|company_folder_sharing_enabled|shared_with_me_count|allowed_actions\.includes\("share"\)/;

  for (const file of sourceFilesUnder('src')) {
    const normalized = relative('.', file).replaceAll('\\', '/');
    if (normalized.startsWith('src/app/design-system/')) continue;

    const source = readFileSync(file, 'utf8');
    if (forbidden.test(source)) hits.push(normalized);
  }

  assert.deepEqual(hits, []);
});


test('Starter omits advanced dashboard analytics', () => {
  const sourceFiles = sourceFilesUnder('src');
  const hits = [];

  for (const file of sourceFiles) {
    const normalized = relative('.', file).replaceAll('\\', '/');
    const source = readFileSync(file, 'utf8');
    if (/DashboardAnalytics|dashboard-analytics-client|\/api\/v1\/dashboard\/analytics/.test(source)) {
      hits.push(normalized);
    }
  }

  assert.deepEqual(hits, []);
});


test("starter edition removes audit log browser while preserving core administration routes", () => {
  const workspaceRoutes = read("src/lib/routing/workspace-routes.ts");
  const rail = read("src/components/workspace/primary-rail.tsx");
  const shell = read("src/components/workspace/workspace-shell.tsx");
  const pageTitles = read("src/lib/routing/page-titles.ts");

  assert.doesNotMatch(workspaceRoutes, /administration\/audit-logs|audit-logs/);
  assert.doesNotMatch(rail, /canViewAuditLogs|audit-logs/);
  assert.doesNotMatch(shell, /AuditLogManagement|audit-logs/);
  assert.doesNotMatch(pageTitles, /Activity & Audit|سجل التدقيق|audit-logs/);

  assert.match(workspaceRoutes, /administration\/users/);
  assert.match(workspaceRoutes, /administration\/departments/);
  assert.match(workspaceRoutes, /administration\/roles/);
});
