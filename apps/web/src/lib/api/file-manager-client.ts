import {
  apiFetch,
  apiFetchResponse,
  buildApiUrl,
  csrfApiFetch,
  ensureCsrfCookie,
  getXsrfToken,
} from "@/lib/api/api-client";
import { ApiError, type ApiErrorPayload } from "@/lib/api/api-error";

export type FileManagerSpaceType = "personal" | "department";
export type FileManagerNodeType = "folder" | "file";

export type FileManagerSpaceAction =
  | "browse"
  | "create_folder"
  | "upload_file";

export type FileManagerDepartmentPathSegment = {
  id: string;
  name: string;
};

export type FileManagerStorageQuota = {
  used_bytes: number;
  limit_bytes: number | null;
  remaining_bytes: number | null;
  is_unlimited: boolean;
  is_over_limit: boolean;
};
export type FileManagerNodeAction =
  | "open"
  | "download"
  | "favorite"
  | "rename"
  | "move"
  | "trash"
  | "restore";

export type FileManagerFileSpace = {
  id: string;
  type: FileManagerSpaceType;
  owner_id: string | null;
  department_id: string | null;
  department_name: string | null;
  department_path: FileManagerDepartmentPathSegment[];
  department_navigation_path: FileManagerDepartmentPathSegment[];
  root_nodes_count: number | null;
  quota: FileManagerStorageQuota;
  allowed_actions: FileManagerSpaceAction[];
  created_at: string | null;
  updated_at: string | null;
};

export type FileManagerFileMetadata = {
  mime_type: string | null;
  extension: string | null;
  size: number | null;
};

export type FileManagerNodeOwner = {
  id: string;
  name: string;
};

export type FileManagerResourceAccess = {
  visibility_restricted: boolean;
  password_protected: boolean;
  locked: boolean;
  can_manage: boolean;
};

export type FileManagerAccessVisibility = "inherit" | "private" | "restricted";

export type FileManagerAccessGrant = {
  id: string;
  recipient: {
    id: string;
    name: string;
    username: string | null;
    email: string | null;
  };
  created_at: string | null;
};

export type FileManagerAccessPolicy = {
  node_id: string;
  visibility: FileManagerAccessVisibility;
  password_protected: boolean;
  effective: {
    visibility_restricted: boolean;
    password_protected: boolean;
  };
  grants: FileManagerAccessGrant[];
};

export type FileManagerAccessRecipient = {
  id: string;
  name: string;
  username: string | null;
  email: string | null;
};

export type FileManagerUnlockResult = {
  unlocked: boolean;
  password_required: boolean;
};

export type FileManagerNode = {
  id: string;
  type: FileManagerNodeType;
  name: string;
  parent_id: string | null;
  owner: FileManagerNodeOwner | null;
  children_count: number | null;
  file: FileManagerFileMetadata | null;
  is_favorite: boolean;
  allowed_actions: FileManagerNodeAction[];
  resource_access: FileManagerResourceAccess | null;
  trashed_at: string | null;
  created_at: string | null;
  updated_at: string | null;
};


export type FileManagerCursorPagination = {
  per_page: number;
  returned: number;
  has_more: boolean;
  next_cursor: string | null;
};

export type FileManagerSpecialListResult = {
  data: FileManagerNode[];
  pagination: FileManagerCursorPagination;
};

export type FileManagerTrashResult = FileManagerSpecialListResult & {
  can_empty_trash: boolean;
};

export type ListFileManagerSpecialNodesOptions = {
  search?: string;
  perPage?: number;
  cursor?: string | null;
  signal?: AbortSignal;
};

export type EmptyFileManagerTrashResult = {
  purged_nodes: number;
  purged_files: number;
  released_bytes: number;
  quota: FileManagerStorageQuota;
};

export type FileManagerBreadcrumb = {
  id: string;
  name: string;
  type: FileManagerNodeType;
};

export type FileManagerPagination = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
};

export type FileManagerBrowseQuery = {
  search: string | null;
  type: FileManagerNodeType | null;
  sort: "name" | "type" | "size" | "created_at" | "updated_at";
  direction: "asc" | "desc";
};

export type FileManagerBrowseMeta = {
  file_space: FileManagerFileSpace;
  parent: FileManagerBreadcrumb | null;
  breadcrumbs: FileManagerBreadcrumb[];
  pagination: FileManagerPagination;
  query: FileManagerBrowseQuery;
};

