"use client";

import * as React from "react";
import { KeenIcon } from "@/components/ui/keen-icon";
import { StorviaSelect } from "@/components/ui/storvia-select";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogMedia,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { ApiError } from "@/lib/api/api-error";
import {
  createAdministrationDepartment,
  deleteAdministrationDepartment,
  fetchAdministrationDepartment,
  fetchAdministrationDepartmentTree,
  syncAdministrationDepartmentMembers,
  updateAdministrationDepartment,
  type AdministrationDepartmentDetails,
  type AdministrationDepartmentNode,
  type DepartmentWritePayload,
} from "@/lib/api/administration-departments-client";
import {
  listAdministrationUsers,
  type AdministrationUser,
} from "@/lib/api/administration-users-client";
import { useAuth } from "@/lib/auth";
import { formatDateTime, formatNumber, type StorviaLocale } from "@/lib/i18n";
import { cn } from "@/lib/utils";

type Props = {
  locale: StorviaLocale;
};

type EditorMode = "create" | "edit" | "members" | null;

type FlatDepartment = AdministrationDepartmentNode & {
  depth: number;
};

const COPY = {
  en: {
    title: "Departments",
    subtitle: "Manage the organization tree, hierarchy, status, and department membership.",
    search: "Search departments...",
    all: "All",
    active: "Active",
    disabled: "Disabled",
    addDepartment: "Add department",
    refresh: "Refresh",
    organizationTree: "Organization tree",
    departmentDetails: "Department details",
    selectDepartment: "Select a department from the tree to inspect it.",
    noDepartments: "No departments match the current filters.",
    loading: "Loading departments...",
    loadingDetails: "Loading department details...",
    name: "Name",
    status: "Status",
    administration: "Administration",
    selectAdministration: "Select an administration",
    administrationItself: "Administration itself",
    parent: "Parent department",
    root: "Top-level administration",
    members: "Members",
    childDepartments: "Child departments",
    createdAt: "Created",
    updatedAt: "Last updated",
    edit: "Edit",
    delete: "Delete",
    manageMembers: "Manage members",
    deleteTitle: "Delete department?",
    deleteDescription: "This permanently deletes the department. Deletion is allowed only when it has no child departments, members, uploaded files, or folders.",
    deleting: "Deleting...",
    deleted: "Department deleted successfully.",
    deleteBlocked: "This department is not empty. Remove child departments, members, and file content before deleting it.",
    createTitle: "Create department",
    editTitle: "Edit department",
    membersTitle: "Department members",
    save: "Save changes",
    create: "Create department",
    saving: "Saving...",
    cancel: "Cancel",
    close: "Close",
    created: "Department created successfully.",
    updated: "Department updated successfully.",
    membersUpdated: "Department membership updated successfully.",
    unknownError: "The request could not be completed.",
    noPermission: "You do not have permission to view departments.",
    noCreatePermission: "You do not have permission to create departments.",
    noUpdatePermission: "You do not have permission to update this department.",
    noDeletePermission: "You do not have permission to delete this department.",
    scopedView: "Department-scoped view",
    noMembersPermission: "You do not have permission to manage department membership.",
    memberSearch: "Search users...",
    loadingMembers: "Loading users...",
    noUsers: "No users match this search.",
    selected: "selected",
    usersViewRequired: "User directory access is required to add or remove members.",
    hierarchyHint: "A department cannot be its own parent or be placed under one of its descendants.",
    activeHint: "Disabled departments remain in the hierarchy but are marked unavailable.",
    statusActive: "Active",
    statusDisabled: "Disabled",
    member: "member",
    membersPlural: "members",
    department: "department",
    departmentsPlural: "departments",
  },
  ar: {
    title: "الإدارات",
    subtitle: "إدارة الهيكل التنظيمي والتسلسل والحالة وعضوية الإدارات.",
    search: "بحث في الإدارات...",
    all: "الكل",
    active: "نشطة",
    disabled: "معطلة",
    addDepartment: "إضافة إدارة",
    refresh: "تحديث",
    organizationTree: "الهيكل التنظيمي",
    departmentDetails: "تفاصيل الإدارة",
    selectDepartment: "اختر إدارة من الشجرة لعرض تفاصيلها.",
    noDepartments: "لا توجد إدارات مطابقة للفلاتر الحالية.",
    loading: "جاري تحميل الإدارات...",
    loadingDetails: "جاري تحميل تفاصيل الإدارة...",
    name: "الاسم",
    status: "الحالة",
    administration: "الإدارة",
    selectAdministration: "اختر الإدارة",
    administrationItself: "الإدارة نفسها",
    parent: "القسم الأب",
    root: "إدارة رئيسية",
    members: "الأعضاء",
    childDepartments: "الإدارات الفرعية",
    createdAt: "تاريخ الإنشاء",
    updatedAt: "آخر تحديث",
    edit: "تعديل",
    delete: "حذف",
    manageMembers: "إدارة الأعضاء",
    deleteTitle: "حذف الإدارة؟",
    deleteDescription: "سيتم حذف الإدارة نهائيًا. يسمح بالحذف فقط إذا لم يكن لديها إدارات فرعية أو أعضاء أو ملفات أو مجلدات مرفوعة.",
    deleting: "جاري الحذف...",
    deleted: "تم حذف الإدارة بنجاح.",
    deleteBlocked: "لا يمكن حذف هذه الإدارة لأنها غير فارغة. أزل الإدارات الفرعية والأعضاء ومحتوى الملفات أولًا.",
    createTitle: "إضافة إدارة",
    editTitle: "تعديل الإدارة",
    membersTitle: "أعضاء الإدارة",
    save: "حفظ التغييرات",
    create: "إنشاء الإدارة",
    saving: "جاري الحفظ...",
    cancel: "إلغاء",
    close: "إغلاق",
    created: "تم إنشاء الإدارة بنجاح.",
    updated: "تم تحديث الإدارة بنجاح.",
    membersUpdated: "تم تحديث عضوية الإدارة بنجاح.",
    unknownError: "تعذر إكمال الطلب.",
    noPermission: "لا تملك صلاحية عرض الإدارات.",
    noCreatePermission: "لا تملك صلاحية إنشاء الإدارات.",
    noUpdatePermission: "لا تملك صلاحية تعديل هذه الإدارة.",
    noDeletePermission: "لا تملك صلاحية حذف هذه الإدارة.",
    scopedView: "عرض مقيّد بإدارتك",
    noMembersPermission: "لا تملك صلاحية إدارة عضوية الإدارات.",
    memberSearch: "بحث في المستخدمين...",
    loadingMembers: "جاري تحميل المستخدمين...",
    noUsers: "لا يوجد مستخدمون مطابقون للبحث.",
    selected: "محدد",
    usersViewRequired: "تحتاج صلاحية عرض المستخدمين لإضافة أو إزالة أعضاء الإدارة.",
    hierarchyHint: "لا يمكن جعل الإدارة أبًا لنفسها أو نقلها تحت إحدى إداراتها الفرعية.",
    activeHint: "الإدارة المعطلة تبقى داخل الهيكل التنظيمي مع تمييز حالتها.",
    statusActive: "نشطة",
    statusDisabled: "معطلة",
    member: "عضو",
    membersPlural: "أعضاء",
    department: "إدارة",
    departmentsPlural: "إدارات",
  },
} as const;

