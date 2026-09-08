import { apiFetch, csrfApiFetch } from "@/lib/api/api-client";
import type {
  FileManagerDepartmentPathSegment,
  FileManagerSpaceType,
  FileManagerStorageQuota,
} from "@/lib/api/file-manager-client";

export type AdministrationStorageQuotaSubject = {
  id: string;
  name: string;
};

export type AdministrationStorageQuota = {
  id: string;
  type: FileManagerSpaceType;
  owner: AdministrationStorageQuotaSubject | null;
  department: AdministrationStorageQuotaSubject | null;
  department_path: FileManagerDepartmentPathSegment[];
  quota: FileManagerStorageQuota;
  created_at: string | null;
  updated_at: string | null;
};

export type AdministrationStorageQuotaPage = {
  data: AdministrationStorageQuota[];
  current_page: number;
  last_page: number;
  total: number;
};

export type AdministrationStorageQuotaFilters = {
  type?: FileManagerSpaceType;
  departmentId?: string;
  departmentScope?: "self" | "descendants" | "self_and_descendants";
  search?: string;
};

export type AdministrationStorageQuotaDepartmentOption = {
  id: string;
  name: string;
  parent_id: string | null;
  path: FileManagerDepartmentPathSegment[];
};

export type AdministrationStorageQuotaFilterOptions = {
  departments: AdministrationStorageQuotaDepartmentOption[];
};

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === "object"
    ? (value as Record<string, unknown>)
    : {};
}

function stringOrNull(value: unknown): string | null {
  return value == null || value === "" ? null : String(value);
}

function safeInteger(value: unknown, fallback = 0): number {
  const numeric = Number(value);
  return Number.isSafeInteger(numeric) && numeric >= 0 ? numeric : fallback;
}

function safeNullableInteger(value: unknown): number | null {
  if (value == null || value === "") return null;
  const numeric = Number(value);
  return Number.isSafeInteger(numeric) && numeric >= 0 ? numeric : null;
}

function normalizeSubject(value: unknown): AdministrationStorageQuotaSubject | null {
  const record = asRecord(value);
  const id = String(record.id ?? "").trim();
  const name = String(record.name ?? "").trim();

  return id && name ? { id, name } : null;
}

function normalizeDepartmentPath(value: unknown): FileManagerDepartmentPathSegment[] {
  if (!Array.isArray(value)) return [];

  return value.flatMap((segment) => {
    const record = asRecord(segment);
    const id = String(record.id ?? "").trim();
    const name = String(record.name ?? "").trim();

    return id && name ? [{ id, name }] : [];
  });
}

function normalizeQuota(value: unknown): FileManagerStorageQuota {
  const record = asRecord(value);
  const usedBytes = safeInteger(record.used_bytes);
  const limitBytes = safeNullableInteger(record.limit_bytes);
  const remainingBytes = safeNullableInteger(record.remaining_bytes);

  return {
    used_bytes: usedBytes,
    limit_bytes: limitBytes,
    remaining_bytes: limitBytes === null ? null : remainingBytes ?? 0,
    is_unlimited: limitBytes === null && record.is_unlimited !== false,
    is_over_limit:
      limitBytes !== null &&
      (record.is_over_limit === true || usedBytes > limitBytes),
  };
}

function normalizeItem(value: unknown): AdministrationStorageQuota {
  const record = asRecord(value);

  return {
    id: String(record.id ?? ""),
    type: record.type === "department" ? "department" : "personal",
    owner: normalizeSubject(record.owner),
    department: normalizeSubject(record.department),
    department_path: normalizeDepartmentPath(record.department_path),
    quota: normalizeQuota(record.quota),
    created_at: stringOrNull(record.created_at),
    updated_at: stringOrNull(record.updated_at),
  };
}

function normalizeDepartmentOption(value: unknown): AdministrationStorageQuotaDepartmentOption {
  const record = asRecord(value);

  return {
    id: String(record.id ?? ""),
    name: String(record.name ?? ""),
    parent_id: stringOrNull(record.parent_id),
    path: normalizeDepartmentPath(record.path),
  };
}

export async function listAdministrationStorageQuotas(
  filters: AdministrationStorageQuotaFilters,
  page = 1,
  signal?: AbortSignal,
): Promise<AdministrationStorageQuotaPage> {
  const params = new URLSearchParams();
  params.set("page", String(Math.max(1, Math.floor(page))));
  if (filters.type) params.set("type", filters.type);
  if (filters.departmentId) params.set("department_id", filters.departmentId);
  if (filters.departmentScope) params.set("department_scope", filters.departmentScope);
  if (filters.search?.trim()) params.set("search", filters.search.trim());

  const response = await apiFetch<unknown>(
    `/api/v1/administration/storage-quotas?${params.toString()}`,
    { signal },
  );
  const record = asRecord(response);
  const meta = asRecord(record.meta);
  const data = Array.isArray(record.data) ? record.data.map(normalizeItem) : [];

  return {
    data,
    current_page: safeInteger(meta.current_page, 1) || 1,
    last_page: safeInteger(meta.last_page, 1) || 1,
    total: safeInteger(meta.total, data.length),
  };
}

export async function fetchAdministrationStorageQuotaFilterOptions(
  signal?: AbortSignal,
): Promise<AdministrationStorageQuotaFilterOptions> {
  const response = await apiFetch<unknown>(
    "/api/v1/administration/storage-quotas/filter-options",
    { signal },
  );
  const payload = asRecord(asRecord(response).data);

  return {
    departments: Array.isArray(payload.departments)
      ? payload.departments.map(normalizeDepartmentOption)
      : [],
  };
}

export async function updateAdministrationStorageQuota(
  fileSpaceId: string,
  limitBytes: number | null,
): Promise<AdministrationStorageQuota> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/administration/storage-quotas/${encodeURIComponent(fileSpaceId)}`,
    {
      method: "PUT",
      body: JSON.stringify({ limit_bytes: limitBytes }),
    },
  );
  const record = asRecord(response);

  return normalizeItem("data" in record ? record.data : response);
}