export type FileManagerBrowseResult = {
  data: FileManagerNode[];
  meta: FileManagerBrowseMeta;
};

export type BrowseFileManagerNodesOptions = {
  parentId?: string | null;
  search?: string;
  type?: FileManagerNodeType | null;
  sort?: FileManagerBrowseQuery["sort"];
  direction?: FileManagerBrowseQuery["direction"];
  perPage?: number;
  page?: number;
  signal?: AbortSignal;
};

export type CreateFolderPayload = {
  name: string;
  parent_id?: string | null;
};

export type UpdateFileManagerNodePayload = {
  name?: string;
  parent_id?: string | null;
};

export type FileManagerFileTypeDescriptor = {
  id: string;
  extension: string;
  label: string;
  category: string;
  preview_mode: "none" | "image" | "pdf";
  icon_svg: string | null;
  upload_allowed: boolean;
};

export type FileManagerUploadPolicy = {
  max_file_size_bytes: number;
  max_files_per_batch: number;
  accepted_extensions: string[];
  file_types: FileManagerFileTypeDescriptor[];
};

export type FileManagerUploadProgress = {
  loaded: number;
  total: number;
  percent: number;
};

export type UploadFileManagerFileOptions = {
  parentId?: string | null;
  signal?: AbortSignal;
  onProgress?: (progress: FileManagerUploadProgress) => void;
};

const inFlightSpaceUploadPolicies = new Map<
  string,
  Promise<FileManagerUploadPolicy>
>();

function abortReason(signal: AbortSignal): unknown {
  if (signal.reason !== undefined) {
    return signal.reason;
  }

  return new DOMException("The request was aborted.", "AbortError");
}

function observeWithSignal<T>(
  request: Promise<T>,
  signal?: AbortSignal,
): Promise<T> {
  if (!signal) {
    return request;
  }

  if (signal.aborted) {
    return Promise.reject(abortReason(signal));
  }

  return new Promise<T>((resolve, reject) => {
    const onAbort = () => reject(abortReason(signal));
    signal.addEventListener("abort", onAbort, { once: true });

    void request.then(resolve, reject).finally(() => {
      signal.removeEventListener("abort", onAbort);
    });
  });
}

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === "object"
    ? (value as Record<string, unknown>)
    : {};
}

function stringOrNull(value: unknown): string | null {
  return value == null || value === "" ? null : String(value);
}

function numberOrNull(value: unknown): number | null {
  if (value == null || value === "") {
    return null;
  }

  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : null;
}

function positiveInteger(value: unknown, fallback: number): number {
  const numeric = Number(value);
  return Number.isInteger(numeric) && numeric > 0 ? numeric : fallback;
}

function safeNonNegativeInteger(value: unknown, fallback = 0): number {
  const numeric = Number(value);
  return Number.isSafeInteger(numeric) && numeric >= 0 ? numeric : fallback;
}

function safeNullableNonNegativeInteger(value: unknown): number | null {
  if (value == null || value === "") {
    return null;
  }

  const numeric = Number(value);
  return Number.isSafeInteger(numeric) && numeric >= 0 ? numeric : null;
}

function normalizeFileTypeDescriptor(value: unknown): FileManagerFileTypeDescriptor | null {
  const record = asRecord(value);
  const id = String(record.id ?? "").trim();
  const extension = String(record.extension ?? "").trim().toLowerCase();
  const label = String(record.label ?? "").trim();
  const previewMode = String(record.preview_mode ?? "").trim().toLowerCase();

  if (
    id === "" ||
    extension === "" ||
    label === "" ||
    (previewMode !== "none" && previewMode !== "image" && previewMode !== "pdf")
  ) {
    return null;
  }

  return {
    id,
    extension,
    label,
    category: String(record.category ?? "other"),
    preview_mode: previewMode,
    icon_svg: stringOrNull(record.icon_svg),
    upload_allowed: record.upload_allowed === true,
  };
}

