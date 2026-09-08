"use client";

import * as React from "react";

import { KeenIcon } from "@/components/ui/keen-icon";
import { StorviaSelect } from "@/components/ui/storvia-select";
import { ApiError } from "@/lib/api/api-error";
import {
  createAdministrationRole,
  fetchAdministrationPermissions,
  fetchAdministrationRole,
  listAdministrationRoles,
  syncAdministrationRolePermissions,
  updateAdministrationRole,
  type AdministrationPermission,
  type AdministrationRole,
  type AdministrationRolesMeta,
} from "@/lib/api/administration-roles-client";
import { useAuth } from "@/lib/auth";
import { formatDateTime, type StorviaLocale } from "@/lib/i18n";
import { cn } from "@/lib/utils";

type Props = {
  locale: StorviaLocale;
};

type EditorMode = "create" | "edit" | null;

const EMPTY_META: AdministrationRolesMeta = {
  currentPage: 1,
  lastPage: 1,
  perPage: 25,
  total: 0,
};

const COPY = {
  en: {
    title: "Roles",
    subtitle: "Define application roles and manage their effective permission sets.",
    search: "Search role name or label...",
    all: "All roles",
    system: "System",
    custom: "Custom",
    addRole: "Add role",
    refresh: "Refresh",
    roles: "roles",
    role: "role",
    roleDetails: "Role details",
    selectRole: "Select a role to inspect its access definition.",
    noRoles: "No roles match the current filters.",
    loading: "Loading roles...",
    loadingDetails: "Loading role details...",
    name: "Role key",
    label: "Display label",
    type: "Type",
    users: "Assigned users",
    permissions: "Permissions",
    createdAt: "Created",
    updatedAt: "Updated",
    edit: "Edit role",
    managePermissions: "Manage permissions",
    createTitle: "Create role",
    editTitle: "Edit role",
    create: "Create role",
    save: "Save changes",
    saving: "Saving...",
    cancel: "Cancel",
    close: "Close",
    created: "Role created successfully.",
    updated: "Role updated successfully.",
    permissionsUpdated: "Role permissions updated successfully.",
    unknownError: "The request could not be completed.",
    noPermission: "You do not have permission to view roles.",
    noManagePermission: "You do not have permission to manage role definitions.",
    permissionCatalogRequired: "Permission catalog access is required to change role permissions.",
    protectedSystem: "System role",
    protectedSuperAdmin: "Protected Super Admin",
    systemHint: "System roles keep their protected identity. Their role key cannot be changed.",
    superAdminHint: "The Super Admin permission set is protected and cannot be changed from role administration.",
    roleKeyHint: "Use lowercase letters, numbers, and underscores. Example: department_manager",
    permissionsTitle: "Role permissions",
    permissionsSubtitle: "Select the capabilities this role should grant.",
    authorityBlocked: "This role already contains permissions beyond your own authority. Only a Super Admin can change its permission set.",
    permissionLimit: "You can only grant permissions that are within your own authority.",
    selected: "selected",
    noPermissions: "This role currently has no permissions.",
    noCatalogPermissions: "No permissions are available in the catalog.",
    previous: "Previous",
    next: "Next",
    page: "Page",
    of: "of",
    permissionGroups: "Permission domains",
    accessModel: "Access model",
    accessModelValue: "Backend-authorized RBAC",
  },
  ar: {
    title: "الأدوار",
    subtitle: "تعريف أدوار النظام وإدارة مجموعات الصلاحيات الفعلية لكل دور.",
    search: "بحث باسم الدور أو المسمى...",
    all: "كل الأدوار",
    system: "نظام",
    custom: "مخصص",
    addRole: "إضافة دور",
    refresh: "تحديث",
    roles: "أدوار",
    role: "دور",
    roleDetails: "تفاصيل الدور",
    selectRole: "اختر دورًا لعرض تعريف الوصول الخاص به.",
    noRoles: "لا توجد أدوار مطابقة للفلاتر الحالية.",
    loading: "جاري تحميل الأدوار...",
    loadingDetails: "جاري تحميل تفاصيل الدور...",
    name: "مفتاح الدور",
    label: "المسمى الظاهر",
    type: "النوع",
    users: "المستخدمون المسندون",
    permissions: "الصلاحيات",
    createdAt: "تاريخ الإنشاء",
    updatedAt: "آخر تحديث",
    edit: "تعديل الدور",
    managePermissions: "إدارة الصلاحيات",
    createTitle: "إضافة دور",
    editTitle: "تعديل الدور",
    create: "إنشاء الدور",
    save: "حفظ التغييرات",
    saving: "جاري الحفظ...",
    cancel: "إلغاء",
    close: "إغلاق",
    created: "تم إنشاء الدور بنجاح.",
    updated: "تم تحديث الدور بنجاح.",
    permissionsUpdated: "تم تحديث صلاحيات الدور بنجاح.",
    unknownError: "تعذر إكمال الطلب.",
    noPermission: "لا تملك صلاحية عرض الأدوار.",
    noManagePermission: "لا تملك صلاحية إدارة تعريفات الأدوار.",
    permissionCatalogRequired: "تحتاج صلاحية عرض كتالوج الصلاحيات لتعديل صلاحيات الدور.",
    protectedSystem: "دور نظام",
    protectedSuperAdmin: "مدير نظام محمي",
    systemHint: "تحافظ أدوار النظام على هويتها المحمية ولا يمكن تغيير مفتاح الدور.",
    superAdminHint: "مجموعة صلاحيات مدير النظام محمية ولا يمكن تغييرها من إدارة الأدوار.",
    roleKeyHint: "استخدم أحرفًا إنجليزية صغيرة وأرقامًا وشرطة سفلية. مثال: department_manager",
    permissionsTitle: "صلاحيات الدور",
    permissionsSubtitle: "حدد القدرات التي يمنحها هذا الدور.",
    authorityBlocked: "يحتوي هذا الدور على صلاحيات أعلى من صلاحياتك الحالية، لذلك لا يستطيع تعديل مجموعته إلا مدير النظام.",
    permissionLimit: "يمكنك منح الصلاحيات الواقعة ضمن صلاحياتك الحالية فقط.",
    selected: "محدد",
    noPermissions: "لا توجد صلاحيات مسندة لهذا الدور حاليًا.",
    noCatalogPermissions: "لا توجد صلاحيات متاحة في الكتالوج.",
    previous: "السابق",
    next: "التالي",
    page: "صفحة",
    of: "من",
    permissionGroups: "نطاقات الصلاحيات",
    accessModel: "نموذج الوصول",
    accessModelValue: "RBAC محكوم من الخادم",
  },
} as const;

