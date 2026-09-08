import type { AuthUser } from "@/lib/auth/auth-types";

export const DEFAULT_WORKSPACE_ROUTE = "/dashboard";

export const WORKSPACE_SECTION_ROUTES = {
  dashboard: "/dashboard",
  files: "/files",
  favorites: "/files/favorites",
  trash: "/files/trash",
  "file-settings": "/files/settings",
  administration: "/administration",
  "admin-dashboard": "/administration/dashboard",
  users: "/administration/users",
  departments: "/administration/departments",
  roles: "/administration/roles",
  permissions: "/administration/permissions",
  "storage-quotas": "/administration/storage-quotas",
  "file-types": "/administration/file-types",
  settings: "/settings",
} as const;

type WorkspaceRouteMap = typeof WORKSPACE_SECTION_ROUTES;
export type WorkspaceSection = keyof WorkspaceRouteMap;
export type WorkspaceRoute = WorkspaceRouteMap[WorkspaceSection];

type SearchParamsReader = {
  get: (name: string) => string | null;
};

export type FileManagerRouteContext = {
  spaceId: string | null;
  folderId: string | null;
  departmentId: string | null;
};

const ROUTE_TO_SECTION = new Map<string, WorkspaceSection>(
  Object.entries(WORKSPACE_SECTION_ROUTES).map(([section, route]) => [
    route,
    section as WorkspaceSection,
  ]),
);

const ADMINISTRATION_PERMISSIONS = new Set([
  "users.view",
  "departments.view",
  "roles.view",
  "permissions.view",
  "system.manage",
]);

const FILE_SPACE_SCOPED_SECTIONS = new Set<WorkspaceSection>([
  "files",
  "favorites",
  "trash",
  "file-settings",
]);

const UUID_PATTERN =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

function normalizePathname(pathname: string): string {
  if (pathname === "/") {
    return pathname;
  }

  return pathname.replace(/\/+$/, "") || "/";
}

function normalizeUuid(value: string | null | undefined): string | null {
  const normalized = value?.trim() ?? "";
  return UUID_PATTERN.test(normalized) ? normalized.toLowerCase() : null;
}

function searchParamsFromValue(
  value: string | SearchParamsReader | null | undefined,
): SearchParamsReader {
  if (!value) {
    return new URLSearchParams();
  }

  return typeof value === "string" ? new URLSearchParams(value) : value;
}

export function workspaceSectionFromPathname(
  pathname: string,
): WorkspaceSection | null {
  return ROUTE_TO_SECTION.get(normalizePathname(pathname)) ?? null;
}

export function workspaceRouteForSection(section: string): WorkspaceRoute | null {
  return Object.prototype.hasOwnProperty.call(WORKSPACE_SECTION_ROUTES, section)
    ? WORKSPACE_SECTION_ROUTES[section as WorkspaceSection]
    : null;
}

export function fileManagerRouteContext(
  searchParams?: string | SearchParamsReader | null,
): FileManagerRouteContext {
  const params = searchParamsFromValue(searchParams);
  const spaceId = normalizeUuid(params.get("space"));
  const folderId = spaceId ? normalizeUuid(params.get("folder")) : null;
  const departmentId = spaceId
    ? null
    : normalizeUuid(params.get("department"));

  return { spaceId, folderId, departmentId };
}

export function fileManagerRoute(
  spaceId?: string | null,
  folderId?: string | null,
  departmentId?: string | null,
): string {
  const normalizedSpaceId = normalizeUuid(spaceId);

  if (!normalizedSpaceId) {
    const normalizedDepartmentId = normalizeUuid(departmentId);

    if (!normalizedDepartmentId) {
      return WORKSPACE_SECTION_ROUTES.files;
    }

    return `${WORKSPACE_SECTION_ROUTES.files}?${new URLSearchParams({ department: normalizedDepartmentId }).toString()}`;
  }

  const params = new URLSearchParams({ space: normalizedSpaceId });
  const normalizedFolderId = normalizeUuid(folderId);

  if (normalizedFolderId) {
    params.set("folder", normalizedFolderId);
  }

  return `${WORKSPACE_SECTION_ROUTES.files}?${params.toString()}`;
}

export function fileManagerSectionRoute(
  section: string,
  spaceId?: string | null,
): string | null {
  const route = workspaceRouteForSection(section);

  if (!route) {
    return null;
  }

  if (!FILE_SPACE_SCOPED_SECTIONS.has(section as WorkspaceSection)) {
    return route;
  }

  if (section === "files") {
    return fileManagerRoute(spaceId, null);
  }

  const normalizedSpaceId = normalizeUuid(spaceId);

  if (!normalizedSpaceId) {
    return route;
  }

  return `${route}?${new URLSearchParams({ space: normalizedSpaceId }).toString()}`;
}

export function canonicalWorkspaceHref(
  pathname: string,
  searchParams?: string | SearchParamsReader | null,
): string | null {
  const section = workspaceSectionFromPathname(pathname);

  if (!section) {
    return null;
  }

  if (FILE_SPACE_SCOPED_SECTIONS.has(section)) {
    const context = fileManagerRouteContext(searchParams);

    if (section === "files") {
      return fileManagerRoute(
        context.spaceId,
        context.folderId,
        context.departmentId,
      );
    }

    return fileManagerSectionRoute(section, context.spaceId);
  }

  return WORKSPACE_SECTION_ROUTES[section];
}

export function canAccessWorkspaceSection(
  user: AuthUser,
  section: WorkspaceSection,
): boolean {
  const isSuperAdmin = user.roles.includes("super_admin");

  if (isSuperAdmin) {
    return true;
  }

  const permissions = new Set(user.permissions);

  switch (section) {
    case "administration":
      return [...ADMINISTRATION_PERMISSIONS].some((permission) =>
        permissions.has(permission),
      );
    case "admin-dashboard":
      return permissions.has("system.manage");
    case "users":
      return permissions.has("users.view");
    case "departments":
      return permissions.has("departments.view");
    case "roles":
      return permissions.has("roles.view");
    case "permissions":
      return permissions.has("permissions.view");
    case "storage-quotas":
    case "file-types":
      return permissions.has("system.manage");
    default:
      return true;
  }
}

export function loginPathForWorkspace(
  pathname: string,
  searchParams?: string | SearchParamsReader | null,
): string {
  const destination = canonicalWorkspaceHref(pathname, searchParams);

  if (!destination) {
    return "/login";
  }

  return `/login?next=${encodeURIComponent(destination)}`;
}

export function safeWorkspaceNext(next: string | null | undefined): string {
  if (!next || next.startsWith("//") || !next.startsWith("/")) {
    return DEFAULT_WORKSPACE_ROUTE;
  }

  let parsed: URL;

  try {
    parsed = new URL(next, "http://storvia.local");
  } catch {
    return DEFAULT_WORKSPACE_ROUTE;
  }

  if (parsed.origin !== "http://storvia.local" || parsed.hash) {
    return DEFAULT_WORKSPACE_ROUTE;
  }

  return (
    canonicalWorkspaceHref(parsed.pathname, parsed.searchParams) ??
    DEFAULT_WORKSPACE_ROUTE
  );
}