function normalizeUploadPolicy(value: unknown): FileManagerUploadPolicy {
  const payload = asRecord(unwrapData(value));
  const fileTypes = Array.isArray(payload.file_types)
    ? payload.file_types.flatMap((item) => {
        const normalized = normalizeFileTypeDescriptor(item);
        return normalized ? [normalized] : [];
      })
    : [];
  const acceptedExtensions = Array.isArray(payload.accepted_extensions)
    ? payload.accepted_extensions
        .filter((item): item is string => typeof item === "string")
        .map((item) => item.trim().toLowerCase())
        .filter((item) => item !== "")
    : fileTypes
        .filter((item) => item.upload_allowed)
        .map((item) => item.extension);

  return {
    max_file_size_bytes: positiveInteger(payload.max_file_size_bytes, 1),
    max_files_per_batch: positiveInteger(payload.max_files_per_batch, 1),
    accepted_extensions: [...new Set(acceptedExtensions)],
    file_types: fileTypes,
  };
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
    .map((segment) => {
      const record = asRecord(segment);
      const id = String(record.id ?? "").trim();
      const name = String(record.name ?? "").trim();

      return id !== "" && name !== "" ? { id, name } : null;
    })
    .filter(
      (segment): segment is FileManagerDepartmentPathSegment =>
        segment !== null,
    );
}

function normalizeSpaceActions(value: unknown): FileManagerSpaceAction[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value.filter(
    (action): action is FileManagerSpaceAction =>
      action === "browse" ||
      action === "create_folder" ||
      action === "upload_file",
  );
}

function normalizeNodeActions(value: unknown): FileManagerNodeAction[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value.filter(
    (action): action is FileManagerNodeAction =>
      action === "open" ||
      action === "download" ||
      action === "favorite" ||
      action === "rename" ||
      action === "move" ||
      action === "trash" ||
      action === "restore",
  );
}

function normalizeFileSpace(value: unknown): FileManagerFileSpace {
  const record = asRecord(value);
  const type: FileManagerSpaceType =
    record.type === "department" ? "department" : "personal";

  return {
    id: String(record.id ?? ""),
    type,
    owner_id: stringOrNull(record.owner_id),
    department_id: stringOrNull(record.department_id),
    department_name: stringOrNull(record.department_name),
    department_path: normalizeDepartmentPath(record.department_path),
    department_navigation_path: normalizeDepartmentPath(
      record.department_navigation_path,
    ),
    root_nodes_count: numberOrNull(record.root_nodes_count),
    quota: normalizeStorageQuota(record.quota),
    allowed_actions: normalizeSpaceActions(record.allowed_actions),
    created_at: stringOrNull(record.created_at),
    updated_at: stringOrNull(record.updated_at),
  };
}

function normalizeBreadcrumb(value: unknown): FileManagerBreadcrumb {
  const record = asRecord(value);

  return {
    id: String(record.id ?? ""),
    name: String(record.name ?? ""),
    type: record.type === "file" ? "file" : "folder",
  };
}

function normalizeResourceAccess(value: unknown): FileManagerResourceAccess | null {
  const record = asRecord(value);

  if (Object.keys(record).length === 0) {
    return null;
  }

  return {
    visibility_restricted: record.visibility_restricted === true,
    password_protected: record.password_protected === true,
    locked: record.locked === true,
    can_manage: record.can_manage === true,
  };
}

function normalizeAccessVisibility(value: unknown): FileManagerAccessVisibility {
  return value === "private" || value === "restricted" ? value : "inherit";
}

function normalizeAccessRecipient(value: unknown): FileManagerAccessRecipient {
  const record = asRecord(value);

  return {
    id: String(record.id ?? ""),
    name: String(record.name ?? ""),
    username: stringOrNull(record.username),
    email: stringOrNull(record.email),
  };
}

function normalizeAccessGrant(value: unknown): FileManagerAccessGrant {
  const record = asRecord(value);

  return {
    id: String(record.id ?? ""),
    recipient: normalizeAccessRecipient(record.recipient),
    created_at: stringOrNull(record.created_at),
  };
}

function normalizeAccessPolicy(value: unknown): FileManagerAccessPolicy {
  const record = asRecord(value);
  const effective = asRecord(record.effective);

  return {
    node_id: String(record.node_id ?? ""),
    visibility: normalizeAccessVisibility(record.visibility),
    password_protected: record.password_protected === true,
    effective: {
      visibility_restricted: effective.visibility_restricted === true,
      password_protected: effective.password_protected === true,
    },
    grants: Array.isArray(record.grants)
      ? record.grants.map(normalizeAccessGrant)
      : [],
  };
}