const DOMAIN_LABELS = {
  en: {
    users: "Users",
    roles: "Roles",
    permissions: "Permissions",
    departments: "Departments",
    files: "Files",
    system: "System",
    other: "Other",
  },
  ar: {
    users: "المستخدمون",
    roles: "الأدوار",
    permissions: "الصلاحيات",
    departments: "الإدارات",
    files: "الملفات",
    system: "النظام",
    other: "أخرى",
  },
} as const;

function readableError(error: unknown, fallback: string): string {
  if (error instanceof ApiError) return error.message;
  if (error instanceof Error) return error.message;
  return fallback;
}


function roleDisplayLabel(role: AdministrationRole, locale: StorviaLocale): string {
  if (locale === "ar") {
    if (role.name === "super_admin") return "مدير النظام";
    if (role.name === "admin") return "مدير";
    if (role.name === "member") return "عضو";
  }

  return role.label || role.name;
}

function permissionDomain(permissionName: string): keyof (typeof DOMAIN_LABELS)["en"] {
  const prefix = permissionName.split(".")[0];
  if (prefix === "users") return "users";
  if (prefix === "roles") return "roles";
  if (prefix === "permissions") return "permissions";
  if (prefix === "departments") return "departments";
  if (prefix === "files") return "files";
  if (prefix === "system") return "system";
  return "other";
}

function groupPermissions(permissions: AdministrationPermission[]) {
  const order: Array<keyof (typeof DOMAIN_LABELS)["en"]> = [
    "users",
    "roles",
    "permissions",
    "departments",
    "files",
    "system",
    "other",
  ];

  return order
    .map((domain) => ({
      domain,
      permissions: permissions.filter(
        (permission) => permissionDomain(permission.name) === domain,
      ),
    }))
    .filter((group) => group.permissions.length > 0);
}

