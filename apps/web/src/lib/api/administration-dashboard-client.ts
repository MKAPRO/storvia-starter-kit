import { apiFetch } from "@/lib/api/api-client";

export type AdminDashboardUsersSummary = {
  total_count: number;
  active_count: number;
  inactive_count: number;
};

export type AdminDashboardDepartmentsSummary = {
  total_count: number;
  active_count: number;
  inactive_count: number;
};

export type AdminDashboardFileSpacesSummary = {
  total_count: number;
  personal_count: number;
  department_count: number;
};

export type AdminDashboardStorageSummary = {
  used_bytes: string;
  finite_limit_bytes: string;
  limited_space_count: number;
  unlimited_space_count: number;
  over_limit_space_count: number;
};

export type AdminDashboardContentSummary = {
  active_files_count: number;
  active_folders_count: number;
  trash_roots_count: number;
};

export type AdminDashboard = {
  users: AdminDashboardUsersSummary;
  departments: AdminDashboardDepartmentsSummary;
  file_spaces: AdminDashboardFileSpacesSummary;
  storage: AdminDashboardStorageSummary;
  content: AdminDashboardContentSummary;
};

type ApiEnvelope = {
  data?: unknown;
};

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === "object"
    ? (value as Record<string, unknown>)
    : {};
}

function nonNegativeSafeInteger(value: unknown): number {
  const numeric = Number(value);
  return Number.isSafeInteger(numeric) && numeric >= 0 ? numeric : 0;
}

function unsignedDecimal(value: unknown): string {
  if (typeof value !== "string") {
    return "0";
  }

  const normalized = value.trim();
  if (!/^\d+$/.test(normalized)) {
    return "0";
  }

  return normalized.replace(/^0+(?=\d)/, "");
}

function normalizeAdminDashboard(value: unknown): AdminDashboard {
  const record = asRecord(value);
  const users = asRecord(record.users);
  const departments = asRecord(record.departments);
  const fileSpaces = asRecord(record.file_spaces);
  const storage = asRecord(record.storage);
  const content = asRecord(record.content);

  return {
    users: {
      total_count: nonNegativeSafeInteger(users.total_count),
      active_count: nonNegativeSafeInteger(users.active_count),
      inactive_count: nonNegativeSafeInteger(users.inactive_count),
    },
    departments: {
      total_count: nonNegativeSafeInteger(departments.total_count),
      active_count: nonNegativeSafeInteger(departments.active_count),
      inactive_count: nonNegativeSafeInteger(departments.inactive_count),
    },
    file_spaces: {
      total_count: nonNegativeSafeInteger(fileSpaces.total_count),
      personal_count: nonNegativeSafeInteger(fileSpaces.personal_count),
      department_count: nonNegativeSafeInteger(fileSpaces.department_count),
    },
    storage: {
      used_bytes: unsignedDecimal(storage.used_bytes),
      finite_limit_bytes: unsignedDecimal(storage.finite_limit_bytes),
      limited_space_count: nonNegativeSafeInteger(storage.limited_space_count),
      unlimited_space_count: nonNegativeSafeInteger(storage.unlimited_space_count),
      over_limit_space_count: nonNegativeSafeInteger(storage.over_limit_space_count),
    },
    content: {
      active_files_count: nonNegativeSafeInteger(content.active_files_count),
      active_folders_count: nonNegativeSafeInteger(content.active_folders_count),
      trash_roots_count: nonNegativeSafeInteger(content.trash_roots_count),
    },
  };
}

export async function getAdminDashboard(
  signal?: AbortSignal,
): Promise<AdminDashboard> {
  const payload = await apiFetch<ApiEnvelope>(
    "/api/v1/administration/dashboard",
    {
      method: "GET",
      signal,
    },
  );

  return normalizeAdminDashboard(payload.data);
}