function normalizeNode(value: unknown): FileManagerNode {
  const record = asRecord(value);
  const fileRecord = asRecord(record.file);
  const ownerRecord = asRecord(record.owner);
  const isFile = record.type === "file";

  return {
    id: String(record.id ?? ""),
    type: isFile ? "file" : "folder",
    name: String(record.name ?? ""),
    parent_id: stringOrNull(record.parent_id),
    owner:
      ownerRecord.id == null
        ? null
        : {
            id: String(ownerRecord.id),
            name: String(ownerRecord.name ?? ""),
          },
    children_count: numberOrNull(record.children_count),
    file: isFile
      ? {
          mime_type: stringOrNull(fileRecord.mime_type),
          extension: stringOrNull(fileRecord.extension),
          size: numberOrNull(fileRecord.size),
        }
      : null,
    is_favorite: record.is_favorite === true,
    allowed_actions: normalizeNodeActions(record.allowed_actions),
    resource_access: normalizeResourceAccess(record.resource_access),
    trashed_at: stringOrNull(record.trashed_at),
    created_at: stringOrNull(record.created_at),
    updated_at: stringOrNull(record.updated_at),
  };
}

function unwrapData(response: unknown): unknown {
  const record = asRecord(response);
  return "data" in record ? record.data : response;
}

function encodeId(value: string): string {
  return encodeURIComponent(value);
}

export type FileManagerDownloadResult = {
  blob: Blob;
  filename: string;
};

function safeFileManagerDownloadName(value: string): string {
  const safe = value.replace(/[\u0000-\u001f\u007f/\\]+/g, "_").trim();

  if (safe === "" || safe === "." || safe === "..") {
    return "download";
  }

  return safe;
}

export async function downloadFileManagerFile(
  fileSpaceId: string,
  nodeId: string,
  filename: string,
): Promise<FileManagerDownloadResult> {
  const response = await apiFetchResponse(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/files/${encodeId(nodeId)}/download`,
    {
      method: "GET",
      headers: {
        Accept: "*/*",
      },
    },
    { fallbackErrorMessage: "The download request failed." },
  );

  let blob: Blob;

  try {
    blob = await response.blob();
  } catch (cause) {
    throw new ApiError({
      code: "NETWORK_ERROR",
      message: "The file transfer did not complete.",
      status: 0,
      cause,
    });
  }

  return {
    blob,
    filename: safeFileManagerDownloadName(filename),
  };
}

export function triggerFileManagerBrowserDownload(
  download: FileManagerDownloadResult,
): void {
  if (typeof document === "undefined") {
    return;
  }

  const objectUrl = URL.createObjectURL(download.blob);
  const anchor = document.createElement("a");

  anchor.href = objectUrl;
  anchor.download = download.filename;
  anchor.rel = "noopener";
  anchor.style.display = "none";

  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();

  window.setTimeout(() => URL.revokeObjectURL(objectUrl), 0);
}

export async function listFileManagerSpaces(
  signal?: AbortSignal,
): Promise<FileManagerFileSpace[]> {
  const response = await apiFetch<unknown>("/api/v1/file-manager/spaces", {
    signal,
  });
  const payload = unwrapData(response);

  return Array.isArray(payload) ? payload.map(normalizeFileSpace) : [];
}

export async function getFileManagerUploadPolicy(
  signal?: AbortSignal,
): Promise<FileManagerUploadPolicy> {
  const response = await apiFetch<unknown>("/api/v1/file-manager/upload-policy", {
    signal,
  });

  return normalizeUploadPolicy(response);
}

export async function getFileManagerSpaceUploadPolicy(
  fileSpaceId: string,
  signal?: AbortSignal,
): Promise<FileManagerUploadPolicy> {
  const key = encodeId(fileSpaceId);
  let request = inFlightSpaceUploadPolicies.get(key);

  if (!request) {
    request = apiFetch<unknown>(
      `/api/v1/file-manager/spaces/${key}/upload-policy`,
    )
      .then(normalizeUploadPolicy)
      .finally(() => {
        if (inFlightSpaceUploadPolicies.get(key) === request) {
          inFlightSpaceUploadPolicies.delete(key);
        }
      });

    inFlightSpaceUploadPolicies.set(key, request);
  }

  return observeWithSignal(request, signal);
}

export async function browseFileManagerNodes(
  fileSpaceId: string,
  options: BrowseFileManagerNodesOptions = {},
): Promise<FileManagerBrowseResult> {
  const params = new URLSearchParams();

  if (options.parentId) {
    params.set("parent_id", options.parentId);
  }

  const search = options.search?.trim();
  if (search) {
    params.set("search", search);
  }

  if (options.type) {
    params.set("type", options.type);
  }

  if (options.sort) {
    params.set("sort", options.sort);
  }

  if (options.direction) {
    params.set("direction", options.direction);
  }

  if (options.perPage) {
    params.set("per_page", String(options.perPage));
  }

  if (options.page) {
    params.set("page", String(options.page));
  }

  const query = params.toString();
  const response = await apiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/nodes${query ? `?${query}` : ""}`,
    { signal: options.signal },
  );
  const record = asRecord(response);
  const meta = asRecord(record.meta);
  const pagination = asRecord(meta.pagination);
  const queryMeta = asRecord(meta.query);

  return {
    data: Array.isArray(record.data) ? record.data.map(normalizeNode) : [],
    meta: {
      file_space: normalizeFileSpace(meta.file_space),
      parent:
        meta.parent == null ? null : normalizeBreadcrumb(meta.parent),
      breadcrumbs: Array.isArray(meta.breadcrumbs)
        ? meta.breadcrumbs.map(normalizeBreadcrumb)
        : [],
      pagination: {
        current_page: positiveInteger(pagination.current_page, 1),
        per_page: positiveInteger(pagination.per_page, 50),
        total: Number(pagination.total ?? 0) || 0,
        last_page: positiveInteger(pagination.last_page, 1),
        from: numberOrNull(pagination.from),
        to: numberOrNull(pagination.to),
      },
      query: {
        search: stringOrNull(queryMeta.search),
        type:
          queryMeta.type === "file"
            ? "file"
            : queryMeta.type === "folder"
              ? "folder"
              : null,
        sort:
          queryMeta.sort === "type" ||
          queryMeta.sort === "size" ||
          queryMeta.sort === "created_at" ||
          queryMeta.sort === "updated_at"
            ? queryMeta.sort
            : "name",
        direction: queryMeta.direction === "desc" ? "desc" : "asc",
      },
    },
  };
}