function readableError(error: unknown, fallback: string): string {
  if (error instanceof ApiError) return error.message;
  if (error instanceof Error) return error.message;
  return fallback;
}

function flattenDepartments(
  nodes: AdministrationDepartmentNode[],
  depth = 0,
): FlatDepartment[] {
  return nodes.flatMap((node) => [
    { ...node, depth },
    ...flattenDepartments(node.children, depth + 1),
  ]);
}

function descendantIds(node: AdministrationDepartmentNode): Set<string> {
  const ids = new Set<string>();

  const visit = (current: AdministrationDepartmentNode) => {
    for (const child of current.children) {
      ids.add(child.id);
      visit(child);
    }
  };

  visit(node);
  return ids;
}

function findDepartment(
  nodes: AdministrationDepartmentNode[],
  id: string,
): AdministrationDepartmentNode | null {
  for (const node of nodes) {
    if (node.id === id) return node;
    const nested = findDepartment(node.children, id);
    if (nested) return nested;
  }

  return null;
}

function rootAdministrationId(
  nodes: AdministrationDepartmentNode[],
  id: string,
): string | null {
  for (const root of nodes) {
    if (root.id === id || findDepartment(root.children, id)) {
      return root.id;
    }
  }

  return null;
}

function filterTree(
  nodes: AdministrationDepartmentNode[],
  query: string,
  status: "all" | "active" | "disabled",
): AdministrationDepartmentNode[] {
  const normalizedQuery = query.trim().toLocaleLowerCase();

  return nodes.flatMap((node) => {
    const children = filterTree(node.children, query, status);
    const matchesQuery =
      normalizedQuery.length === 0 ||
      node.name.toLocaleLowerCase().includes(normalizedQuery);
    const matchesStatus = status === "all" || node.status === status;

    if ((matchesQuery && matchesStatus) || children.length > 0) {
      return [{ ...node, children }];
    }

    return [];
  });
}


