import { apiFetch, csrfApiFetch } from "@/lib/api/api-client";

export const FILE_TYPE_CATEGORIES = [
  "document",
  "spreadsheet",
  "presentation",
  "text",
  "image",
  "archive",
  "other",
] as const;

export const FILE_TYPE_PREVIEW_MODES = ["none", "image", "pdf"] as const;

export type FileTypeCategory = (typeof FILE_TYPE_CATEGORIES)[number];
export type FileTypePreviewMode = (typeof FILE_TYPE_PREVIEW_MODES)[number];

export type AdministrationFileType = {
  id: string;
  extension: string;
  label: string;
  category: FileTypeCategory;
  mime_types: string[];
  is_enabled: boolean;
  preview_mode: FileTypePreviewMode;
  icon_svg: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type AdministrationFileTypePage = {
  data: AdministrationFileType[];
  current_page: number;
  last_page: number;
  total: number;
};

export type AdministrationFileTypeWritePayload = {
  extension: string;
  label: string;
  category: FileTypeCategory;
  mime_types: string[];
  is_enabled: boolean;
  preview_mode: FileTypePreviewMode;
  icon_svg: string | null;
};

export type AdministrationDepartmentFileTypePolicy = {
  department: { id: string; name: string };
  file_space_id: string;
  disabled_file_type_ids: string[];
  file_types: Array<
    AdministrationFileType & {
      department_allowed: boolean;
      effective_allowed: boolean;
    }
  >;
};

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === "object"
    ? (value as Record<string, unknown>)
    : {};
}

function stringOrNull(value: unknown): string | null {
  return value == null || value === "" ? null : String(value);
}

function safePositiveInteger(value: unknown, fallback: number): number {
  const parsed = Number(value);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
}

function isCategory(value: string): value is FileTypeCategory {
  return FILE_TYPE_CATEGORIES.includes(value as FileTypeCategory);
}

function isPreviewMode(value: string): value is FileTypePreviewMode {
  return FILE_TYPE_PREVIEW_MODES.includes(value as FileTypePreviewMode);
}

function normalizeMimeTypes(value: unknown): string[] {
  if (!Array.isArray(value)) return [];

  return value
    .filter((mime): mime is string => typeof mime === "string")
    .map((mime) => mime.trim().toLowerCase())
    .filter((mime) => mime !== "");
}

function normalizeFileType(value: unknown): AdministrationFileType | null {
  const record = asRecord(value);
  const id = String(record.id ?? "").trim();
  const extension = String(record.extension ?? "").trim().toLowerCase();
  const label = String(record.label ?? "").trim();
  const category = String(record.category ?? "").trim().toLowerCase();
  const previewMode = String(record.preview_mode ?? "").trim().toLowerCase();

  if (
    id === "" ||
    extension === "" ||
    label === "" ||
    !isCategory(category) ||
    !isPreviewMode(previewMode)
  ) {
    return null;
  }

  return {
    id,
    extension,
    label,
    category,
    mime_types: normalizeMimeTypes(record.mime_types),
    is_enabled: record.is_enabled === true,
    preview_mode: previewMode,
    icon_svg: stringOrNull(record.icon_svg),
    created_at: stringOrNull(record.created_at),
    updated_at: stringOrNull(record.updated_at),
  };
}

function unwrapData(value: unknown): unknown {
  const record = asRecord(value);
  return "data" in record ? record.data : value;
}

export async function listAdministrationFileTypes(
  filters: {
    search?: string;
    category?: FileTypeCategory | "";
    enabled?: "" | "1" | "0";
  },
  page = 1,
  signal?: AbortSignal,
): Promise<AdministrationFileTypePage> {
  const params = new URLSearchParams();
  params.set("page", String(Math.max(1, Math.floor(page))));

  if (filters.search?.trim()) params.set("search", filters.search.trim());
  if (filters.category) params.set("category", filters.category);
  if (filters.enabled) params.set("enabled", filters.enabled);

  const response = await apiFetch<unknown>(
    `/api/v1/administration/file-types?${params.toString()}`,
    { signal },
  );
  const record = asRecord(response);
  const meta = asRecord(record.meta);
  const data = Array.isArray(record.data)
    ? record.data.flatMap((item) => {
        const normalized = normalizeFileType(item);
        return normalized ? [normalized] : [];
      })
    : [];

  return {
    data,
    current_page: safePositiveInteger(meta.current_page, 1),
    last_page: safePositiveInteger(meta.last_page, 1),
    total: Math.max(0, Number(meta.total) || 0),
  };
}

export async function createAdministrationFileType(
  payload: AdministrationFileTypeWritePayload,
): Promise<AdministrationFileType> {
  const response = await csrfApiFetch<unknown>(
    "/api/v1/administration/file-types",
    {
      method: "POST",
      body: JSON.stringify(payload),
    },
  );
  const normalized = normalizeFileType(unwrapData(response));

  if (!normalized) {
    throw new Error("The file type response is invalid.");
  }

  return normalized;
}

export async function updateAdministrationFileType(
  fileTypeId: string,
  payload: Partial<AdministrationFileTypeWritePayload>,
): Promise<AdministrationFileType> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/administration/file-types/${encodeURIComponent(fileTypeId)}`,
    {
      method: "PATCH",
      body: JSON.stringify(payload),
    },
  );
  const normalized = normalizeFileType(unwrapData(response));

  if (!normalized) {
    throw new Error("The file type response is invalid.");
  }

  return normalized;
}

export async function fetchAdministrationDepartmentFileTypePolicy(
  departmentId: string,
  signal?: AbortSignal,
): Promise<AdministrationDepartmentFileTypePolicy> {
  const response = await apiFetch<unknown>(
    `/api/v1/administration/departments/${encodeURIComponent(departmentId)}/file-type-policy`,
    { signal },
  );
  return normalizeDepartmentPolicy(unwrapData(response));
}

export async function updateAdministrationDepartmentFileTypePolicy(
  departmentId: string,
  disabledFileTypeIds: string[],
): Promise<AdministrationDepartmentFileTypePolicy> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/administration/departments/${encodeURIComponent(departmentId)}/file-type-policy`,
    {
      method: "PUT",
      body: JSON.stringify({ disabled_file_type_ids: disabledFileTypeIds }),
    },
  );
  return normalizeDepartmentPolicy(unwrapData(response));
}

function normalizeDepartmentPolicy(
  value: unknown,
): AdministrationDepartmentFileTypePolicy {
  const record = asRecord(value);
  const department = asRecord(record.department);
  const fileTypes = Array.isArray(record.file_types)
    ? record.file_types.flatMap((item) => {
        const normalized = normalizeFileType(item);
        if (!normalized) return [];
        const itemRecord = asRecord(item);
        return [
          {
            ...normalized,
            department_allowed: itemRecord.department_allowed !== false,
            effective_allowed: itemRecord.effective_allowed === true,
          },
        ];
      })
    : [];

  return {
    department: {
      id: String(department.id ?? ""),
      name: String(department.name ?? ""),
    },
    file_space_id: String(record.file_space_id ?? ""),
    disabled_file_type_ids: Array.isArray(record.disabled_file_type_ids)
      ? record.disabled_file_type_ids.map(String)
      : [],
    file_types: fileTypes,
  };
}