function specialListQuery(options: ListFileManagerSpecialNodesOptions): string {
  const params = new URLSearchParams();
  const search = options.search?.trim();

  if (search) {
    params.set("search", search);
  }

  if (options.perPage) {
    params.set("per_page", String(options.perPage));
  }

  if (options.cursor) {
    params.set("cursor", options.cursor);
  }

  const query = params.toString();
  return query ? `?${query}` : "";
}

function normalizeCursorPagination(value: unknown): FileManagerCursorPagination {
  const pagination = asRecord(value);

  return {
    per_page: positiveInteger(pagination.per_page, 50),
    returned: Number(pagination.returned ?? 0) || 0,
    has_more: pagination.has_more === true,
    next_cursor: stringOrNull(pagination.next_cursor),
  };
}

export async function listFavoriteFileManagerNodes(
  fileSpaceId: string,
  options: ListFileManagerSpecialNodesOptions = {},
): Promise<FileManagerSpecialListResult> {
  const response = await apiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/favorites${specialListQuery(options)}`,
    { signal: options.signal },
  );
  const record = asRecord(response);
  const meta = asRecord(record.meta);

  return {
    data: Array.isArray(record.data) ? record.data.map(normalizeNode) : [],
    pagination: normalizeCursorPagination(meta.pagination),
  };
}

export async function listTrashFileManagerNodes(
  fileSpaceId: string,
  options: ListFileManagerSpecialNodesOptions = {},
): Promise<FileManagerTrashResult> {
  const response = await apiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/trash${specialListQuery(options)}`,
    { signal: options.signal },
  );
  const record = asRecord(response);
  const meta = asRecord(record.meta);

  return {
    data: Array.isArray(record.data) ? record.data.map(normalizeNode) : [],
    can_empty_trash: meta.can_empty_trash === true,
    pagination: normalizeCursorPagination(meta.pagination),
  };
}