export function DepartmentManagement({ locale }: Props) {
  const t = COPY[locale];
  const { user: actor } = useAuth();
  const permissions = React.useMemo(
    () => new Set(actor?.permissions ?? []),
    [actor?.permissions],
  );
  const isSuperAdmin = actor?.roles.includes("super_admin") ?? false;
  const canView = isSuperAdmin || permissions.has("departments.view");
  const canViewAll = isSuperAdmin || permissions.has("departments.view_all");
  const canCreate = isSuperAdmin || permissions.has("departments.create");
  const canUpdate = isSuperAdmin || permissions.has("departments.update");
  const canDelete = isSuperAdmin || permissions.has("departments.delete");
  const canManageMembers =
    isSuperAdmin || permissions.has("departments.manage_members");
  const canViewUsers = isSuperAdmin || permissions.has("users.view");

  const [tree, setTree] = React.useState<AdministrationDepartmentNode[]>([]);
  const [selectedId, setSelectedId] = React.useState<string | null>(null);
  const [selected, setSelected] = React.useState<AdministrationDepartmentDetails | null>(null);
  const [query, setQuery] = React.useState("");
  const [statusFilter, setStatusFilter] = React.useState<"all" | "active" | "disabled">("all");
  const [loading, setLoading] = React.useState(true);
  const [detailsLoading, setDetailsLoading] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const [notice, setNotice] = React.useState<string | null>(null);
  const [editorMode, setEditorMode] = React.useState<EditorMode>(null);
  const [deleteOpen, setDeleteOpen] = React.useState(false);
  const [deleting, setDeleting] = React.useState(false);

  const flatDepartments = React.useMemo(() => flattenDepartments(tree), [tree]);
  const filteredTree = React.useMemo(
    () => filterTree(tree, query, statusFilter),
    [query, statusFilter, tree],
  );
  const selectedNode = React.useMemo(
    () => (selectedId ? findDepartment(tree, selectedId) : null),
    [selectedId, tree],
  );

  const loadDepartment = React.useCallback(
    async (departmentId: string) => {
      setDetailsLoading(true);
      setError(null);

      try {
        setSelected(await fetchAdministrationDepartment(departmentId));
      } catch (requestError) {
        setSelected(null);
        setError(readableError(requestError, t.unknownError));
      } finally {
        setDetailsLoading(false);
      }
    },
    [t.unknownError],
  );

  const loadTree = React.useCallback(async (preferredId?: string | null) => {
    if (!canView) {
      setTree([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const nextTree = await fetchAdministrationDepartmentTree();
      setTree(nextTree);

      const preferredStillExists = preferredId
        ? findDepartment(nextTree, preferredId)
        : null;
      const nextSelectedId = preferredStillExists?.id ?? nextTree[0]?.id ?? null;

      setSelectedId(nextSelectedId);
      if (nextSelectedId) {
        await loadDepartment(nextSelectedId);
      } else {
        setSelected(null);
      }
    } catch (requestError) {
      setTree([]);
      setSelected(null);
      setError(readableError(requestError, t.unknownError));
    } finally {
      setLoading(false);
    }
  }, [canView, loadDepartment, t.unknownError]);

  React.useEffect(() => {
    const timeout = window.setTimeout(() => {
      void loadTree(null);
    }, 0);

    return () => window.clearTimeout(timeout);
  }, [loadTree]);

  async function selectDepartment(departmentId: string) {
    setSelectedId(departmentId);
    setNotice(null);
    await loadDepartment(departmentId);
  }

  async function handleSaved(message: string, departmentId?: string) {
    setEditorMode(null);
    setNotice(message);
    setError(null);
    await loadTree(departmentId ?? selectedId);
  }

  async function handleDelete() {
    if (!selected || !canDelete) return;

    setDeleting(true);
    setError(null);

    try {
      await deleteAdministrationDepartment(selected.id);
      setDeleteOpen(false);
      setSelected(null);
      setSelectedId(null);
      setNotice(t.deleted);
      await loadTree(null);
    } catch (requestError) {
      if (requestError instanceof ApiError && requestError.code === "RESOURCE_CONFLICT") {
        setError(t.deleteBlocked);
      } else {
        setError(readableError(requestError, t.unknownError));
      }
    } finally {
      setDeleting(false);
    }
  }

  if (!canView) {
    return (
      <main className="min-w-0 flex-1 overflow-y-auto bg-workspace-background p-4 sm:p-5 lg:p-6">
        <div className="mx-auto flex min-h-[420px] max-w-[980px] items-center justify-center rounded-xl border border-dashed border-workspace-content-border bg-card/55 p-8 text-center">
          <div className="max-w-md">
            <div className="mx-auto flex size-12 items-center justify-center rounded-xl bg-destructive/10 text-destructive">
              <KeenIcon name="shield-tick" className="text-[21px]" />
            </div>
            <p className="mt-4 text-sm font-semibold">{t.noPermission}</p>
          </div>
        </div>
      </main>
    );
  }

  return (
    <main className="min-w-0 flex-1 overflow-y-auto bg-workspace-background p-4 sm:p-5 lg:p-6">
      <div className="mx-auto flex w-full max-w-[1640px] flex-col gap-5">
        <header className="rounded-xl border border-workspace-content-border bg-workspace-panel p-5 shadow-sm">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex min-w-0 items-center gap-3">
              <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-cyan/12 text-brand-cyan ring-1 ring-brand-cyan/20">
                <KeenIcon name="element-11" className="text-[22px]" />
              </div>
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <h1 className="text-xl font-bold text-foreground">{t.title}</h1>
                  <span className="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold text-muted-foreground">
                    {formatNumber(flatDepartments.length, locale)} {flatDepartments.length === 1 ? t.department : t.departmentsPlural}
                  </span>
                  {!canViewAll && (
                    <span className="rounded-full bg-brand-cyan/10 px-2.5 py-1 text-xs font-semibold text-brand-cyan">
                      {t.scopedView}
                    </span>
                  )}
                </div>
                <p className="mt-1 text-sm text-muted-foreground">{t.subtitle}</p>
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                onClick={() => void loadTree(selectedId)}
                className="inline-flex h-10 items-center gap-2 rounded-lg border border-border bg-background px-3 text-sm font-semibold hover:bg-accent"
              >
                <KeenIcon name="arrows-circle" className="text-[16px]" />
                {t.refresh}
              </button>
              {canCreate && (
                <button
                  type="button"
                  onClick={() => {
                    setNotice(null);
                    setEditorMode("create");
                  }}
                  className="inline-flex h-10 items-center gap-2 rounded-lg bg-brand-cyan px-4 text-sm font-semibold text-white hover:opacity-90"
                >
                  <KeenIcon name="plus" className="text-[16px]" />
                  {t.addDepartment}
                </button>
              )}
            </div>
          </div>
        </header>

        {(error || notice) && (
          <div
            className={cn(
              "rounded-xl border px-4 py-3 text-sm",
              error
                ? "border-destructive/25 bg-destructive/5 text-destructive"
                : "border-emerald-500/25 bg-emerald-500/5 text-emerald-700 dark:text-emerald-300",
            )}
          >
            {error ?? notice}
          </div>
        )}

        <div className="grid min-h-[620px] gap-5 xl:grid-cols-[minmax(360px,0.9fr)_minmax(0,1.35fr)]">
          <section className="overflow-hidden rounded-xl border border-workspace-content-border bg-workspace-panel shadow-sm">
            <div className="border-b border-border p-4">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <h2 className="text-sm font-bold">{t.organizationTree}</h2>
                  <p className="mt-1 text-xs text-muted-foreground">
                    {formatNumber(flatDepartments.length, locale)} {t.departmentsPlural}
                  </p>
                </div>
                <KeenIcon name="element-11" className="text-[18px] text-brand-cyan" />
              </div>

              <div className="mt-4 grid gap-2 sm:grid-cols-[minmax(0,1fr)_150px] xl:grid-cols-1 2xl:grid-cols-[minmax(0,1fr)_150px]">
                <div className="relative">
                  <KeenIcon
                    name="magnifier"
                    className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-[15px] text-muted-foreground"
                  />
                  <input
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder={t.search}
                    className="h-10 w-full rounded-lg border border-border bg-background pe-3 ps-9 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/30"
                  />
                </div>

                <StorviaSelect
                  value={statusFilter}
                  onValueChange={(value) =>
                    setStatusFilter(
                      value === "active" || value === "disabled"
                        ? value
                        : "all",
                    )
                  }
                  options={[
                    { value: "all", label: t.all },
                    { value: "active", label: t.active },
                    { value: "disabled", label: t.disabled },
                  ]}
                  ariaLabel={t.status}
                  className="h-10 w-36 rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/30"
                />
              </div>
            </div>

            <div className="max-h-[700px] overflow-y-auto p-2">
              {loading ? (
                <div className="flex min-h-64 items-center justify-center gap-2 text-sm text-muted-foreground">
                  <KeenIcon name="loading" className="animate-spin text-[17px] text-brand-cyan" />
                  {t.loading}
                </div>
              ) : filteredTree.length ? (
                <DepartmentTree
                  nodes={filteredTree}
                  locale={locale}
                  selectedId={selectedId}
                  onSelect={(id) => void selectDepartment(id)}
                />
              ) : (
                <div className="flex min-h-64 flex-col items-center justify-center px-6 text-center">
                  <div className="flex size-11 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                    <KeenIcon name="element-11" className="text-[20px]" />
                  </div>
                  <p className="mt-3 text-sm font-semibold">{t.noDepartments}</p>
                </div>
              )}
            </div>
          </section>

          <section className="overflow-hidden rounded-xl border border-workspace-content-border bg-workspace-panel shadow-sm">
            <div className="border-b border-border px-5 py-4">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <h2 className="text-sm font-bold">{t.departmentDetails}</h2>
                  {selectedNode && (
                    <p className="mt-1 text-xs text-muted-foreground">{selectedNode.name}</p>
                  )}
                </div>

                {selected && (
                  <div className="flex flex-wrap items-center gap-2">
                    {canManageMembers && (
                      <button
                        type="button"
                        onClick={() => {
                          setNotice(null);
                          setEditorMode("members");
                        }}
                        className="inline-flex h-9 items-center gap-2 rounded-lg border border-border bg-background px-3 text-xs font-semibold hover:bg-accent"
                      >
                        <KeenIcon name="people" className="text-[15px]" />
                        {t.manageMembers}
                      </button>
                    )}
                    {canDelete && (
                      <button
                        type="button"
                        onClick={() => {
                          setNotice(null);
                          setDeleteOpen(true);
                        }}
                        className="inline-flex h-9 items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 text-xs font-semibold text-destructive hover:bg-destructive/10"
                      >
                        <KeenIcon name="trash" className="text-[15px]" />
                        {t.delete}
                      </button>
                    )}
                    {canUpdate && (
                      <button
                        type="button"
                        onClick={() => {
                          setNotice(null);
                          setEditorMode("edit");
                        }}
                        className="inline-flex h-9 items-center gap-2 rounded-lg bg-brand-cyan px-3 text-xs font-semibold text-white hover:opacity-90"
                      >
                        <KeenIcon name="document" className="text-[15px]" />
                        {t.edit}
                      </button>
                    )}
                  </div>
                )}
              </div>
            </div>

            {detailsLoading ? (
              <div className="flex min-h-[520px] items-center justify-center gap-2 text-sm text-muted-foreground">
                <KeenIcon name="loading" className="animate-spin text-[17px] text-brand-cyan" />
                {t.loadingDetails}
              </div>
            ) : selected ? (
              <DepartmentDetails
                department={selected}
                tree={tree}
                locale={locale}
              />
            ) : (
              <div className="flex min-h-[520px] flex-col items-center justify-center px-8 text-center">
                <div className="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                  <KeenIcon name="element-11" className="text-[21px]" />
                </div>
                <p className="mt-4 max-w-sm text-sm text-muted-foreground">
                  {t.selectDepartment}
                </p>
              </div>
            )}
          </section>
        </div>
      </div>

      {editorMode === "create" && (
        <DepartmentEditor
          locale={locale}
          mode="create"
          tree={tree}
          department={null}
          onClose={() => setEditorMode(null)}
          onSaved={(department) => void handleSaved(t.created, department.id)}
        />
      )}

      {editorMode === "edit" && selected && (
        <DepartmentEditor
          locale={locale}
          mode="edit"
          tree={tree}
          department={selected}
          onClose={() => setEditorMode(null)}
          onSaved={(department) => void handleSaved(t.updated, department.id)}
        />
      )}

      {editorMode === "members" && selected && (
        <MembersEditor
          locale={locale}
          department={selected}
          canViewUsers={canViewUsers}
          onClose={() => setEditorMode(null)}
          onSaved={(department) => void handleSaved(t.membersUpdated, department.id)}
        />
      )}

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent size="sm" dir={locale === "ar" ? "rtl" : "ltr"}>
          <AlertDialogHeader>
            <AlertDialogMedia>
              <KeenIcon name="trash" className="text-[20px]" />
            </AlertDialogMedia>
            <AlertDialogTitle>{t.deleteTitle}</AlertDialogTitle>
            <AlertDialogDescription>
              {t.deleteDescription}
              {selected ? ` ${selected.name}` : ""}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>{t.cancel}</AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              disabled={deleting}
              aria-busy={deleting}
              onClick={() => void handleDelete()}
            >
              {deleting ? t.deleting : t.delete}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </main>
  );
}

function DepartmentTree({
  nodes,
  locale,
  selectedId,
  onSelect,
}: {
  nodes: AdministrationDepartmentNode[];
  locale: StorviaLocale;
  selectedId: string | null;
  onSelect: (departmentId: string) => void;
}) {
  return (
    <div className="space-y-1">
      {nodes.map((node) => (
        <DepartmentTreeNode
          key={node.id}
          node={node}
          locale={locale}
          selectedId={selectedId}
          onSelect={onSelect}
          depth={0}
        />
      ))}
    </div>
  );
}

function DepartmentTreeNode({
  node,
  locale,
  selectedId,
  onSelect,
  depth,
}: {
  node: AdministrationDepartmentNode;
  locale: StorviaLocale;
  selectedId: string | null;
  onSelect: (departmentId: string) => void;
  depth: number;
}) {
  const t = COPY[locale];
  const [expanded, setExpanded] = React.useState(true);
  const hasChildren = node.children.length > 0;
  const active = node.id === selectedId;

  return (
    <div>
      <div
        className={cn(
          "group flex min-h-11 items-center gap-2 rounded-lg border border-transparent pe-2 transition-colors",
          active
            ? "border-brand-cyan/20 bg-brand-cyan/8 text-foreground"
            : "hover:bg-accent/55",
        )}
        style={{ paddingInlineStart: `${depth * 18 + 6}px` }}
      >
        <button
          type="button"
          aria-label={expanded ? t.close : t.organizationTree}
          disabled={!hasChildren}
          onClick={() => setExpanded((value) => !value)}
          className={cn(
            "flex size-7 shrink-0 items-center justify-center rounded-md text-muted-foreground",
            hasChildren ? "hover:bg-background" : "opacity-25",
          )}
        >
          <KeenIcon
            name={expanded ? "down" : "right"}
            className="text-[12px] rtl:rotate-180"
          />
        </button>

        <button
          type="button"
          onClick={() => onSelect(node.id)}
          className="flex min-w-0 flex-1 items-center gap-2 py-2 text-start"
        >
          <span
            className={cn(
              "flex size-8 shrink-0 items-center justify-center rounded-lg",
              active
                ? "bg-brand-cyan/14 text-brand-cyan"
                : "bg-muted text-muted-foreground group-hover:text-foreground",
            )}
          >
            <KeenIcon name="element-11" className="text-[15px]" />
          </span>

          <span className="min-w-0 flex-1">
            <span className="block truncate text-[13px] font-semibold">{node.name}</span>
            <span className="mt-0.5 flex items-center gap-2 text-[10px] text-muted-foreground">
              <span>
                {node.members_count} {node.members_count === 1 ? t.member : t.membersPlural}
              </span>
              <span aria-hidden="true">•</span>
              <span
                className={cn(
                  node.status === "active"
                    ? "text-emerald-600 dark:text-emerald-400"
                    : "text-amber-600 dark:text-amber-400",
                )}
              >
                {node.status === "active" ? t.statusActive : t.statusDisabled}
              </span>
            </span>
          </span>
        </button>
      </div>

      {hasChildren && expanded && (
        <div className="mt-1 space-y-1">
          {node.children.map((child) => (
            <DepartmentTreeNode
              key={child.id}
              node={child}
              locale={locale}
              selectedId={selectedId}
              onSelect={onSelect}
              depth={depth + 1}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function DepartmentDetails({
  department,
  tree,
  locale,
}: {
  department: AdministrationDepartmentDetails;
  tree: AdministrationDepartmentNode[];
  locale: StorviaLocale;
}) {
  const t = COPY[locale];
  const parent = department.parent_id
    ? findDepartment(tree, department.parent_id)
    : null;
  const node = findDepartment(tree, department.id);

  return (
    <div className="space-y-5 p-5">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Metric label={t.status}>
          <StatusBadge status={department.status} locale={locale} />
        </Metric>
        <Metric label={t.members} value={String(department.members_count)} />
        <Metric label={t.childDepartments} value={String(node?.children.length ?? 0)} />
        <Metric label={t.parent} value={parent?.name ?? t.root} />
      </div>

      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(280px,0.7fr)]">
        <section className="rounded-xl border border-border p-4">
          <div className="flex items-center justify-between gap-3">
            <div>
              <h3 className="text-sm font-bold">{t.members}</h3>
              <p className="mt-1 text-xs text-muted-foreground">
                {department.members_count} {department.members_count === 1 ? t.member : t.membersPlural}
              </p>
            </div>
            <KeenIcon name="people" className="text-[18px] text-brand-cyan" />
          </div>

          <div className="mt-4 divide-y divide-border overflow-hidden rounded-lg border border-border">
            {department.members.length ? (
              department.members.map((member) => (
                <div key={member.id} className="flex items-center gap-3 bg-background px-3 py-3">
                  <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-cyan/10 text-xs font-bold text-brand-cyan">
                    {initials(member.name)}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold">{member.name}</p>
                    <p className="truncate text-[11px] text-muted-foreground">
                      {member.email}
                    </p>
                  </div>
                  <StatusBadge status={member.status} locale={locale} compact />
                </div>
              ))
            ) : (
              <div className="px-4 py-8 text-center text-sm text-muted-foreground">
                {t.noUsers}
              </div>
            )}
          </div>
        </section>

        <section className="rounded-xl border border-border p-4">
          <h3 className="text-sm font-bold">{t.departmentDetails}</h3>
          <dl className="mt-4 space-y-4">
            <DetailRow label={t.name} value={department.name} />
            <DetailRow label={t.parent} value={parent?.name ?? t.root} />
            <DetailRow label={t.createdAt} value={formatDateTime(department.created_at, locale)} />
            <DetailRow label={t.updatedAt} value={formatDateTime(department.updated_at, locale)} />
          </dl>
        </section>
      </div>
    </div>
  );
}

function DepartmentEditor({
  locale,
  mode,
  tree,
  department,
  onClose,
  onSaved,
}: {
  locale: StorviaLocale;
  mode: "create" | "edit";
  tree: AdministrationDepartmentNode[];
  department: AdministrationDepartmentDetails | null;
  onClose: () => void;
  onSaved: (department: AdministrationDepartmentDetails) => void;
}) {
  const t = COPY[locale];
  const initialParentId = department?.parent_id ?? "";
  const [name, setName] = React.useState(department?.name ?? "");
  const [administrationId, setAdministrationId] = React.useState(
    initialParentId ? (rootAdministrationId(tree, initialParentId) ?? "") : "",
  );
  const [parentId, setParentId] = React.useState(initialParentId);
  const [isActive, setIsActive] = React.useState(department?.status !== "disabled");
  const [saving, setSaving] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  const invalidParentIds = React.useMemo(() => {
    if (!department) return new Set<string>();
    const node = findDepartment(tree, department.id);
    const ids = node ? descendantIds(node) : new Set<string>();
    ids.add(department.id);
    return ids;
  }, [department, tree]);

  const administrationOptions = React.useMemo(
    () => tree.filter((item) => !invalidParentIds.has(item.id)),
    [invalidParentIds, tree],
  );

  const parentOptions = React.useMemo(() => {
    if (!administrationId) return [];
    const administration = tree.find((item) => item.id === administrationId);
    if (!administration) return [];

    return flattenDepartments(administration.children, 1).filter(
      (item) => !invalidParentIds.has(item.id),
    );
  }, [administrationId, invalidParentIds, tree]);

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSaving(true);
    setError(null);

    const payload: DepartmentWritePayload = {
      name: name.trim(),
      parent_id: parentId || null,
      is_active: isActive,
    };

    try {
      const saved =
        mode === "create"
          ? await createAdministrationDepartment(payload)
          : await updateAdministrationDepartment(department!.id, payload);
      onSaved(saved);
    } catch (requestError) {
      setError(readableError(requestError, t.unknownError));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-[90] flex justify-end bg-black/45 backdrop-blur-[1px]">
      <button type="button" aria-label={t.close} className="absolute inset-0" onClick={onClose} />
      <aside
        className="relative z-10 h-full w-full max-w-[600px] overflow-y-auto border-s border-border bg-background shadow-2xl"
        dir={locale === "ar" ? "rtl" : "ltr"}
      >
        <form onSubmit={save} className="flex min-h-full flex-col">
          <header className="sticky top-0 z-10 flex items-center justify-between border-b border-border bg-background/95 px-5 py-4 backdrop-blur">
            <div>
              <h2 className="text-lg font-bold">
                {mode === "create" ? t.createTitle : t.editTitle}
              </h2>
              {department && (
                <p className="mt-1 text-xs text-muted-foreground">{department.name}</p>
              )}
            </div>
            <button type="button" onClick={onClose} className="flex size-9 items-center justify-center rounded-lg hover:bg-accent">
              <KeenIcon name="cross" className="text-[18px]" />
            </button>
          </header>

          <div className="flex-1 space-y-5 p-5">
            {error && (
              <div className="rounded-lg border border-destructive/25 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                {error}
              </div>
            )}

            <section className="space-y-4 rounded-xl border border-border p-4">
              <Field label={t.name}>
                <input
                  required
                  maxLength={120}
                  value={name}
                  onChange={(event) => setName(event.target.value)}
                  className="h-10 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/30"
                />
              </Field>

              <Field label={t.administration}>
                <StorviaSelect
                  value={administrationId}
                  onValueChange={(value) => {
                    setAdministrationId(value);
                    setParentId(value);
                  }}
                  options={[
                    { value: "", label: t.root },
                    ...administrationOptions.map((option) => ({
                      value: option.id,
                      label: option.name,
                    })),
                  ]}
                  searchable
                  searchPlaceholder={t.search}
                  placeholder={t.selectAdministration}
                  ariaLabel={t.administration}
                  className="h-10 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/30"
                />
              </Field>

              <Field label={t.parent}>
                <StorviaSelect
                  value={parentId}
                  onValueChange={setParentId}
                  options={
                    administrationId
                      ? [
                          {
                            value: administrationId,
                            label: t.administrationItself,
                          },
                          ...parentOptions.map((option) => ({
                            value: option.id,
                            label: `${"— ".repeat(Math.max(0, option.depth - 1))}${option.name}`,
                          })),
                        ]
                      : []
                  }
                  searchable
                  searchPlaceholder={t.search}
                  placeholder={
                    administrationId ? t.administrationItself : t.selectAdministration
                  }
                  ariaLabel={t.parent}
                  disabled={!administrationId}
                  className="h-10 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/30"
                />
                <p className="text-[11px] leading-5 text-muted-foreground">{t.hierarchyHint}</p>
              </Field>

              <label className="flex items-start gap-3 rounded-lg border border-border p-3">
                <input
                  type="checkbox"
                  checked={isActive}
                  onChange={(event) => setIsActive(event.target.checked)}
                  className="mt-0.5"
                />
                <span>
                  <span className="block text-sm font-semibold">{t.active}</span>
                  <span className="mt-1 block text-xs leading-5 text-muted-foreground">{t.activeHint}</span>
                </span>
              </label>
            </section>
          </div>

          <footer className="sticky bottom-0 flex justify-end gap-2 border-t border-border bg-background/95 px-5 py-4 backdrop-blur">
            <button type="button" onClick={onClose} disabled={saving} className="h-10 rounded-lg border border-border px-4 text-sm font-semibold hover:bg-accent disabled:opacity-50">
              {t.cancel}
            </button>
            <button type="submit" disabled={saving || !name.trim()} className="h-10 rounded-lg bg-brand-cyan px-5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50">
              {saving ? t.saving : mode === "create" ? t.create : t.save}
            </button>
          </footer>
        </form>
      </aside>
    </div>
  );
}

function MembersEditor({
  locale,
  department,
  canViewUsers,
  onClose,
  onSaved,
}: {
  locale: StorviaLocale;
  department: AdministrationDepartmentDetails;
  canViewUsers: boolean;
  onClose: () => void;
  onSaved: (department: AdministrationDepartmentDetails) => void;
}) {
  const t = COPY[locale];
  const [users, setUsers] = React.useState<AdministrationUser[]>([]);
  const [selectedIds, setSelectedIds] = React.useState<string[]>(
    department.members.map((member) => member.id),
  );
  const [query, setQuery] = React.useState("");
  const [loading, setLoading] = React.useState(canViewUsers);
  const [saving, setSaving] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  React.useEffect(() => {
    if (!canViewUsers) return;

    let active = true;

    async function loadUsers() {
      try {
        const collected: AdministrationUser[] = [];
        let page = 1;
        let lastPage = 1;

        do {
          const result = await listAdministrationUsers({ page, perPage: 100 });
          collected.push(...result.users);
          lastPage = result.meta.lastPage;
          page += 1;
        } while (page <= lastPage && page <= 100);

        if (active) setUsers(collected);
      } catch (requestError) {
        if (active) setError(readableError(requestError, t.unknownError));
      } finally {
        if (active) setLoading(false);
      }
    }

    void loadUsers();

    return () => {
      active = false;
    };
  }, [canViewUsers, t.unknownError]);

  const filteredUsers = React.useMemo(() => {
    const normalized = query.trim().toLocaleLowerCase();
    if (!normalized) return users;

    return users.filter((user) =>
      [user.name, user.username ?? "", user.email].some((value) =>
        value.toLocaleLowerCase().includes(normalized),
      ),
    );
  }, [query, users]);

  function toggleUser(userId: string) {
    setSelectedIds((current) =>
      current.includes(userId)
        ? current.filter((id) => id !== userId)
        : [...current, userId],
    );
  }

  async function save() {
    setSaving(true);
    setError(null);

    try {
      onSaved(await syncAdministrationDepartmentMembers(department.id, selectedIds));
    } catch (requestError) {
      setError(readableError(requestError, t.unknownError));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-[90] flex justify-end bg-black/45 backdrop-blur-[1px]">
      <button type="button" aria-label={t.close} className="absolute inset-0" onClick={onClose} />
      <aside
        className="relative z-10 flex h-full w-full max-w-[680px] flex-col border-s border-border bg-background shadow-2xl"
        dir={locale === "ar" ? "rtl" : "ltr"}
      >
        <header className="flex items-center justify-between border-b border-border px-5 py-4">
          <div>
            <h2 className="text-lg font-bold">{t.membersTitle}</h2>
            <p className="mt-1 text-xs text-muted-foreground">{department.name}</p>
          </div>
          <button type="button" onClick={onClose} className="flex size-9 items-center justify-center rounded-lg hover:bg-accent">
            <KeenIcon name="cross" className="text-[18px]" />
          </button>
        </header>

        <div className="flex-1 overflow-y-auto p-5">
          {!canViewUsers ? (
            <div className="rounded-xl border border-amber-500/25 bg-amber-500/5 p-4 text-sm text-amber-700 dark:text-amber-300">
              {t.usersViewRequired}
            </div>
          ) : (
            <>
              <div className="flex items-center justify-between gap-3">
                <div className="relative min-w-0 flex-1">
                  <KeenIcon name="magnifier" className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-[15px] text-muted-foreground" />
                  <input
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder={t.memberSearch}
                    className="h-10 w-full rounded-lg border border-border bg-background pe-3 ps-9 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/30"
                  />
                </div>
                <span className="shrink-0 rounded-full bg-muted px-2.5 py-1 text-xs font-semibold text-muted-foreground">
                  {selectedIds.length} {t.selected}
                </span>
              </div>

              {error && (
                <div className="mt-4 rounded-lg border border-destructive/25 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                  {error}
                </div>
              )}

              <div className="mt-4 overflow-hidden rounded-xl border border-border">
                {loading ? (
                  <div className="flex min-h-64 items-center justify-center gap-2 text-sm text-muted-foreground">
                    <KeenIcon name="loading" className="animate-spin text-[17px] text-brand-cyan" />
                    {t.loadingMembers}
                  </div>
                ) : filteredUsers.length ? (
                  <div className="divide-y divide-border">
                    {filteredUsers.map((user) => (
                      <label key={user.id} className="flex cursor-pointer items-center gap-3 bg-background px-4 py-3 hover:bg-accent/45">
                        <input
                          type="checkbox"
                          checked={selectedIds.includes(user.id)}
                          onChange={() => toggleUser(user.id)}
                        />
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-cyan/10 text-xs font-bold text-brand-cyan">
                          {initials(user.name)}
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm font-semibold">{user.name}</p>
                          <p className="truncate text-[11px] text-muted-foreground">{user.email}</p>
                        </div>
                        <StatusBadge status={user.status} locale={locale} compact />
                      </label>
                    ))}
                  </div>
                ) : (
                  <div className="px-4 py-10 text-center text-sm text-muted-foreground">{t.noUsers}</div>
                )}
              </div>
            </>
          )}
        </div>

        <footer className="flex justify-end gap-2 border-t border-border bg-background px-5 py-4">
          <button type="button" onClick={onClose} disabled={saving} className="h-10 rounded-lg border border-border px-4 text-sm font-semibold hover:bg-accent disabled:opacity-50">
            {t.cancel}
          </button>
          {canViewUsers && (
            <button type="button" onClick={() => void save()} disabled={saving || loading} className="h-10 rounded-lg bg-brand-cyan px-5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50">
              {saving ? t.saving : t.save}
            </button>
          )}
        </footer>
      </aside>
    </div>
  );
}

function StatusBadge({
  status,
  locale,
  compact = false,
}: {
  status: "active" | "disabled";
  locale: StorviaLocale;
  compact?: boolean;
}) {
  const t = COPY[locale];

  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full font-semibold",
        compact ? "px-2 py-0.5 text-[10px]" : "px-2.5 py-1 text-xs",
        status === "active"
          ? "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
          : "bg-amber-500/10 text-amber-700 dark:text-amber-300",
      )}
    >
      {status === "active" ? t.statusActive : t.statusDisabled}
    </span>
  );
}

function Metric({
  label,
  value,
  children,
}: {
  label: string;
  value?: string;
  children?: React.ReactNode;
}) {
  return (
    <div className="rounded-xl border border-border bg-background p-4">
      <p className="text-[11px] font-semibold text-muted-foreground">{label}</p>
      <div className="mt-2 min-h-6 text-sm font-bold">{children ?? value ?? "—"}</div>
    </div>
  );
}

function DetailRow({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-[11px] font-semibold text-muted-foreground">{label}</dt>
      <dd className="mt-1 break-words text-sm font-medium">{value}</dd>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <fieldset className="grid min-w-0 gap-1.5">
      <legend className="text-xs font-semibold text-muted-foreground">{label}</legend>
      {children}
    </fieldset>
  );
}

function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return "D";
  return parts.slice(0, 2).map((part) => part[0]).join("").toUpperCase();
}