export function RoleManagement({ locale }: Props) {
  const t = COPY[locale];
  const { user: actor } = useAuth();
  const actorPermissions = React.useMemo(
    () => new Set(actor?.permissions ?? []),
    [actor?.permissions],
  );
  const actorIsSuperAdmin = actor?.roles.includes("super_admin") ?? false;
  const canView = actorIsSuperAdmin || actorPermissions.has("roles.view");
  const canManage = actorIsSuperAdmin || actorPermissions.has("roles.manage");
  const canViewPermissions =
    actorIsSuperAdmin || actorPermissions.has("permissions.view");

  const [roles, setRoles] = React.useState<AdministrationRole[]>([]);
  const [meta, setMeta] = React.useState<AdministrationRolesMeta>(EMPTY_META);
  const [selectedId, setSelectedId] = React.useState<string | null>(null);
  const [selected, setSelected] = React.useState<AdministrationRole | null>(null);
  const [catalog, setCatalog] = React.useState<AdministrationPermission[]>([]);
  const [query, setQuery] = React.useState("");
  const [typeFilter, setTypeFilter] = React.useState<"all" | "system" | "custom">("all");
  const [page, setPage] = React.useState(1);
  const [loading, setLoading] = React.useState(true);
  const [detailsLoading, setDetailsLoading] = React.useState(false);
  const [catalogLoading, setCatalogLoading] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const [notice, setNotice] = React.useState<string | null>(null);
  const [editorMode, setEditorMode] = React.useState<EditorMode>(null);
  const [permissionsOpen, setPermissionsOpen] = React.useState(false);

  const loadRole = React.useCallback(
    async (roleId: string) => {
      setDetailsLoading(true);
      setError(null);

      try {
        setSelected(await fetchAdministrationRole(roleId));
      } catch (requestError) {
        setSelected(null);
        setError(readableError(requestError, t.unknownError));
      } finally {
        setDetailsLoading(false);
      }
    },
    [t.unknownError],
  );

  const loadRoles = React.useCallback(
    async (preferredId?: string | null) => {
      if (!canView) {
        setRoles([]);
        setMeta(EMPTY_META);
        setLoading(false);
        return;
      }

      setLoading(true);
      setError(null);

      try {
        const result = await listAdministrationRoles({
          search: query,
          type: typeFilter,
          page,
          perPage: 25,
        });
        setRoles(result.roles);
        setMeta(result.meta);

        const nextSelectedId =
          result.roles.find((role) => role.id === preferredId)?.id ??
          result.roles[0]?.id ??
          null;
        setSelectedId(nextSelectedId);

        if (nextSelectedId) {
          await loadRole(nextSelectedId);
        } else {
          setSelected(null);
        }
      } catch (requestError) {
        setRoles([]);
        setSelected(null);
        setError(readableError(requestError, t.unknownError));
      } finally {
        setLoading(false);
      }
    },
    [canView, loadRole, page, query, t.unknownError, typeFilter],
  );

  const loadCatalog = React.useCallback(async () => {
    if (!canViewPermissions) {
      setCatalog([]);
      return;
    }

    setCatalogLoading(true);
    try {
      setCatalog(await fetchAdministrationPermissions());
    } catch (requestError) {
      setCatalog([]);
      setError(readableError(requestError, t.unknownError));
    } finally {
      setCatalogLoading(false);
    }
  }, [canViewPermissions, t.unknownError]);

  React.useEffect(() => {
    const timeout = window.setTimeout(() => {
      void loadRoles(null);
    }, 220);

    return () => window.clearTimeout(timeout);
  }, [loadRoles]);

  React.useEffect(() => {
    const timeout = window.setTimeout(() => {
      void loadCatalog();
    }, 0);

    return () => window.clearTimeout(timeout);
  }, [loadCatalog]);

  async function selectRole(roleId: string) {
    setSelectedId(roleId);
    setNotice(null);
    await loadRole(roleId);
  }

  async function handleRoleSaved(message: string, roleId: string) {
    setEditorMode(null);
    setNotice(message);
    setError(null);
    await loadRoles(roleId);
  }

  async function handlePermissionsSaved(role: AdministrationRole) {
    setSelected(role);
    setPermissionsOpen(false);
    setNotice(t.permissionsUpdated);
    setError(null);
    await loadRoles(role.id);
  }

  const selectedCanBeManaged = Boolean(
    selected &&
      canManage &&
      selected.name !== "super_admin" &&
      (actorIsSuperAdmin || !selected.isSystem),
  );

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
                <KeenIcon name="people" className="text-[22px]" />
              </div>
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <h1 className="text-xl font-bold text-foreground">{t.title}</h1>
                  <span className="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold text-muted-foreground">
                    {meta.total} {meta.total === 1 ? t.role : t.roles}
                  </span>
                </div>
                <p className="mt-1 text-sm text-muted-foreground">{t.subtitle}</p>
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                onClick={() => void loadRoles(selectedId)}
                className="inline-flex h-10 items-center gap-2 rounded-lg border border-border bg-background px-3 text-sm font-medium transition-colors hover:bg-accent"
              >
                <KeenIcon name="arrows-circle" className="text-[16px]" />
                {t.refresh}
              </button>
              {canManage && (
                <button
                  type="button"
                  onClick={() => setEditorMode("create")}
                  className="inline-flex h-10 items-center gap-2 rounded-lg bg-brand-cyan px-4 text-sm font-semibold text-white shadow-sm transition-opacity hover:opacity-90"
                >
                  <KeenIcon name="plus" className="text-[16px]" />
                  {t.addRole}
                </button>
              )}
            </div>
          </div>

          <div className="mt-5 grid gap-3 md:grid-cols-[minmax(0,1fr)_190px]">
            <div className="relative">
              <KeenIcon name="magnifier" className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-[17px] text-muted-foreground" />
              <input
                value={query}
                onChange={(event) => {
                  setQuery(event.target.value);
                  setPage(1);
                }}
                placeholder={t.search}
                className="h-10 w-full rounded-lg border border-border bg-background ps-9 pe-3 text-sm outline-none transition-shadow focus:ring-2 focus:ring-brand-cyan/25"
              />
            </div>
            <StorviaSelect
              value={typeFilter}
              onValueChange={(value) => {
                setTypeFilter(value as "all" | "system" | "custom");
                setPage(1);
              }}
              options={[
                { value: "all", label: t.all },
                { value: "system", label: t.system },
                { value: "custom", label: t.custom },
              ]}
              ariaLabel={t.type}
              className="h-10 w-40 rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/25"
            />
          </div>
        </header>

        {(notice || error) && (
          <div
            className={cn(
              "rounded-xl border px-4 py-3 text-sm",
              error
                ? "border-destructive/30 bg-destructive/8 text-destructive"
                : "border-brand-cyan/25 bg-brand-cyan/8 text-foreground",
            )}
          >
            {error ?? notice}
          </div>
        )}

        <div className="grid min-h-[590px] gap-5 xl:grid-cols-[minmax(360px,0.8fr)_minmax(0,1.2fr)]">
          <section className="overflow-hidden rounded-xl border border-workspace-content-border bg-workspace-panel shadow-sm">
            <div className="flex items-center justify-between border-b border-border px-4 py-3.5">
              <div>
                <h2 className="text-sm font-bold">{t.title}</h2>
                <p className="mt-0.5 text-xs text-muted-foreground">
                  {meta.total} {meta.total === 1 ? t.role : t.roles}
                </p>
              </div>
              <div className="flex size-8 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                <KeenIcon name="people" className="text-[16px]" />
              </div>
            </div>

            <div className="min-h-[480px]">
              {loading ? (
                <div className="flex min-h-[440px] items-center justify-center gap-2 text-sm text-muted-foreground">
                  <KeenIcon name="loading" className="animate-spin text-[17px] text-brand-cyan" />
                  {t.loading}
                </div>
              ) : roles.length ? (
                <div className="divide-y divide-border/70">
                  {roles.map((role) => {
                    const active = selectedId === role.id;
                    return (
                      <button
                        key={role.id}
                        type="button"
                        onClick={() => void selectRole(role.id)}
                        className={cn(
                          "flex w-full items-start gap-3 px-4 py-3.5 text-start transition-colors",
                          active
                            ? "bg-brand-cyan/8"
                            : "hover:bg-accent/45",
                        )}
                      >
                        <span
                          className={cn(
                            "mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg ring-1",
                            role.isSystem
                              ? "bg-brand-blue/10 text-brand-blue ring-brand-blue/15"
                              : "bg-brand-cyan/10 text-brand-cyan ring-brand-cyan/15",
                          )}
                        >
                          <KeenIcon name={role.isSystem ? "shield-tick" : "people"} className="text-[17px]" />
                        </span>
                        <span className="min-w-0 flex-1">
                          <span className="flex flex-wrap items-center gap-2">
                            <span className="truncate text-sm font-semibold text-foreground">
                              {roleDisplayLabel(role, locale)}
                            </span>
                            {role.isSystem && (
                              <span className="rounded-full bg-brand-blue/10 px-2 py-0.5 text-[10px] font-semibold text-brand-blue">
                                {t.system}
                              </span>
                            )}
                          </span>
                          <span className="mt-1 block truncate font-mono text-[11px] text-muted-foreground" dir="ltr">
                            {role.name}
                          </span>
                          <span className="mt-2 flex flex-wrap gap-3 text-[11px] text-muted-foreground">
                            <span>{role.usersCount} {t.users}</span>
                            <span>{role.permissionsCount} {t.permissions}</span>
                          </span>
                        </span>
                      </button>
                    );
                  })}
                </div>
              ) : (
                <div className="flex min-h-[440px] items-center justify-center px-8 text-center">
                  <div className="max-w-sm">
                    <div className="mx-auto flex size-11 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                      <KeenIcon name="magnifier" className="text-[19px]" />
                    </div>
                    <p className="mt-3 text-sm font-semibold">{t.noRoles}</p>
                  </div>
                </div>
              )}
            </div>

            <div className="flex items-center justify-between border-t border-border px-4 py-3 text-xs text-muted-foreground">
              <span>
                {t.page} {meta.currentPage} {t.of} {meta.lastPage}
              </span>
              <div className="flex gap-2">
                <button
                  type="button"
                  disabled={page <= 1 || loading}
                  onClick={() => setPage((current) => Math.max(1, current - 1))}
                  className="rounded-md border border-border px-2.5 py-1.5 font-medium text-foreground disabled:opacity-40"
                >
                  {t.previous}
                </button>
                <button
                  type="button"
                  disabled={page >= meta.lastPage || loading}
                  onClick={() => setPage((current) => Math.min(meta.lastPage, current + 1))}
                  className="rounded-md border border-border px-2.5 py-1.5 font-medium text-foreground disabled:opacity-40"
                >
                  {t.next}
                </button>
              </div>
            </div>
          </section>

          <section className="overflow-hidden rounded-xl border border-workspace-content-border bg-workspace-panel shadow-sm">
            <div className="flex items-center justify-between border-b border-border px-5 py-4">
              <div>
                <h2 className="text-sm font-bold">{t.roleDetails}</h2>
                <p className="mt-0.5 text-xs text-muted-foreground">{t.selectRole}</p>
              </div>
              <div className="flex size-8 items-center justify-center rounded-lg bg-brand-blue/10 text-brand-blue">
                <KeenIcon name="shield-tick" className="text-[16px]" />
              </div>
            </div>

            {detailsLoading ? (
              <div className="flex min-h-[500px] items-center justify-center gap-2 text-sm text-muted-foreground">
                <KeenIcon name="loading" className="animate-spin text-[17px] text-brand-cyan" />
                {t.loadingDetails}
              </div>
            ) : selected ? (
              <RoleDetails
                role={selected}
                locale={locale}
                canManage={selectedCanBeManaged}
                canViewPermissions={canViewPermissions}
                onEdit={() => setEditorMode("edit")}
                onManagePermissions={() => setPermissionsOpen(true)}
              />
            ) : (
              <div className="flex min-h-[500px] items-center justify-center px-8 text-center text-sm text-muted-foreground">
                {t.selectRole}
              </div>
            )}
          </section>
        </div>
      </div>

      {editorMode && (
        <RoleEditor
          mode={editorMode}
          locale={locale}
          role={editorMode === "edit" ? selected : null}
          actorIsSuperAdmin={actorIsSuperAdmin}
          onClose={() => setEditorMode(null)}
          onSaved={(role) =>
            void handleRoleSaved(
              editorMode === "create" ? t.created : t.updated,
              role.id,
            )
          }
        />
      )}

      {permissionsOpen && selected && (
        <PermissionEditor
          locale={locale}
          role={selected}
          catalog={catalog}
          catalogLoading={catalogLoading}
          actorPermissions={actorPermissions}
          actorIsSuperAdmin={actorIsSuperAdmin}
          onClose={() => setPermissionsOpen(false)}
          onSaved={(role) => void handlePermissionsSaved(role)}
        />
      )}
    </main>
  );
}