export async function uploadFileManagerFile(
  fileSpaceId: string,
  file: File,
  parentId?: string | null,
): Promise<FileManagerNode> {
  const formData = new FormData();
  formData.append("file", file);

  if (parentId) {
    formData.append("parent_id", parentId);
  }

  const response = await csrfApiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/files`,
    {
      method: "POST",
      body: formData,
    },
  );

  return normalizeNode(unwrapData(response));
}

export async function uploadFileManagerFileWithProgress(
  fileSpaceId: string,
  file: File,
  options: UploadFileManagerFileOptions = {},
): Promise<FileManagerNode> {
  await ensureCsrfCookie();

  return sendFileManagerUploadRequest(fileSpaceId, file, options, true);
}

async function sendFileManagerUploadRequest(
  fileSpaceId: string,
  file: File,
  options: UploadFileManagerFileOptions,
  retryCsrf: boolean,
): Promise<FileManagerNode> {
  if (options.signal?.aborted) {
    throw uploadCancelledError();
  }

  const path = `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/files`;

  return new Promise<FileManagerNode>((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    const abort = () => xhr.abort();

    const cleanup = () => {
      options.signal?.removeEventListener("abort", abort);
    };

    xhr.open("POST", buildApiUrl(path));
    xhr.withCredentials = true;
    xhr.setRequestHeader("Accept", "application/json");

    const token = getXsrfToken();
    if (token) {
      xhr.setRequestHeader("X-XSRF-TOKEN", token);
    }

    xhr.upload.onprogress = (event) => {
      const total = event.lengthComputable && event.total > 0 ? event.total : file.size;
      const loaded = Math.min(event.loaded, total || event.loaded);
      const percent = total > 0 ? Math.min(100, Math.round((loaded / total) * 100)) : 0;

      options.onProgress?.({ loaded, total, percent });
    };

    xhr.onerror = () => {
      cleanup();
      reject(
        new ApiError({
          code: "NETWORK_ERROR",
          message: "Unable to reach the STORVIA API.",
          status: 0,
        }),
      );
    };

    xhr.onabort = () => {
      cleanup();
      reject(uploadCancelledError());
    };

    xhr.onload = () => {
      cleanup();

      if (xhr.status === 419 && retryCsrf) {
        void ensureCsrfCookie(true)
          .then(() =>
            sendFileManagerUploadRequest(fileSpaceId, file, options, false),
          )
          .then(resolve, reject);
        return;
      }

      if (xhr.status < 200 || xhr.status >= 300) {
        reject(parseUploadApiError(xhr));
        return;
      }

      try {
        const response = JSON.parse(xhr.responseText) as unknown;
        resolve(normalizeNode(unwrapData(response)));
      } catch (cause) {
        reject(
          new ApiError({
            code: "HTTP_ERROR",
            message: "The upload response could not be read.",
            status: xhr.status,
            cause,
          }),
        );
      }
    };

    options.signal?.addEventListener("abort", abort, { once: true });

    const formData = new FormData();
    formData.append("file", file);

    if (options.parentId) {
      formData.append("parent_id", options.parentId);
    }

    xhr.send(formData);
  });
}

function parseUploadApiError(xhr: XMLHttpRequest): ApiError {
  let payload: ApiErrorPayload | null = null;

  try {
    payload = JSON.parse(xhr.responseText) as ApiErrorPayload;
  } catch {
    payload = null;
  }

  if (payload?.error?.code && payload.error.message) {
    return new ApiError({
      code: payload.error.code,
      message: payload.error.message,
      status: xhr.status,
      details: payload.error.details,
    });
  }

  return new ApiError({
    code: "HTTP_ERROR",
    message: xhr.statusText || "The upload request failed.",
    status: xhr.status,
  });
}

function uploadCancelledError(): ApiError {
  return new ApiError({
    code: "UPLOAD_CANCELLED",
    message: "The upload was cancelled.",
    status: 0,
  });
}

export async function createFileManagerFolder(
  fileSpaceId: string,
  payload: CreateFolderPayload,
): Promise<FileManagerNode> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/folders`,
    {
      method: "POST",
      body: JSON.stringify(payload),
    },
  );

  return normalizeNode(unwrapData(response));
}

export async function updateFileManagerNode(
  fileSpaceId: string,
  nodeId: string,
  payload: UpdateFileManagerNodePayload,
): Promise<FileManagerNode> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/nodes/${encodeId(nodeId)}`,
    {
      method: "PATCH",
      body: JSON.stringify(payload),
    },
  );

  return normalizeNode(unwrapData(response));
}

export async function trashFileManagerNode(
  fileSpaceId: string,
  nodeId: string,
): Promise<FileManagerNode> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/nodes/${encodeId(nodeId)}`,
    { method: "DELETE" },
  );

  return normalizeNode(unwrapData(response));
}

