import { apiFetch, csrfApiFetch } from "@/lib/api/api-client";

export type DepartmentStatus = "active" | "disabled";

export type DepartmentMember = {
  id: string;
  name: string;
  username: string | null;
  email: string;
  status: DepartmentStatus;
};

export type AdministrationDepartmentNode = {
  id: string;
  name: string;
  status: DepartmentStatus;
  parent_id: string | null;
  members_count: number;
  children: AdministrationDepartmentNode[];
};

export type AdministrationDepartmentDetails = Omit<
  AdministrationDepartmentNode,
  "children"
> & {
  members: DepartmentMember[];
  created_at: string | null;
  updated_at: string | null;
};

export type DepartmentWritePayload = {
  name: string;
  parent_id: string | null;
  is_active: boolean;
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

function normalizeStatus(record: Record<string, unknown>): DepartmentStatus {
  return record.status === "disabled" || record.is_active === false
    ? "disabled"
    : "active";
}

function normalizeMember(value: unknown): DepartmentMember {
  const record = asRecord(value);

  return {
    id: String(record.id ?? record.uuid ?? ""),
    name: String(record.name ?? ""),
    username: record.username == null ? null : String(record.username),
    email: String(record.email ?? ""),
    status: normalizeStatus(record),
  };
}

function normalizeNode(value: unknown): AdministrationDepartmentNode {
  const record = asRecord(value);

  return {
    id: String(record.id ?? record.uuid ?? ""),
    name: String(record.name ?? ""),
    status: normalizeStatus(record),
    parent_id:
      record.parent_id == null || record.parent_id === ""
        ? null
        : String(record.parent_id),
    members_count: numeric(record.members_count),
    children: Array.isArray(record.children)
      ? record.children.map(normalizeNode)
      : [],
  };
}

function normalizeDetails(value: unknown): AdministrationDepartmentDetails {
  const record = asRecord(value);

  return {
    id: String(record.id ?? record.uuid ?? ""),
    name: String(record.name ?? ""),
    status: normalizeStatus(record),
    parent_id:
      record.parent_id == null || record.parent_id === ""
        ? null
        : String(record.parent_id),
    members_count: numeric(record.members_count),
    members: Array.isArray(record.members)
      ? record.members.map(normalizeMember)
      : [],
    created_at: record.created_at == null ? null : String(record.created_at),
    updated_at: record.updated_at == null ? null : String(record.updated_at),
  };
}

function unwrapData(response: unknown): unknown {
  const record = asRecord(response);
  return "data" in record ? record.data : response;
}

export async function fetchAdministrationDepartmentTree(): Promise<
  AdministrationDepartmentNode[]
> {
  const response = await apiFetch<unknown>(
    "/api/v1/administration/departments/tree",
  );
  const payload = unwrapData(response);

  return Array.isArray(payload) ? payload.map(normalizeNode) : [];
}

export async function fetchAdministrationDepartment(
  departmentId: string,
): Promise<AdministrationDepartmentDetails> {
  const response = await apiFetch<unknown>(
    `/api/v1/administration/departments/${encodeURIComponent(departmentId)}`,
  );

  return normalizeDetails(unwrapData(response));
}

export async function createAdministrationDepartment(
  payload: DepartmentWritePayload,
): Promise<AdministrationDepartmentDetails> {
  const response = await csrfApiFetch<unknown>(
    "/api/v1/administration/departments",
    {
      method: "POST",
      body: JSON.stringify(payload),
    },
  );

  return normalizeDetails(unwrapData(response));
}

export async function updateAdministrationDepartment(
  departmentId: string,
  payload: Partial<DepartmentWritePayload>,
): Promise<AdministrationDepartmentDetails> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/administration/departments/${encodeURIComponent(departmentId)}`,
    {
      method: "PATCH",
      body: JSON.stringify(payload),
    },
  );

  return normalizeDetails(unwrapData(response));
}

export async function syncAdministrationDepartmentMembers(
  departmentId: string,
  userIds: string[],
): Promise<AdministrationDepartmentDetails> {
  const response = await csrfApiFetch<unknown>(
    `/api/v1/administration/departments/${encodeURIComponent(departmentId)}/members`,
    {
      method: "PUT",
      body: JSON.stringify({ user_ids: userIds }),
    },
  );

  return normalizeDetails(unwrapData(response));
}

export async function deleteAdministrationDepartment(
  departmentId: string,
): Promise<void> {
  await csrfApiFetch<void>(
    `/api/v1/administration/departments/${encodeURIComponent(departmentId)}`,
    { method: "DELETE" },
  );
}