function RoleDetails({
  role,
  locale,
  canManage,
  canViewPermissions,
  onEdit,
  onManagePermissions,
}: {
  role: AdministrationRole;
  locale: StorviaLocale;
  canManage: boolean;
  canViewPermissions: boolean;
  onEdit: () => void;
  onManagePermissions: () => void;
}) {
  const t = COPY[locale];
  const groups = groupPermissions(role.permissions);

  return (
    <div className="p-5">
      <div className="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-lg font-bold text-foreground">
              {roleDisplayLabel(role, locale)}
            </h3>
            <span
              className={cn(
                "rounded-full px-2.5 py-1 text-[11px] font-semibold",
                role.isSystem
                  ? "bg-brand-blue/10 text-brand-blue"
                  : "bg-brand-cyan/10 text-brand-cyan",
              )}
            >
              {role.isSystem ? t.system : t.custom}
            </span>
            {role.name === "super_admin" && (
              <span className="rounded-full bg-destructive/10 px-2.5 py-1 text-[11px] font-semibold text-destructive">
                {t.protectedSuperAdmin}
              </span>
            )}
          </div>
          <p className="mt-2 font-mono text-xs text-muted-foreground" dir="ltr">
            {role.name}
          </p>
        </div>

        {canManage && (
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              onClick={onEdit}
              className="h-9 rounded-lg border border-border bg-background px-3 text-xs font-semibold transition-colors hover:bg-accent"
            >
              {t.edit}
            </button>
            {canViewPermissions && (
              <button
                type="button"
                onClick={onManagePermissions}
                className="h-9 rounded-lg bg-brand-cyan px-3 text-xs font-semibold text-white transition-opacity hover:opacity-90"
              >
                {t.managePermissions}
              </button>
            )}
          </div>
        )}
      </div>

      {role.isSystem && (
        <div className="mt-4 rounded-lg border border-brand-blue/20 bg-brand-blue/6 px-4 py-3 text-xs leading-5 text-muted-foreground">
          {role.name === "super_admin" ? t.superAdminHint : t.systemHint}
        </div>
      )}

      <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Metric label={t.users} value={String(role.usersCount)} />
        <Metric label={t.permissions} value={String(role.permissionsCount)} />
        <Metric label={t.type} value={role.isSystem ? t.system : t.custom} />
        <Metric label={t.permissionGroups} value={String(groups.length)} />
      </div>

      <div className="mt-5 grid gap-4 lg:grid-cols-2">
        <InfoRow label={t.name} value={role.name} mono />
        <InfoRow label={t.label} value={role.label || "—"} />
        <InfoRow label={t.createdAt} value={formatDateTime(role.createdAt, locale)} />
        <InfoRow label={t.updatedAt} value={formatDateTime(role.updatedAt, locale)} />
        <InfoRow label={t.accessModel} value={t.accessModelValue} />
      </div>

      <div className="mt-6">
        <div className="flex items-center justify-between gap-3">
          <div>
            <h4 className="text-sm font-bold">{t.permissions}</h4>
            <p className="mt-0.5 text-xs text-muted-foreground">
              {role.permissionsCount} {t.permissions}
            </p>
          </div>
        </div>

        {groups.length ? (
          <div className="mt-3 space-y-3">
            {groups.map((group) => (
              <div key={group.domain} className="rounded-lg border border-border/80 bg-background/45 p-3.5">
                <div className="mb-3 flex items-center gap-2">
                  <span className="size-1.5 rounded-full bg-brand-cyan" />
                  <span className="text-xs font-bold">
                    {DOMAIN_LABELS[locale][group.domain]}
                  </span>
                  <span className="text-[11px] text-muted-foreground">
                    {group.permissions.length}
                  </span>
                </div>
                <div className="flex flex-wrap gap-2">
                  {group.permissions.map((permission) => (
                    <span
                      key={permission.name}
                      className="rounded-md border border-border bg-card px-2.5 py-1.5 font-mono text-[11px] text-foreground/80"
                      dir="ltr"
                    >
                      {permission.name}
                    </span>
                  ))}
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="mt-3 rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
            {t.noPermissions}
          </div>
        )}
      </div>
    </div>
  );
}

function RoleEditor({
  mode,
  locale,
  role,
  actorIsSuperAdmin,
  onClose,
  onSaved,
}: {
  mode: Exclude<EditorMode, null>;
  locale: StorviaLocale;
  role: AdministrationRole | null;
  actorIsSuperAdmin: boolean;
  onClose: () => void;
  onSaved: (role: AdministrationRole) => void;
}) {
  const t = COPY[locale];
  const [name, setName] = React.useState(role?.name ?? "");
  const [label, setLabel] = React.useState(role?.label ?? "");
  const [saving, setSaving] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const isSystem = role?.isSystem ?? false;
  const nameLocked = mode === "edit" && isSystem;

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSaving(true);
    setError(null);

    try {
      const payload = { name: name.trim(), label: label.trim() };
      const saved =
        mode === "create"
          ? await createAdministrationRole(payload)
          : await updateAdministrationRole(role!.id, payload);
      onSaved(saved);
    } catch (requestError) {
      setError(readableError(requestError, t.unknownError));
    } finally {
      setSaving(false);
    }
  }

  if (mode === "edit" && isSystem && !actorIsSuperAdmin) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-[90]">
      <button
        type="button"
        aria-label={t.close}
        className="absolute inset-0 bg-black/45 backdrop-blur-[1px]"
        onClick={onClose}
      />
      <aside className="absolute inset-y-0 end-0 flex w-[min(94vw,520px)] flex-col border-s border-border bg-background shadow-2xl">
        <div className="flex items-center justify-between border-b border-border px-5 py-4">
          <div>
            <h2 className="text-base font-bold">
              {mode === "create" ? t.createTitle : t.editTitle}
            </h2>
            <p className="mt-1 text-xs text-muted-foreground">
              {mode === "edit" && isSystem ? t.systemHint : t.roleKeyHint}
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-accent"
            aria-label={t.close}
          >
            <KeenIcon name="cross" className="text-[16px]" />
          </button>
        </div>

        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
          <div className="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
            {error && (
              <div className="rounded-lg border border-destructive/30 bg-destructive/8 px-3 py-2.5 text-sm text-destructive">
                {error}
              </div>
            )}

            <Field label={t.name}>
              <input
                value={name}
                onChange={(event) => setName(event.target.value)}
                required
                minLength={2}
                maxLength={64}
                pattern="[a-z][a-z0-9_]*"
                disabled={nameLocked}
                dir="ltr"
                className="h-10 rounded-lg border border-border bg-background px-3 font-mono text-sm outline-none focus:ring-2 focus:ring-brand-cyan/25 disabled:bg-muted disabled:text-muted-foreground"
              />
            </Field>
            <p className="-mt-3 text-xs text-muted-foreground">{t.roleKeyHint}</p>

            <Field label={t.label}>
              <input
                value={label}
                onChange={(event) => setLabel(event.target.value)}
                required
                maxLength={100}
                className="h-10 rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/25"
              />
            </Field>

            {isSystem && (
              <div className="rounded-lg border border-brand-blue/20 bg-brand-blue/6 px-4 py-3 text-xs leading-5 text-muted-foreground">
                {t.systemHint}
              </div>
            )}
          </div>

          <footer className="flex justify-end gap-2 border-t border-border bg-background/95 px-5 py-4 backdrop-blur">
            <button
              type="button"
              onClick={onClose}
              disabled={saving}
              className="h-10 rounded-lg border border-border px-4 text-sm font-semibold hover:bg-accent disabled:opacity-50"
            >
              {t.cancel}
            </button>
            <button
              type="submit"
              disabled={saving || !name.trim() || !label.trim()}
              className="h-10 rounded-lg bg-brand-cyan px-5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
            >
              {saving ? t.saving : mode === "create" ? t.create : t.save}
            </button>
          </footer>
        </form>
      </aside>
    </div>
  );
}

function PermissionEditor({
  locale,
  role,
  catalog,
  catalogLoading,
  actorPermissions,
  actorIsSuperAdmin,
  onClose,
  onSaved,
}: {
  locale: StorviaLocale;
  role: AdministrationRole;
  catalog: AdministrationPermission[];
  catalogLoading: boolean;
  actorPermissions: Set<string>;
  actorIsSuperAdmin: boolean;
  onClose: () => void;
  onSaved: (role: AdministrationRole) => void;
}) {
  const t = COPY[locale];
  const [selectedNames, setSelectedNames] = React.useState<string[]>(
    role.permissions.map((permission) => permission.name),
  );
  const [saving, setSaving] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const groups = groupPermissions(catalog);
  const hasBeyondAuthority =
    !actorIsSuperAdmin &&
    role.permissions.some((permission) => !actorPermissions.has(permission.name));

  function canGrant(permissionName: string): boolean {
    return actorIsSuperAdmin || actorPermissions.has(permissionName);
  }

  function toggle(permissionName: string) {
    if (hasBeyondAuthority || !canGrant(permissionName)) return;

    setSelectedNames((current) =>
      current.includes(permissionName)
        ? current.filter((name) => name !== permissionName)
        : [...current, permissionName],
    );
  }

  async function save() {
    if (hasBeyondAuthority) return;
    setSaving(true);
    setError(null);

    try {
      onSaved(
        await syncAdministrationRolePermissions(
          role.id,
          [...selectedNames].sort((left, right) => left.localeCompare(right)),
        ),
      );
    } catch (requestError) {
      setError(readableError(requestError, t.unknownError));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-[95]">
      <button
        type="button"
        aria-label={t.close}
        className="absolute inset-0 bg-black/50 backdrop-blur-[1px]"
        onClick={onClose}
      />
      <aside className="absolute inset-y-0 end-0 flex w-[min(96vw,680px)] flex-col border-s border-border bg-background shadow-2xl">
        <div className="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
          <div className="min-w-0">
            <h2 className="text-base font-bold">{t.permissionsTitle}</h2>
            <p className="mt-1 text-xs text-muted-foreground">{t.permissionsSubtitle}</p>
            <div className="mt-2 flex flex-wrap items-center gap-2">
              <span className="text-sm font-semibold">{roleDisplayLabel(role, locale)}</span>
              <span className="font-mono text-[11px] text-muted-foreground" dir="ltr">
                {role.name}
              </span>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-accent"
            aria-label={t.close}
          >
            <KeenIcon name="cross" className="text-[16px]" />
          </button>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto p-5">
          {error && (
            <div className="mb-4 rounded-lg border border-destructive/30 bg-destructive/8 px-3 py-2.5 text-sm text-destructive">
              {error}
            </div>
          )}

          {hasBeyondAuthority ? (
            <div className="mb-4 rounded-lg border border-destructive/25 bg-destructive/7 px-4 py-3 text-sm leading-6 text-destructive">
              {t.authorityBlocked}
            </div>
          ) : !actorIsSuperAdmin ? (
            <div className="mb-4 rounded-lg border border-brand-blue/20 bg-brand-blue/6 px-4 py-3 text-xs leading-5 text-muted-foreground">
              {t.permissionLimit}
            </div>
          ) : null}

          <div className="mb-4 flex items-center justify-between rounded-lg border border-border bg-card px-4 py-3">
            <span className="text-sm font-semibold">{t.permissions}</span>
            <span className="rounded-full bg-brand-cyan/10 px-2.5 py-1 text-xs font-semibold text-brand-cyan">
              {selectedNames.length} {t.selected}
            </span>
          </div>

          {catalogLoading ? (
            <div className="flex min-h-[320px] items-center justify-center gap-2 text-sm text-muted-foreground">
              <KeenIcon name="loading" className="animate-spin text-[17px] text-brand-cyan" />
              {t.loading}
            </div>
          ) : groups.length ? (
            <div className="space-y-4">
              {groups.map((group) => (
                <section key={group.domain} className="overflow-hidden rounded-xl border border-border">
                  <div className="flex items-center justify-between border-b border-border bg-muted/35 px-4 py-3">
                    <h3 className="text-xs font-bold">{DOMAIN_LABELS[locale][group.domain]}</h3>
                    <span className="text-[11px] text-muted-foreground">{group.permissions.length}</span>
                  </div>
                  <div className="divide-y divide-border/65">
                    {group.permissions.map((permission) => {
                      const checked = selectedNames.includes(permission.name);
                      const grantable = canGrant(permission.name);
                      const disabled = hasBeyondAuthority || !grantable || saving;

                      return (
                        <label
                          key={permission.name}
                          className={cn(
                            "flex items-start gap-3 px-4 py-3.5",
                            disabled ? "cursor-default opacity-65" : "cursor-pointer hover:bg-accent/35",
                          )}
                        >
                          <input
                            type="checkbox"
                            checked={checked}
                            disabled={disabled}
                            onChange={() => toggle(permission.name)}
                            className="mt-0.5"
                          />
                          <span className="min-w-0 flex-1">
                            <span className="block font-mono text-xs font-semibold text-foreground" dir="ltr">
                              {permission.name}
                            </span>
                            {permission.description && (
                              <span className="mt-1 block text-xs leading-5 text-muted-foreground">
                                {permission.description}
                              </span>
                            )}
                          </span>
                        </label>
                      );
                    })}
                  </div>
                </section>
              ))}
            </div>
          ) : (
            <div className="rounded-lg border border-dashed border-border px-4 py-10 text-center text-sm text-muted-foreground">
              {t.noCatalogPermissions}
            </div>
          )}
        </div>

        <footer className="flex justify-end gap-2 border-t border-border bg-background/95 px-5 py-4 backdrop-blur">
          <button
            type="button"
            onClick={onClose}
            disabled={saving}
            className="h-10 rounded-lg border border-border px-4 text-sm font-semibold hover:bg-accent disabled:opacity-50"
          >
            {t.cancel}
          </button>
          <button
            type="button"
            onClick={() => void save()}
            disabled={saving || catalogLoading || hasBeyondAuthority}
            className="h-10 rounded-lg bg-brand-cyan px-5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
          >
            {saving ? t.saving : t.save}
          </button>
        </footer>
      </aside>
    </div>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg border border-border bg-background/50 px-3.5 py-3">
      <p className="text-[11px] font-medium text-muted-foreground">{label}</p>
      <p className="mt-1 text-base font-bold text-foreground">{value}</p>
    </div>
  );
}

function InfoRow({
  label,
  value,
  mono = false,
}: {
  label: string;
  value: string;
  mono?: boolean;
}) {
  return (
    <div className="rounded-lg border border-border/80 bg-background/40 px-3.5 py-3">
      <p className="text-[11px] font-medium text-muted-foreground">{label}</p>
      <p
        className={cn("mt-1.5 break-words text-sm font-semibold", mono && "font-mono text-xs")}
        dir={mono ? "ltr" : undefined}
      >
        {value}
      </p>
    </div>
  );
}

function Field({
  label,
  children,
}: {
  label: string;
  children: React.ReactNode;
}) {
  return (
    <fieldset className="grid min-w-0 gap-1.5">
      <legend className="text-xs font-semibold text-muted-foreground">{label}</legend>
      {children}
    </fieldset>
  );
}
