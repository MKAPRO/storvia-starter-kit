import { apiFetch, csrfApiFetch } from "@/lib/api/api-client";
import type { StorviaLocale } from "@/lib/i18n";

export type AdministrationUserStatus = "active" | "disabled";

export type AdministrationDepartment = {
  id: number | string;
  name: string;
  code?: string | null;
  children?: AdministrationDepartment[];
};

export type AdministrationUser = {
  id: string;
  name: string;
  username: string | null;
  email: string;
  status: AdministrationUserStatus;
  locale: StorviaLocale;
  personal_space_enabled: boolean;
  roles: string[];
  permissions: string[];
  departments: AdministrationDepartment[];
  last_login_at: string | null;
};

export type AdministrationUsersMeta = {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
};

export type AdministrationUsersResult = {
  users: AdministrationUser[];
  meta: AdministrationUsersMeta;
};

type UserWritePayload = {
  name: string;
  username: string;
  email: string;
  password?: string;
  password_confirmation?: string;
  locale: StorviaLocale;
  personal_space_enabled?: boolean;
};

export type AdministrationUserFileTypePolicyItem = {
  id: string;
  extension: string;
  label: string;
  category: string;
  is_enabled: boolean;
  user_allowed: boolean;
  effective_allowed: boolean;
};

export type AdministrationUserFileTypePolicy = {
  user: { id: string; name: string };
  disabled_file_type_ids: string[];
  file_types: AdministrationUserFileTypePolicyItem[];
};

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === "object"
    ? (value as Record<string, unknown>)
    : {};
}

function numeric(value: unknown, fallback: number): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

function normalizeDepartment(value: unknown): AdministrationDepartment {
  const record = asRecord(value);

  return {
    id: (record.id ?? record.uuid ?? record.name ?? "") as number | string,
    name: String(record.name ?? record.label ?? record.title ?? ""),
    code: record.code == null ? null : String(record.code),
    children: Array.isArray(record.children)
      ? record.children.map(normalizeDepartment)
      : undefined,
  };
}

function normalizeUser(value: unknown): AdministrationUser {
  const record = asRecord(value);
  const rawRoles = Array.isArray(record.roles) ? record.roles : [];
  const rawDepartments = Array.isArray(record.departments)
    ? record.departments
    : [];

  return {
    id: String(record.id ?? record.uuid ?? ""),
    name: String(record.name ?? ""),
    username: record.username == null ? null : String(record.username),
    email: String(record.email ?? ""),
    status:
      record.status === "disabled" || record.is_active === false
        ? "disabled"
        : "active",
    locale: record.locale === "ar" ? "ar" : "en",
    personal_space_enabled: record.personal_space_enabled !== false,
    roles: rawRoles
      .map((role) => {
        if (typeof role === "string") return role;
        const roleRecord = asRecord(role);
        return String(roleRecord.name ?? roleRecord.label ?? "");
      })
      .filter(Boolean),
    permissions: Array.isArray(record.permissions)
      ? record.permissions.map(String)
      : [],
    departments: rawDepartments.map(normalizeDepartment),
    last_login_at:
      record.last_login_at == null ? null : String(record.last_login_at),
  };
}

export async function listAdministrationUsers(params?: {
  search?: string;
  status?: "active" | "disabled" | "all";
  role?: string;
  department?: string;
  page?: number;
  perPage?: number;
}): Promise<AdministrationUsersResult> {
  const query = new URLSearchParams();

  if (params?.search?.trim()) {
    query.set("q", params.search.trim());
  }

  if (params?.status && params.status !== "all") {
    query.set("status", params.status);
  }

  if (params?.role) {
    query.set("role", params.role);
  }

  if (params?.department) {
    query.set("department", params.department);
  }

  query.set("page", String(params?.page ?? 1));
  query.set("per_page", String(params?.perPage ?? 25));

  const response = await apiFetch<unknown>(
    `/api/v1/administration/users?${query.toString()}`,
  );

  const record = asRecord(response);
  const rows = Array.isArray(record.data)
    ? record.data
    : Array.isArray(response)
      ? response
      : [];
  const meta = asRecord(record.meta);

  return {
    users: rows.map(normalizeUser),
    meta: {
      currentPage: numeric(meta.current_page, params?.page ?? 1),
      lastPage: Math.max(1, numeric(meta.last_page, 1)),
      perPage: numeric(meta.per_page, params?.perPage ?? 25),
      total: numeric(meta.total, rows.length),
    },
  };
}

export async function fetchAdministrationUser(
  userId: string,
): Promise<AdministrationUser> {
  const response = await apiFetch<unknown>(
    `/api/v1/administration/users/${encodeURIComponent(userId)}`,
  );
  const record = asRecord(response);

  return normalizeUser("data" in record ? record.data : response);
}