export async function emptyFileManagerTrash(
  fileSpaceId: string,
): Promise<EmptyFileManagerTrashResult> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/trash`,
    { method: "DELETE" },
  );
  const record = asRecord(unwrapData(response));

  return {
    purged_nodes: safeNonNegativeInteger(record.purged_nodes),
    purged_files: safeNonNegativeInteger(record.purged_files),
    released_bytes: safeNonNegativeInteger(record.released_bytes),
    quota: normalizeStorageQuota(record.quota),
  };
}

export async function restoreFileManagerNode(
  fileSpaceId: string,
  nodeId: string,
): Promise<FileManagerNode> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/trash/${encodeId(nodeId)}/restore`,
    { method: "POST" },
  );

  return normalizeNode(unwrapData(response));
}

export async function setFileManagerFavorite(
  fileSpaceId: string,
  nodeId: string,
  favorite: boolean,
): Promise<FileManagerNode> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/nodes/${encodeId(nodeId)}/favorite`,
    { method: favorite ? "PUT" : "DELETE" },
  );

  return normalizeNode(unwrapData(response));
}
export async function getFileManagerAccessPolicy(
  fileSpaceId: string,
  nodeId: string,
  signal?: AbortSignal,
): Promise<FileManagerAccessPolicy> {
  const response = await apiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/nodes/${encodeId(nodeId)}/access-policy`,
    { signal },
  );

  return normalizeAccessPolicy(unwrapData(response));
}

export async function listFileManagerAccessRecipients(
  fileSpaceId: string,
  nodeId: string,
  query = "",
  signal?: AbortSignal,
): Promise<FileManagerAccessRecipient[]> {
  const params = new URLSearchParams();
  const term = query.trim();

  if (term) {
    params.set("q", term);
  }

  params.set("per_page", "20");

  const response = await apiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/nodes/${encodeId(nodeId)}/access-policy/recipients?${params.toString()}`,
    { signal },
  );
  const payload = unwrapData(response);

  return Array.isArray(payload) ? payload.map(normalizeAccessRecipient) : [];
}

export async function updateFileManagerAccessVisibility(
  fileSpaceId: string,
  nodeId: string,
  visibility: FileManagerAccessVisibility,
): Promise<FileManagerAccessPolicy> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/nodes/${encodeId(nodeId)}/access-policy`,
    {
      method: "PUT",
      body: JSON.stringify({ visibility }),
    },
  );

  return normalizeAccessPolicy(unwrapData(response));
}

export async function setFileManagerAccessPassword(
  fileSpaceId: string,
  nodeId: string,
  password: string,
): Promise<FileManagerAccessPolicy> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/nodes/${encodeId(nodeId)}/access-policy/password`,
    {
      method: "PUT",
      body: JSON.stringify({
        password,
        password_confirmation: password,
      }),
    },
  );

  return normalizeAccessPolicy(unwrapData(response));
}

export async function removeFileManagerAccessPassword(
  fileSpaceId: string,
  nodeId: string,
): Promise<FileManagerAccessPolicy> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/nodes/${encodeId(nodeId)}/access-policy/password`,
    { method: "DELETE" },
  );

  return normalizeAccessPolicy(unwrapData(response));
}

export async function grantFileManagerViewAccess(
  fileSpaceId: string,
  nodeId: string,
  recipientId: string,
): Promise<FileManagerAccessPolicy> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/nodes/${encodeId(nodeId)}/access-policy/grants`,
    {
      method: "POST",
      body: JSON.stringify({ recipient_id: recipientId }),
    },
  );

  return normalizeAccessPolicy(unwrapData(response));
}

export async function revokeFileManagerViewAccess(
  fileSpaceId: string,
  nodeId: string,
  grantId: string,
): Promise<FileManagerAccessPolicy> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/nodes/${encodeId(nodeId)}/access-policy/grants/${encodeId(grantId)}`,
    { method: "DELETE" },
  );

  return normalizeAccessPolicy(unwrapData(response));
}

export async function unlockFileManagerNode(
  fileSpaceId: string,
  nodeId: string,
  password: string,
): Promise<FileManagerUnlockResult> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/file-manager/spaces/${encodeId(fileSpaceId)}/nodes/${encodeId(nodeId)}/unlock`,
    {
      method: "POST",
      body: JSON.stringify({ password }),
    },
  );
  const record = asRecord(unwrapData(response));

  return {
    unlocked: record.unlocked === true,
    password_required: record.password_required === true,
  };
}
