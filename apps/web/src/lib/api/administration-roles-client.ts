import { apiFetch, csrfApiFetch } from "@/lib/api/api-client";

export type AdministrationPermission = {
  name: string;
  description: string | null;
};

export type AdministrationRole = {
  id: string;
  name: string;
  label: string;
  isSystem: boolean;
  usersCount: number;
  permissionsCount: number;
  permissions: AdministrationPermission[];
  createdAt: string | null;
  updatedAt: string | null;
};

export type AdministrationRolesMeta = {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
};

export type AdministrationRolesResult = {
  roles: AdministrationRole[];
  meta: AdministrationRolesMeta;
};

export type RoleWritePayload = {
  name: string;
  label: string;
};

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === "object"
    ? (value as Record<string, unknown>)
    : {};
}

function numeric(value: unknown, fallback = 0): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

function normalizePermission(value: unknown): AdministrationPermission {
  const record = asRecord(value);

  return {
    name: String(record.name ?? ""),
    description:
      record.description == null ? null : String(record.description),
  };
}

function normalizeRole(value: unknown): AdministrationRole {
  const record = asRecord(value);
  const permissions = Array.isArray(record.permissions)
    ? record.permissions.map(normalizePermission).filter((permission) => permission.name)
    : [];

  return {
    id: String(record.id ?? record.uuid ?? ""),
    name: String(record.name ?? ""),
    label: String(record.label ?? record.name ?? ""),
    isSystem: record.is_system === true,
    usersCount: numeric(record.users_count),
    permissionsCount: numeric(record.permissions_count, permissions.length),
    permissions,
    createdAt: record.created_at == null ? null : String(record.created_at),
    updatedAt: record.updated_at == null ? null : String(record.updated_at),
  };
}

function unwrapData(response: unknown): unknown {
  const record = asRecord(response);
  return "data" in record ? record.data : response;
}

export async function listAdministrationRoles(params?: {
  search?: string;
  type?: "system" | "custom" | "all";
  page?: number;
  perPage?: number;
}): Promise<AdministrationRolesResult> {
  const query = new URLSearchParams();

  if (params?.search?.trim()) {
    query.set("q", params.search.trim());
  }

  if (params?.type && params.type !== "all") {
    query.set("type", params.type);
  }

  query.set("page", String(params?.page ?? 1));
  query.set("per_page", String(params?.perPage ?? 25));

  const response = await apiFetch<unknown>(
    `/api/v1/administration/roles?${query.toString()}`,
  );
  const record = asRecord(response);
  const rows = Array.isArray(record.data)
    ? record.data
    : Array.isArray(response)
      ? response
      : [];
  const meta = asRecord(record.meta);

  return {
    roles: rows.map(normalizeRole),
    meta: {
      currentPage: numeric(meta.current_page, params?.page ?? 1),
      lastPage: Math.max(1, numeric(meta.last_page, 1)),
      perPage: numeric(meta.per_page, params?.perPage ?? 25),
      total: numeric(meta.total, rows.length),
    },
  };
}

export async function fetchAdministrationRoleCatalog(options?: {
  includePermissions?: boolean;
}): Promise<AdministrationRole[]> {
  const collected: AdministrationRole[] = [];
  let page = 1;
  let lastPage = 1;

  do {
    const result = await listAdministrationRoles({ page, perPage: 100 });
    collected.push(...result.roles);
    lastPage = result.meta.lastPage;
    page += 1;
  } while (page <= lastPage);

  if (options?.includePermissions && collected.length > 0) {
    return Promise.all(collected.map((role) => fetchAdministrationRole(role.id)));
  }

  return collected;
}

export async function fetchAdministrationRole(
  roleId: string,
): Promise<AdministrationRole> {
  const response = await apiFetch<unknown>(
    `/api/v1/administration/roles/${encodeURIComponent(roleId)}`,
  );

  return normalizeRole(unwrapData(response));
}

export async function createAdministrationRole(
  payload: RoleWritePayload,
): Promise<AdministrationRole> {
  const response = await csrfApiFetch<unknown>(
    "/api/v1/administration/roles",
    {
      method: "POST",
      body: JSON.stringify(payload),
    },
  );

  return normalizeRole(unwrapData(response));
}

export async function updateAdministrationRole(
  roleId: string,
  payload: Partial<RoleWritePayload>,
): Promise<AdministrationRole> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/administration/roles/${encodeURIComponent(roleId)}`,
    {
      method: "PATCH",
      body: JSON.stringify(payload),
    },
  );

  return normalizeRole(unwrapData(response));
}

export async function syncAdministrationRolePermissions(
  roleId: string,
  permissions: string[],
): Promise<AdministrationRole> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/administration/roles/${encodeURIComponent(roleId)}/permissions`,
    {
      method: "PUT",
      body: JSON.stringify({ permissions }),
    },
  );

  return normalizeRole(unwrapData(response));
}

export async function fetchAdministrationPermissions(): Promise<
  AdministrationPermission[]
> {
  const response = await apiFetch<unknown>(
    "/api/v1/administration/permissions",
  );
  const payload = unwrapData(response);

  return Array.isArray(payload)
    ? payload.map(normalizePermission).filter((permission) => permission.name)
    : [];
}