export async function createAdministrationUser(
  payload: UserWritePayload,
): Promise<AdministrationUser> {
  const response = await csrfApiFetch<unknown>(
    "/api/v1/administration/users",
    {
      method: "POST",
      body: JSON.stringify(payload),
    },
  );
  const record = asRecord(response);

  return normalizeUser("data" in record ? record.data : response);
}

export async function updateAdministrationUser(
  userId: string,
  payload: Partial<UserWritePayload>,
): Promise<AdministrationUser> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/administration/users/${encodeURIComponent(userId)}`,
    {
      method: "PATCH",
      body: JSON.stringify(payload),
    },
  );
  const record = asRecord(response);

  return normalizeUser("data" in record ? record.data : response);
}

export async function updateAdministrationUserStatus(
  userId: string,
  active: boolean,
): Promise<AdministrationUser> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/administration/users/${encodeURIComponent(userId)}/status`,
    {
      method: "PUT",
      body: JSON.stringify({ is_active: active }),
    },
  );
  const record = asRecord(response);

  return normalizeUser("data" in record ? record.data : response);
}

export async function resetAdministrationUserPassword(
  userId: string,
  password: string,
  passwordConfirmation: string,
): Promise<AdministrationUser> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/administration/users/${encodeURIComponent(userId)}/password`,
    {
      method: "PUT",
      body: JSON.stringify({
        password,
        password_confirmation: passwordConfirmation,
      }),
    },
  );
  const record = asRecord(response);

  return normalizeUser("data" in record ? record.data : response);
}

export async function syncAdministrationUserRoles(
  userId: string,
  roles: string[],
): Promise<AdministrationUser> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/administration/users/${encodeURIComponent(userId)}/roles`,
    {
      method: "PUT",
      body: JSON.stringify({ roles }),
    },
  );
  const record = asRecord(response);

  return normalizeUser("data" in record ? record.data : response);
}

export async function syncAdministrationUserDepartments(
  userId: string,
  departmentIds: Array<number | string>,
): Promise<AdministrationUser> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/administration/users/${encodeURIComponent(userId)}/departments`,
    {
      method: "PUT",
      body: JSON.stringify({ departments: departmentIds }),
    },
  );
  const record = asRecord(response);

  return normalizeUser("data" in record ? record.data : response);
}

export async function fetchDepartmentTree(): Promise<
  AdministrationDepartment[]
> {
  const response = await apiFetch<unknown>(
    "/api/v1/administration/departments/tree",
  );
  const record = asRecord(response);
  const payload = "data" in record ? record.data : response;

  if (Array.isArray(payload)) {
    return payload.map(normalizeDepartment);
  }

  const payloadRecord = asRecord(payload);
  const rows = Array.isArray(payloadRecord.departments)
    ? payloadRecord.departments
    : [];

  return rows.map(normalizeDepartment);
}

function normalizeUserFileTypePolicy(
  value: unknown,
): AdministrationUserFileTypePolicy {
  const record = asRecord(value);
  const user = asRecord(record.user);
  const fileTypes = Array.isArray(record.file_types)
    ? record.file_types.flatMap((value) => {
        const item = asRecord(value);
        const id = String(item.id ?? "").trim();
        const extension = String(item.extension ?? "").trim().toLowerCase();
        const label = String(item.label ?? "").trim();

        if (!id || !extension || !label) {
          return [];
        }

        return [
          {
            id,
            extension,
            label,
            category: String(item.category ?? "other"),
            is_enabled: item.is_enabled === true,
            user_allowed: item.user_allowed !== false,
            effective_allowed: item.effective_allowed === true,
          },
        ];
      })
    : [];

  return {
    user: {
      id: String(user.id ?? ""),
      name: String(user.name ?? ""),
    },
    disabled_file_type_ids: Array.isArray(record.disabled_file_type_ids)
      ? record.disabled_file_type_ids.map(String)
      : [],
    file_types: fileTypes,
  };
}

export async function fetchAdministrationUserFileTypePolicy(
  userId: string,
  signal?: AbortSignal,
): Promise<AdministrationUserFileTypePolicy> {
  const response = await apiFetch<unknown>(
    `/api/v1/administration/users/${encodeURIComponent(userId)}/file-type-policy`,
    { signal },
  );
  const record = asRecord(response);

  return normalizeUserFileTypePolicy("data" in record ? record.data : response);
}

export async function updateAdministrationUserFileTypePolicy(
  userId: string,
  disabledFileTypeIds: string[],
): Promise<AdministrationUserFileTypePolicy> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/administration/users/${encodeURIComponent(userId)}/file-type-policy`,
    {
      method: "PUT",
      body: JSON.stringify({ disabled_file_type_ids: disabledFileTypeIds }),
    },
  );
  const record = asRecord(response);

  return normalizeUserFileTypePolicy("data" in record ? record.data : response);
}
