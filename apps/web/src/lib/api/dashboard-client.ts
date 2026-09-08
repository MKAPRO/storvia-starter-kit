import { apiFetch } from "@/lib/api/api-client";
import type {
  FileManagerDepartmentPathSegment,
  FileManagerNodeAction,
  FileManagerStorageQuota,
} from "@/lib/api/file-manager-client";

export type UserDashboardSummary = {
  files_count: number;
  folders_count: number;
  favorites_count: number;
  assigned_departments_count: number;
};

export type UserDashboardRecentFile = {
  id: string;
  name: string;
  parent_id: string | null;
  file: {
    mime_type: string | null;
    extension: string | null;
    size: number;
  };
  is_favorite: boolean;
  allowed_actions: FileManagerNodeAction[];
  file_space: {
    id: string;
    type: "personal" | "department";
    department_name: string | null;
    department_path: FileManagerDepartmentPathSegment[];
  };
  updated_at: string | null;
};

export type UserDashboard = {
  personal_space: {
    id: string;
    quota: FileManagerStorageQuota;
  } | null;
  summary: UserDashboardSummary;
  recent_files: UserDashboardRecentFile[];
};

type ApiEnvelope = {
  data?: unknown;
};

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === "object"
    ? (value as Record<string, unknown>)
    : {};
}

function safeNonNegativeInteger(value: unknown): number {
  const numeric = Number(value);
  return Number.isSafeInteger(numeric) && numeric >= 0 ? numeric : 0;
}

function safeNullableNonNegativeInteger(value: unknown): number | null {
  if (value == null || value === "") {
    return null;
  }

  const numeric = Number(value);
  return Number.isSafeInteger(numeric) && numeric >= 0 ? numeric : null;
}

function stringOrNull(value: unknown): string | null {
  if (value == null) {
    return null;
  }

  const text = String(value).trim();
  return text === "" ? null : text;
}

function normalizeStorageQuota(value: unknown): FileManagerStorageQuota {
  const record = asRecord(value);
  const usedBytes = safeNonNegativeInteger(record.used_bytes);
  const limitBytes = safeNullableNonNegativeInteger(record.limit_bytes);
  const remainingBytes = safeNullableNonNegativeInteger(record.remaining_bytes);

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

function normalizeDepartmentPath(
  value: unknown,
): FileManagerDepartmentPathSegment[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value
    .map((entry) => {
      const record = asRecord(entry);
      const id = String(record.id ?? "").trim();
      const name = String(record.name ?? "").trim();

      return id !== "" && name !== "" ? { id, name } : null;
    })
    .filter(
      (entry): entry is FileManagerDepartmentPathSegment => entry !== null,
    );
}

function normalizeNodeActions(value: unknown): FileManagerNodeAction[] {
  if (!Array.isArray(value)) {
    return [];
  }

  const allowed = new Set<FileManagerNodeAction>([
    "open",
    "download",
    "favorite",
    "rename",
    "move",
    "trash",
    "restore",
  ]);

  return value.filter(
    (entry): entry is FileManagerNodeAction =>
      typeof entry === "string" && allowed.has(entry as FileManagerNodeAction),
  );
}

function normalizeRecentFile(value: unknown): UserDashboardRecentFile | null {
  const record = asRecord(value);
  const id = String(record.id ?? "").trim();
  const name = String(record.name ?? "").trim();

  if (id === "" || name === "") {
    return null;
  }

  const file = asRecord(record.file);
  const fileSpace = asRecord(record.file_space);
  const fileSpaceId = String(fileSpace.id ?? "").trim();
  const fileSpaceType = fileSpace.type === "department" ? "department" : "personal";

  if (fileSpaceId === "") {
    return null;
  }

  return {
    id,
    name,
    parent_id: stringOrNull(record.parent_id),
    file: {
      mime_type: stringOrNull(file.mime_type),
      extension: stringOrNull(file.extension),
      size: safeNonNegativeInteger(file.size),
    },
    is_favorite: record.is_favorite === true,
    allowed_actions: normalizeNodeActions(record.allowed_actions),
    file_space: {
      id: fileSpaceId,
      type: fileSpaceType,
      department_name: stringOrNull(fileSpace.department_name),
      department_path: normalizeDepartmentPath(fileSpace.department_path),
    },
    updated_at: stringOrNull(record.updated_at),
  };
}

function normalizeDashboard(value: unknown): UserDashboard {
  const record = asRecord(value);
  const personalSpace = asRecord(record.personal_space);
  const summary = asRecord(record.summary);
  const personalSpaceId = String(personalSpace.id ?? "").trim();
  const normalizedPersonalSpace =
    record.personal_space == null || personalSpaceId === ""
      ? null
      : {
          id: personalSpaceId,
          quota: normalizeStorageQuota(personalSpace.quota),
        };

  const recentFiles = Array.isArray(record.recent_files)
    ? record.recent_files
        .map(normalizeRecentFile)
        .filter((entry): entry is UserDashboardRecentFile => entry !== null)
        .slice(0, 6)
    : [];

  return {
    personal_space: normalizedPersonalSpace,
    summary: {
      files_count: safeNonNegativeInteger(summary.files_count),
      folders_count: safeNonNegativeInteger(summary.folders_count),
      favorites_count: safeNonNegativeInteger(summary.favorites_count),
      assigned_departments_count: safeNonNegativeInteger(
        summary.assigned_departments_count,
      ),
    },
    recent_files: recentFiles,
  };
}

export async function getUserDashboard(
  signal?: AbortSignal,
): Promise<UserDashboard> {
  const payload = await apiFetch<ApiEnvelope>("/api/v1/dashboard", {
    method: "GET",
    signal,
  });

  return normalizeDashboard(payload.data);
}
