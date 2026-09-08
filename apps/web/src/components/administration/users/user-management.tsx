"use client";

import * as React from "react";
import { UserFileTypePolicyEditor } from "@/components/administration/users/user-file-type-policy-editor";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { KeenIcon } from "@/components/ui/keen-icon";
import { StorviaSelect } from "@/components/ui/storvia-select";
import { ApiError } from "@/lib/api/api-error";
import {
  fetchAdministrationRoleCatalog,
  type AdministrationRole,
} from "@/lib/api/administration-roles-client";
import {
  createAdministrationUser,
  fetchAdministrationUser,
  fetchDepartmentTree,
  listAdministrationUsers,
  resetAdministrationUserPassword,
  syncAdministrationUserDepartments,
  syncAdministrationUserRoles,
  updateAdministrationUser,
  updateAdministrationUserStatus,
  type AdministrationDepartment,
  type AdministrationUser,
  type AdministrationUsersMeta,
} from "@/lib/api/administration-users-client";
import { useAuth } from "@/lib/auth";
import { formatDateTime, formatNumber, type StorviaLocale } from "@/lib/i18n";
import { cn } from "@/lib/utils";

type Props = {
  locale: StorviaLocale;
};

type EditorMode = "create" | "edit" | null;



const EMPTY_META: AdministrationUsersMeta = {
  currentPage: 1,
  lastPage: 1,
  perPage: 25,
  total: 0,
};

const COPY = {
  en: {
    title: "Users",
    subtitle: "Manage user identities, access, roles, departments, and account status.",
    search: "Search name, username, or email...",
    all: "All",
    active: "Active",
    disabled: "Disabled",
    allRoles: "All roles",
    allAdministrations: "All administrations",
    administrationItself: "Administration itself",
    allDepartments: "All departments",
    chooseDepartment: "Choose a department",
    addUser: "Add user",
    name: "Name",
    username: "Username",
    email: "Email",
    status: "Status",
    roles: "Roles",
    departments: "Departments",
    administration: "Administration",
    chooseAdministration: "Choose an administration",
    childDepartments: "Departments",
    noChildDepartments: "This administration has no direct departments.",
    personalSpace: "Personal space",
    personalSpaceEnabled: "Enabled",
    personalSpaceDisabled: "Disabled",
    personalSpaceDescription:
      "This is a user-specific workspace entitlement, independent from roles and department membership.",
    userSpecificSettings: "User-specific settings",
    userFileTypesAfterCreate:
      "Create the user first, then reopen the user to configure file type restrictions.",
    lastLogin: "Last login",
    actions: "Actions",
    resetPassword: "Reset password",
    resetPasswordTitle: "Reset password",
    resetPasswordDescription:
      "All active sessions and access tokens for this user will be revoked.",
    resetPasswordUser: "User",
    resetPasswordDone: "Password reset successfully.",
    edit: "Edit",
    disable: "Disable",
    enable: "Enable",
    noUsers: "No users match the current filters.",
    loading: "Loading users...",
    refresh: "Refresh",
    createTitle: "Create user",
    editTitle: "Edit user",
    password: "Password",
    confirmPassword: "Confirm password",
    locale: "Language",
    english: "English",
    arabic: "Arabic",
    save: "Save changes",
    cancel: "Cancel",
    saving: "Saving...",
    close: "Close",
    created: "User created successfully.",
    updated: "User updated successfully.",
    statusUpdated: "User status updated.",
    unknownError: "The request could not be completed.",
    previous: "Previous",
    next: "Next",
    page: "Page",
    of: "of",
    records: "users",
    selected: "selected",
    noDepartment: "No department",
    noRoleAccess: "You do not have permission to assign roles.",
    noRoleOptions: "No roles are available from the role catalog.",
    roleCatalogLoadFailed: "Failed to load the role catalog.",
    departmentTreeLoadFailed: "Failed to load departments.",
    noDepartmentAccess: "You do not have permission to manage department membership.",
    legacyRoleConflict:
      "This legacy account has multiple or missing roles. Select exactly one role.",
    legacyDepartmentConflict:
      "This legacy membership does not match one administration and its direct departments. Choose a valid administration.",
    protectedUser: "Protected Super Admin",
    scopedView: "Department-scoped view",
    passwordHint: "At least 12 characters with upper/lowercase, number, and symbol.",
  },
  ar: {
    title: "المستخدمون",
    subtitle: "إدارة هوية المستخدمين والوصول والأدوار والإدارات وحالة الحساب.",
    search: "بحث بالاسم أو اسم المستخدم أو البريد...",
    all: "الكل",
    active: "نشط",
    disabled: "معطل",
    allRoles: "كل الأدوار",
    allAdministrations: "كل الإدارات",
    administrationItself: "الإدارة نفسها",
    allDepartments: "كل الأقسام",
    chooseDepartment: "اختر القسم",
    addUser: "إضافة مستخدم",
    name: "الاسم",
    username: "اسم المستخدم",
    email: "البريد الإلكتروني",
    status: "الحالة",
    roles: "الأدوار",
    departments: "الإدارات",
    administration: "الإدارة",
    chooseAdministration: "اختر الإدارة",
    childDepartments: "الأقسام",
    noChildDepartments: "لا توجد أقسام مباشرة تابعة لهذه الإدارة.",
    personalSpace: "المساحة الشخصية",
    personalSpaceEnabled: "مفعّلة",
    personalSpaceDisabled: "معطّلة",
    personalSpaceDescription:
      "هذا استحقاق خاص بالمستخدم ومستقل عن الدور وعضوية الإدارة أو القسم.",
    userSpecificSettings: "إعدادات خاصة بالمستخدم",
    userFileTypesAfterCreate:
      "أنشئ المستخدم أولًا ثم أعد فتحه لضبط قيود أنواع الملفات الخاصة به.",
    lastLogin: "آخر دخول",
    actions: "الإجراءات",
    resetPassword: "إعادة تهيئة كلمة المرور",
    resetPasswordTitle: "إعادة تهيئة كلمة المرور",
    resetPasswordDescription:
      "سيتم إبطال جميع جلسات ورموز وصول هذا المستخدم.",
    resetPasswordUser: "المستخدم",
    resetPasswordDone: "تمت إعادة تهيئة كلمة المرور بنجاح.",
    edit: "تعديل",
    disable: "تعطيل",
    enable: "تفعيل",
    noUsers: "لا يوجد مستخدمون مطابقون للفلاتر الحالية.",
    loading: "جاري تحميل المستخدمين...",
    refresh: "تحديث",
    createTitle: "إضافة مستخدم",
    editTitle: "تعديل المستخدم",
    password: "كلمة المرور",
    confirmPassword: "تأكيد كلمة المرور",
    locale: "اللغة",
    english: "English",
    arabic: "العربية",
    save: "حفظ التغييرات",
    cancel: "إلغاء",
    saving: "جاري الحفظ...",
    close: "إغلاق",
    created: "تم إنشاء المستخدم بنجاح.",
    updated: "تم تحديث المستخدم بنجاح.",
    statusUpdated: "تم تحديث حالة المستخدم.",
    unknownError: "تعذر إكمال الطلب.",
    previous: "السابق",
    next: "التالي",
    page: "صفحة",
    of: "من",
    records: "مستخدم",
    selected: "محدد",
    noDepartment: "بدون إدارة",
    noRoleAccess: "لا تملك صلاحية إسناد الأدوار.",
    noRoleOptions: "لا توجد أدوار متاحة من كتالوج الأدوار.",
    roleCatalogLoadFailed: "تعذر تحميل كتالوج الأدوار.",
    departmentTreeLoadFailed: "تعذر تحميل الإدارات والأقسام.",
    noDepartmentAccess: "لا تملك صلاحية إدارة عضوية الإدارات.",
    legacyRoleConflict:
      "هذا حساب قديم بأدوار متعددة أو بدون دور. اختر دورًا واحدًا بالضبط.",
    legacyDepartmentConflict:
      "العضوية القديمة لا تطابق إدارة واحدة وأقسامها المباشرة. اختر إدارة صحيحة.",
    protectedUser: "مدير نظام محمي",
    scopedView: "عرض مقيّد بإدارتك",
    passwordHint: "12 حرفًا على الأقل مع أحرف كبيرة وصغيرة ورقم ورمز.",
  },
} as const;

function flattenDepartments(
  nodes: AdministrationDepartment[],
  depth = 0,
): Array<AdministrationDepartment & { depth: number }> {
  return nodes.flatMap((node) => [
    { ...node, depth },
    ...flattenDepartments(node.children ?? [], depth + 1),
  ]);
}

function readableError(error: unknown, fallback: string): string {
  if (error instanceof ApiError) return error.message;
  if (error instanceof Error) return error.message;
  return fallback;
}

function roleLabel(
  roleName: string,
  locale: StorviaLocale,
  roleOptions: AdministrationRole[],
): string {
  if (locale === "ar") {
    if (roleName === "super_admin") return "مدير النظام";
    if (roleName === "admin") return "مدير";
    if (roleName === "member") return "عضو";
  }

  return roleOptions.find((role) => role.name === roleName)?.label ?? roleName;
}

function isSuperAdmin(user: AdministrationUser): boolean {
  return user.roles.includes("super_admin");
}

function initialOrganizationSelection(
  user: AdministrationUser | null,
  administrations: AdministrationDepartment[],
): {
  administrationId: string;
  departmentIds: string[];
  legacyConflict: boolean;
} {
  const membershipIds = new Set(
    (user?.departments ?? []).map((department) => String(department.id)),
  );
  if (membershipIds.size === 0) {
    return { administrationId: "", departmentIds: [], legacyConflict: false };
  }

  const selectedRoots = administrations.filter((administration) =>
    membershipIds.has(String(administration.id)),
  );
  if (selectedRoots.length !== 1) {
    return { administrationId: "", departmentIds: [], legacyConflict: true };
  }

  const root = selectedRoots[0];
  const childIds = new Set((root.children ?? []).map((child) => String(child.id)));
  const selectedChildren = [...membershipIds].filter(
    (id) => id !== String(root.id),
  );
  const invalid = selectedChildren.some((id) => !childIds.has(id));

  return {
    administrationId: invalid ? "" : String(root.id),
    departmentIds: invalid ? [] : selectedChildren,
    legacyConflict: invalid,
  };
}

export function UserManagement({ locale }: Props) {
  const t = COPY[locale];
  const { user: actor } = useAuth();

  const actorPermissions = React.useMemo(
    () => new Set(actor?.permissions ?? []),
    [actor?.permissions],
  );
  const actorIsSuperAdmin = actor?.roles.includes("super_admin") ?? false;

  const canViewAllUsers = actorIsSuperAdmin || actorPermissions.has("users.view_all");
  const canCreate = actorIsSuperAdmin || actorPermissions.has("users.create");
  const canUpdate = actorIsSuperAdmin || actorPermissions.has("users.update");
  const canDisable = actorIsSuperAdmin || actorPermissions.has("users.disable");
  const canAssignRoles =
    actorIsSuperAdmin || actorPermissions.has("users.assign_roles");
  const canManageDepartments =
    actorIsSuperAdmin || actorPermissions.has("departments.manage_members");
  const canViewRoles = actorIsSuperAdmin || actorPermissions.has("roles.view");
  const canManageUserFileTypes =
    actorIsSuperAdmin || actorPermissions.has("system.manage");

  const [users, setUsers] = React.useState<AdministrationUser[]>([]);
  const [roleOptions, setRoleOptions] = React.useState<AdministrationRole[]>([]);
  const [departments, setDepartments] = React.useState<AdministrationDepartment[]>([]);
  const [roleCatalogError, setRoleCatalogError] = React.useState(false);
  const [departmentTreeError, setDepartmentTreeError] = React.useState(false);
  const [meta, setMeta] = React.useState<AdministrationUsersMeta>(EMPTY_META);
  const [loading, setLoading] = React.useState(true);
  const [query, setQuery] = React.useState("");
  const [statusFilter, setStatusFilter] = React.useState<"all" | "active" | "disabled">("all");
  const [roleFilter, setRoleFilter] = React.useState("");
  const [administrationFilter, setAdministrationFilter] = React.useState("");
  const [departmentFilter, setDepartmentFilter] = React.useState("");
  const [page, setPage] = React.useState(1);
  const [error, setError] = React.useState<string | null>(null);
  const [notice, setNotice] = React.useState<string | null>(null);
  const [editorMode, setEditorMode] = React.useState<EditorMode>(null);
  const [selectedUser, setSelectedUser] = React.useState<AdministrationUser | null>(null);
  const [resettingUser, setResettingUser] =
    React.useState<AdministrationUser | null>(null);

  const selectedFilterAdministration = React.useMemo(
    () =>
      departments.find(
        (administration) => String(administration.id) === administrationFilter,
      ) ?? null,
    [administrationFilter, departments],
  );
  const filterDepartments = React.useMemo(
    () =>
      selectedFilterAdministration
        ? flattenDepartments(selectedFilterAdministration.children ?? [])
        : [],
    [selectedFilterAdministration],
  );

  const loadUsers = React.useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const result = await listAdministrationUsers({
        search: query,
        status: statusFilter,
        role: roleFilter || undefined,
        department: departmentFilter || administrationFilter || undefined,
        page,
        perPage: 25,
      });

      setUsers(result.users);
      setMeta(result.meta);
    } catch (requestError) {
      setError(readableError(requestError, t.unknownError));
    } finally {
      setLoading(false);
    }
  }, [
    administrationFilter,
    departmentFilter,
    page,
    query,
    roleFilter,
    statusFilter,
    t.unknownError,
  ]);

  const loadDepartmentTree = React.useCallback(async () => {
    try {
      const nextDepartments = await fetchDepartmentTree();
      setDepartments(nextDepartments);
      setDepartmentTreeError(false);
    } catch {
      setDepartments([]);
      setDepartmentTreeError(true);
    }
  }, []);

  const loadRoleCatalog = React.useCallback(async () => {
    if (!canViewRoles) {
      setRoleOptions([]);
      setRoleCatalogError(false);
      return;
    }

    try {
      const nextRoles = await fetchAdministrationRoleCatalog({
        includePermissions: canAssignRoles,
      });
      setRoleOptions(nextRoles);
      setRoleCatalogError(false);
    } catch {
      setRoleOptions([]);
      setRoleCatalogError(true);
    }
  }, [canAssignRoles, canViewRoles]);

  React.useEffect(() => {
    const timeout = window.setTimeout(() => {
      void loadDepartmentTree();
      void loadRoleCatalog();
    }, 0);

    return () => window.clearTimeout(timeout);
  }, [loadDepartmentTree, loadRoleCatalog]);

  React.useEffect(() => {
    const timeout = window.setTimeout(() => {
      void loadUsers();
    }, 220);

    return () => window.clearTimeout(timeout);
  }, [loadUsers]);

  async function openEdit(user: AdministrationUser) {
    setError(null);

    try {
      setSelectedUser(await fetchAdministrationUser(user.id));
    } catch {
      setSelectedUser(user);
    }

    setEditorMode("edit");
  }

  async function toggleStatus(user: AdministrationUser) {
    setError(null);
    setNotice(null);

    try {
      await updateAdministrationUserStatus(user.id, user.status !== "active");
      setNotice(t.statusUpdated);
      await loadUsers();
    } catch (requestError) {
      setError(readableError(requestError, t.unknownError));
    }
  }

  const auxiliaryLoadErrors = [
    departmentTreeError ? t.departmentTreeLoadFailed : null,
    roleCatalogError ? t.roleCatalogLoadFailed : null,
  ].filter((message) => message !== null);

  function refresh() {
    void loadUsers();
    void loadDepartmentTree();
    void loadRoleCatalog();
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
                    {formatNumber(meta.total, locale)} {t.records}
                  </span>
                  {!canViewAllUsers && (
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
                onClick={refresh}
                className="inline-flex h-10 items-center gap-2 rounded-lg border border-border bg-background px-3 text-sm font-medium hover:bg-accent"
              >
                <KeenIcon name="arrows-circle" className="text-[17px]" />
                {t.refresh}
              </button>

              {canCreate && (
                <button
                  type="button"
                  onClick={() => {
                    setSelectedUser(null);
                    setEditorMode("create");
                  }}
                  className="inline-flex h-10 items-center gap-2 rounded-lg bg-brand-cyan px-4 text-sm font-semibold text-white shadow-sm hover:opacity-90"
                >
                  <KeenIcon name="plus" className="text-[17px]" />
                  {t.addUser}
                </button>
              )}
            </div>
          </div>
        </header>

        {(error || auxiliaryLoadErrors.length > 0 || notice) && (
          <div
            className={cn(
              "rounded-lg border px-4 py-3 text-sm",
              error || auxiliaryLoadErrors.length > 0
                ? "border-destructive/25 bg-destructive/5 text-destructive"
                : "border-emerald-500/25 bg-emerald-500/5 text-emerald-700 dark:text-emerald-300",
            )}
          >
            {error ? (
              error
            ) : auxiliaryLoadErrors.length > 0 ? (
              <div className="space-y-1">
                {auxiliaryLoadErrors.map((message) => (
                  <p key={message}>{message}</p>
                ))}
              </div>
            ) : (
              notice
            )}
          </div>
        )}

        <section className="overflow-hidden rounded-xl border border-workspace-content-border bg-workspace-panel shadow-sm">
          <div className="grid gap-3 border-b border-border p-4 lg:grid-cols-[minmax(260px,1fr)_auto_auto_auto_auto]">
            <label className="relative block">
              <KeenIcon
                name="magnifier"
                className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-[17px] text-muted-foreground"
              />
              <input
                value={query}
                onChange={(event) => {
                  setQuery(event.target.value);
                  setPage(1);
                }}
                placeholder={t.search}
                className="h-10 w-full rounded-lg border border-border bg-background ps-10 pe-3 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/30"
              />
            </label>

            <div className="flex items-center gap-1 rounded-lg border border-border bg-background p-1">
              {(["all", "active", "disabled"] as const).map((value) => (
                <button
                  key={value}
                  type="button"
                  onClick={() => {
                    setStatusFilter(value);
                    setPage(1);
                  }}
                  className={cn(
                    "rounded-md px-3 py-1.5 text-xs font-semibold transition-colors",
                    statusFilter === value
                      ? "bg-foreground text-background"
                      : "text-muted-foreground hover:bg-accent hover:text-foreground",
                  )}
                >
                  {t[value]}
                </button>
              ))}
            </div>

            <StorviaSelect
              value={roleFilter}
              onValueChange={(value) => {
                setRoleFilter(value);
                setPage(1);
              }}
              options={[
                { value: "", label: t.allRoles },
                ...roleOptions.map((role) => ({
                  value: role.name,
                  label: roleLabel(role.name, locale, roleOptions),
                  keywords: role.name,
                })),
              ]}
              placeholder={t.allRoles}
              searchable
              searchPlaceholder={t.search}
              ariaLabel={t.roles}
              disabled={roleCatalogError}
              className="h-10 min-w-40 rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/30"
            />

            <StorviaSelect
              value={administrationFilter}
              onValueChange={(value) => {
                setAdministrationFilter(value);
                setDepartmentFilter(value);
                setPage(1);
              }}
              options={[
                { value: "", label: t.allAdministrations },
                ...departments.map((administration) => ({
                  value: String(administration.id),
                  label: administration.name,
                })),
              ]}
              placeholder={t.allAdministrations}
              searchable
              searchPlaceholder={t.search}
              ariaLabel={t.administration}
              disabled={departmentTreeError}
              className="h-10 min-w-44 rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/30"
            />

            <StorviaSelect
              value={departmentFilter}
              onValueChange={(value) => {
                setDepartmentFilter(value);
                setPage(1);
              }}
              options={
                administrationFilter
                  ? [
                      {
                        value: administrationFilter,
                        label: t.administrationItself,
                      },
                      ...filterDepartments.map((department) => ({
                        value: String(department.id),
                        label: `${"— ".repeat(department.depth)}${department.name}`,
                      })),
                    ]
                  : []
              }
              placeholder={
                administrationFilter
                  ? t.administrationItself
                  : t.chooseAdministration
              }
              searchable
              searchPlaceholder={t.search}
              ariaLabel={t.departments}
              disabled={!administrationFilter || departmentTreeError}
              className="h-10 min-w-44 rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/30"
            />
          </div>

          <div className="overflow-x-auto">
            <table className="w-full min-w-[1040px] text-start text-sm">
              <thead className="bg-muted/45 text-xs uppercase tracking-wide text-muted-foreground">
                <tr>
                  <th className="px-4 py-3 text-start font-semibold">{t.name}</th>
                  <th className="px-4 py-3 text-start font-semibold">{t.email}</th>
                  <th className="px-4 py-3 text-start font-semibold">{t.status}</th>
                  <th className="px-4 py-3 text-start font-semibold">{t.roles}</th>
                  <th className="px-4 py-3 text-start font-semibold">{t.departments}</th>
                  <th className="px-4 py-3 text-start font-semibold">{t.lastLogin}</th>
                  <th className="px-4 py-3 text-end font-semibold">{t.actions}</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-border">
                {loading ? (
                  <tr>
                    <td colSpan={7} className="px-4 py-12 text-center text-muted-foreground">
                      {t.loading}
                    </td>
                  </tr>
                ) : users.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-4 py-12 text-center text-muted-foreground">
                      {t.noUsers}
                    </td>
                  </tr>
                ) : (
                  users.map((listedUser) => {
                    const protectedTarget =
                      isSuperAdmin(listedUser) && !actorIsSuperAdmin;
                    const showEdit =
                      (canUpdate && !protectedTarget) ||
                      canAssignRoles ||
                      canManageDepartments ||
                      canManageUserFileTypes;
                    const showStatus = canDisable && !protectedTarget;

                    return (
                      <tr key={listedUser.id} className="hover:bg-muted/20">
                        <td className="px-4 py-4">
                          <div className="flex items-center gap-2">
                            <div>
                              <div className="font-semibold text-foreground">
                                {listedUser.name}
                              </div>
                              <div className="mt-0.5 text-xs text-muted-foreground">
                                {listedUser.username ? `@${listedUser.username}` : "—"}
                              </div>
                            </div>
                            {protectedTarget && (
                              <span
                                title={t.protectedUser}
                                className="flex size-6 items-center justify-center rounded-full bg-amber-500/10 text-amber-700 dark:text-amber-300"
                              >
                                <KeenIcon name="shield-tick" className="text-[14px]" />
                              </span>
                            )}
                          </div>
                        </td>

                        <td className="px-4 py-4 text-muted-foreground">
                          {listedUser.email}
                        </td>

                        <td className="px-4 py-4">
                          <span
                            className={cn(
                              "inline-flex rounded-full px-2.5 py-1 text-xs font-semibold",
                              listedUser.status === "active"
                                ? "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                : "bg-muted text-muted-foreground",
                            )}
                          >
                            {listedUser.status === "active" ? t.active : t.disabled}
                          </span>
                        </td>

                        <td className="px-4 py-4">
                          <div className="flex max-w-[250px] flex-wrap gap-1">
                            {listedUser.roles.length ? (
                              listedUser.roles.map((role) => (
                                <span
                                  key={role}
                                  className="rounded-md bg-brand-blue/8 px-2 py-1 text-xs text-brand-blue"
                                >
                                  {roleLabel(role, locale, roleOptions)}
                                </span>
                              ))
                            ) : (
                              <span className="text-muted-foreground">—</span>
                            )}
                          </div>
                        </td>

                        <td className="px-4 py-4 text-muted-foreground">
                          {listedUser.departments.length
                            ? listedUser.departments
                                .map((department) => department.name)
                                .join(", ")
                            : t.noDepartment}
                        </td>

                        <td className="px-4 py-4 text-muted-foreground">
                          {formatDateTime(listedUser.last_login_at, locale)}
                        </td>

                        <td className="px-4 py-4">
                          <div className="flex justify-end gap-2">
                            {showEdit && (
                              <button
                                type="button"
                                onClick={() => void openEdit(listedUser)}
                                className="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-accent"
                              >
                                {t.edit}
                              </button>
                            )}

                            {canUpdate && !protectedTarget && (
                              <button
                                type="button"
                                title={t.resetPassword}
                                aria-label={t.resetPassword}
                                onClick={() => setResettingUser(listedUser)}
                                className="flex size-9 items-center justify-center rounded-lg border border-border hover:bg-accent"
                              >
                                <KeenIcon name="key" className="text-[16px]" />
                              </button>
                            )}

                            {showStatus && (
                              <button
                                type="button"
                                onClick={() => void toggleStatus(listedUser)}
                                className="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-accent"
                              >
                                {listedUser.status === "active"
                                  ? t.disable
                                  : t.enable}
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>

          <div className="flex flex-col gap-3 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="text-xs text-muted-foreground">
              {meta.total} {t.records} · {t.page} {meta.currentPage} {t.of}{" "}
              {meta.lastPage}
            </div>

            <div className="flex items-center gap-2">
              <button
                type="button"
                disabled={loading || meta.currentPage <= 1}
                onClick={() => setPage((current) => Math.max(1, current - 1))}
                className="h-9 rounded-lg border border-border px-3 text-xs font-semibold hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40"
              >
                {t.previous}
              </button>
              <button
                type="button"
                disabled={loading || meta.currentPage >= meta.lastPage}
                onClick={() =>
                  setPage((current) => Math.min(meta.lastPage, current + 1))
                }
                className="h-9 rounded-lg border border-border px-3 text-xs font-semibold hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40"
              >
                {t.next}
              </button>
            </div>
          </div>
        </section>
      </div>

      {editorMode && (
        <UserEditor
          locale={locale}
          mode={editorMode}
          user={selectedUser}
          departments={departments}
          roleOptions={roleOptions}
          roleCatalogError={roleCatalogError}
          departmentTreeError={departmentTreeError}
          actorPermissions={actorPermissions}
          canUpdateIdentity={canUpdate}
          canAssignRoles={canAssignRoles}
          canManageDepartments={canManageDepartments}
          canManageUserFileTypes={canManageUserFileTypes}
          actorIsSuperAdmin={actorIsSuperAdmin}
          onClose={() => {
            setEditorMode(null);
            setSelectedUser(null);
          }}
          onSaved={async (message) => {
            setNotice(message);
            setEditorMode(null);
            setSelectedUser(null);
            await loadUsers();
          }}
        />
      )}
      {resettingUser ? (
        <PasswordResetDialog
          locale={locale}
          user={resettingUser}
          onClose={() => setResettingUser(null)}
          onDone={async () => {
            setNotice(t.resetPasswordDone);
            setResettingUser(null);
            await loadUsers();
          }}
        />
      ) : null}
    </main>
  );
}

function UserEditor({
  locale,
  mode,
  user,
  departments,
  roleOptions,
  roleCatalogError,
  departmentTreeError,
  actorPermissions,
  canUpdateIdentity,
  canAssignRoles,
  canManageDepartments,
  canManageUserFileTypes,
  actorIsSuperAdmin,
  onClose,
  onSaved,
}: {
  locale: StorviaLocale;
  mode: Exclude<EditorMode, null>;
  user: AdministrationUser | null;
  departments: AdministrationDepartment[];
  roleOptions: AdministrationRole[];
  roleCatalogError: boolean;
  departmentTreeError: boolean;
  actorPermissions: Set<string>;
  canUpdateIdentity: boolean;
  canAssignRoles: boolean;
  canManageDepartments: boolean;
  canManageUserFileTypes: boolean;
  actorIsSuperAdmin: boolean;
  onClose: () => void;
  onSaved: (message: string) => Promise<void>;
}) {
  const t = COPY[locale];
  const protectedIdentity =
    mode === "edit" && user != null && isSuperAdmin(user) && !actorIsSuperAdmin;
  const mayEditIdentity = mode === "create" || (canUpdateIdentity && !protectedIdentity);

  const organizationSelection = React.useMemo(
    () => initialOrganizationSelection(user, departments),
    [departments, user],
  );

  const [name, setName] = React.useState(user?.name ?? "");
  const [username, setUsername] = React.useState(user?.username ?? "");
  const [email, setEmail] = React.useState(user?.email ?? "");
  const [userLocale, setUserLocale] = React.useState<StorviaLocale>(
    user?.locale ?? "en",
  );
  const [personalSpaceEnabled, setPersonalSpaceEnabled] = React.useState(
    user?.personal_space_enabled ?? true,
  );
  const [password, setPassword] = React.useState("");
  const [passwordConfirmation, setPasswordConfirmation] = React.useState("");
  const [role, setRole] = React.useState(
    user?.roles.length === 1 ? user.roles[0] : mode === "create" ? "member" : "",
  );
  const [administrationId, setAdministrationId] = React.useState(
    organizationSelection.administrationId,
  );
  const [departmentIds, setDepartmentIds] = React.useState<string[]>(
    organizationSelection.departmentIds,
  );
  const [saving, setSaving] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  async function save(event: React.FormEvent) {
    event.preventDefault();
    setSaving(true);
    setError(null);

    try {
      if (canAssignRoles && !role) {
        setError(t.legacyRoleConflict);
        return;
      }

      const organizationIds = administrationId
        ? [administrationId, ...departmentIds]
        : [];

      if (mode === "create") {
        const created = await createAdministrationUser({
          name,
          username,
          email,
          password,
          password_confirmation: passwordConfirmation,
          locale: userLocale,
          personal_space_enabled: personalSpaceEnabled,
        });

        if (canAssignRoles && !roleCatalogError) {
          await syncAdministrationUserRoles(created.id, [role]);
        }

        if (canManageDepartments && !departmentTreeError) {
          await syncAdministrationUserDepartments(created.id, organizationIds);
        }

        await onSaved(t.created);
        return;
      }

      if (!user) return;

      if (mayEditIdentity) {
        await updateAdministrationUser(user.id, {
          name,
          username,
          email,
          locale: userLocale,
          personal_space_enabled: personalSpaceEnabled,
        });
      }

      if (canAssignRoles && !protectedIdentity && !roleCatalogError) {
        await syncAdministrationUserRoles(user.id, [role]);
      }

      if (canManageDepartments && !departmentTreeError) {
        await syncAdministrationUserDepartments(user.id, organizationIds);
      }

      await onSaved(t.updated);
    } catch (requestError) {
      setError(readableError(requestError, t.unknownError));
    } finally {
      setSaving(false);
    }
  }

  function toggleDepartment(id: string) {
    setDepartmentIds((current) =>
      current.includes(id)
        ? current.filter((value) => value !== id)
        : [...current, id],
    );
  }

  const selectedAdministration = departments.find(
    (department) => String(department.id) === administrationId,
  );
  const directDepartments = selectedAdministration?.children ?? [];

  return (
    <div className="fixed inset-0 z-[80] flex items-stretch justify-end bg-black/45 backdrop-blur-[1px]">
      <button
        type="button"
        aria-label={t.close}
        className="absolute inset-0"
        onClick={onClose}
      />

      <aside
        className="relative z-10 h-full w-full max-w-[640px] overflow-y-auto border-s border-border bg-background shadow-2xl"
        dir={locale === "ar" ? "rtl" : "ltr"}
      >
        <form onSubmit={save} className="flex min-h-full flex-col">
          <header className="sticky top-0 z-10 flex items-center justify-between border-b border-border bg-background/95 px-5 py-4 backdrop-blur">
            <div>
              <h2 className="text-lg font-bold">
                {mode === "create" ? t.createTitle : t.editTitle}
              </h2>
              {user?.email && (
                <p className="mt-1 text-xs text-muted-foreground">{user.email}</p>
              )}
            </div>

            <button
              type="button"
              onClick={onClose}
              className="flex size-9 items-center justify-center rounded-lg hover:bg-accent"
            >
              <KeenIcon name="cross" className="text-[18px]" />
            </button>
          </header>

          <div className="flex-1 space-y-6 p-5">
            {error && (
              <div className="rounded-lg border border-destructive/25 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                {error}
              </div>
            )}

            <section className="rounded-xl border border-border p-4">
              <div className="mb-4 flex items-center justify-between">
                <h3 className="font-semibold">{t.name}</h3>
                {protectedIdentity && (
                  <span className="rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
                    {t.protectedUser}
                  </span>
                )}
              </div>

              <div className="grid gap-4 sm:grid-cols-2">
                <Field label={t.name}>
                  <input
                    required
                    disabled={!mayEditIdentity}
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                    className="h-10 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/30 disabled:cursor-not-allowed disabled:opacity-60"
                  />
                </Field>

                <Field label={t.username}>
                  <input
                    required
                    disabled={!mayEditIdentity}
                    value={username}
                    onChange={(event) => setUsername(event.target.value)}
                    className="h-10 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/30 disabled:cursor-not-allowed disabled:opacity-60"
                  />
                </Field>

                <Field label={t.email}>
                  <input
                    required
                    disabled={!mayEditIdentity}
                    type="email"
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    className="h-10 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/30 disabled:cursor-not-allowed disabled:opacity-60"
                  />
                </Field>

                <Field label={t.locale}>
                  <StorviaSelect
                    disabled={!mayEditIdentity}
                    value={userLocale}
                    onValueChange={(value) =>
                      setUserLocale(value === "ar" ? "ar" : "en")
                    }
                    options={[
                      { value: "en", label: t.english },
                      { value: "ar", label: t.arabic },
                    ]}
                    ariaLabel={t.locale}
                    className="h-10 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/30 disabled:cursor-not-allowed disabled:opacity-60"
                  />
                </Field>

                {mode === "create" ? (
                  <>
                    <Field label={t.password}>
                      <input
                        required
                        disabled={!mayEditIdentity}
                        type="password"
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                        className="h-10 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/30 disabled:cursor-not-allowed disabled:opacity-60"
                      />
                      <p className="text-[11px] leading-5 text-muted-foreground">
                        {t.passwordHint}
                      </p>
                    </Field>

                    <Field label={t.confirmPassword}>
                      <input
                        required
                        disabled={!mayEditIdentity}
                        type="password"
                        value={passwordConfirmation}
                        onChange={(event) =>
                          setPasswordConfirmation(event.target.value)
                        }
                        className="h-10 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-brand-cyan/30 disabled:cursor-not-allowed disabled:opacity-60"
                      />
                    </Field>
                  </>
                ) : null}
              </div>
            </section>

            <section className="rounded-xl border border-border p-4">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <h3 className="font-semibold">{t.userSpecificSettings}</h3>
                  <p className="mt-1 text-xs leading-5 text-muted-foreground">
                    {t.personalSpaceDescription}
                  </p>
                </div>
                <span className="rounded-md bg-brand-cyan/10 px-2 py-1 text-[10px] font-semibold text-brand-cyan">
                  User-specific
                </span>
              </div>

              <label
                className={cn(
                  "mt-4 flex items-center justify-between gap-4 rounded-lg border border-border p-3",
                  mayEditIdentity ? "cursor-pointer" : "opacity-65",
                )}
              >
                <span className="min-w-0">
                  <span className="block text-sm font-medium">{t.personalSpace}</span>
                  <span className="mt-0.5 block text-[11px] text-muted-foreground">
                    {personalSpaceEnabled
                      ? t.personalSpaceEnabled
                      : t.personalSpaceDisabled}
                  </span>
                </span>
                <input
                  type="checkbox"
                  checked={personalSpaceEnabled}
                  disabled={!mayEditIdentity || saving}
                  onChange={(event) => setPersonalSpaceEnabled(event.target.checked)}
                  className="size-4"
                />
              </label>

            </section>

            {canManageUserFileTypes ? (
              mode === "edit" && user ? (
                <UserFileTypePolicyEditor locale={locale} userId={user.id} />
              ) : (
                <section className="rounded-xl border border-border p-4">
                  <h3 className="font-semibold">{t.userSpecificSettings}</h3>
                  <p className="mt-2 text-sm leading-6 text-muted-foreground">
                    {t.userFileTypesAfterCreate}
                  </p>
                </section>
              )
            ) : null}

            <section className="rounded-xl border border-border p-4">
              <h3 className="font-semibold">{t.roles}</h3>

              {canAssignRoles && !protectedIdentity ? (
                roleOptions.length ? (
                  <div className="mt-3">
                    <StorviaSelect
                      value={role}
                      onValueChange={setRole}
                      options={roleOptions.map((option) => {
                        const assignable =
                          actorIsSuperAdmin ||
                          (option.name !== "super_admin" &&
                            option.permissions.every((permission) =>
                              actorPermissions.has(permission.name),
                            ));
                        return {
                          value: option.name,
                          label: roleLabel(option.name, locale, roleOptions),
                          keywords: option.name,
                          disabled: !assignable,
                        };
                      })}
                      placeholder={
                        roleCatalogError ? t.roleCatalogLoadFailed : t.noRoleOptions
                      }
                      searchable
                      searchPlaceholder={t.search}
                      emptyText={
                        roleCatalogError ? t.roleCatalogLoadFailed : t.noRoleOptions
                      }
                      invalid={!role}
                      ariaLabel={t.roles}
                    />
                    {!role ? (
                      <p className="mt-2 text-xs font-medium text-destructive">
                        {t.legacyRoleConflict}
                      </p>
                    ) : null}
                  </div>
                ) : (
                  <p className="mt-3 text-sm text-muted-foreground">
                    {roleCatalogError ? t.roleCatalogLoadFailed : t.noRoleOptions}
                  </p>
                )
              ) : (
                <p className="mt-3 text-sm text-muted-foreground">
                  {t.noRoleAccess}
                </p>
              )}
            </section>

            <section className="rounded-xl border border-border p-4">
              <h3 className="font-semibold">{t.departments}</h3>

              {canManageDepartments ? (
                <div className="mt-3 space-y-4">
                  <Field label={t.administration}>
                    <StorviaSelect
                      value={administrationId}
                      onValueChange={(value) => {
                        setAdministrationId(value);
                        setDepartmentIds([]);
                      }}
                      options={departments.map((administration) => ({
                        value: String(administration.id),
                        label: administration.name,
                      }))}
                      placeholder={t.chooseAdministration}
                      searchable
                      searchPlaceholder={t.search}
                      emptyText={
                        departmentTreeError
                          ? t.departmentTreeLoadFailed
                          : t.noDepartment
                      }
                      ariaLabel={t.administration}
                      disabled={departmentTreeError}
                    />
                  </Field>

                  {organizationSelection.legacyConflict &&
                  !administrationId ? (
                    <p className="text-xs font-medium text-destructive">
                      {t.legacyDepartmentConflict}
                    </p>
                  ) : null}

                  {administrationId ? (
                    <div>
                      <div className="mb-2 flex items-center justify-between">
                        <span className="text-xs font-semibold text-muted-foreground">
                          {t.childDepartments}
                        </span>
                        <span className="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold text-muted-foreground">
                          {departmentIds.length} {t.selected}
                        </span>
                      </div>
                      <div className="max-h-72 space-y-1 overflow-y-auto rounded-lg border border-border p-2">
                      {directDepartments.length ? (
                        directDepartments.map((department) => (
                      <label
                        key={String(department.id)}
                        className={cn(
                          "flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm transition-colors hover:bg-accent/50",
                          departmentIds.includes(String(department.id)) &&
                            "bg-brand-cyan/5",
                        )}
                      >
                        <input
                          type="checkbox"
                          checked={departmentIds.includes(String(department.id))}
                          onChange={() =>
                            toggleDepartment(String(department.id))
                          }
                        />
                        <span className="min-w-0 truncate">{department.name}</span>
                      </label>
                        ))
                      ) : (
                        <p className="px-2 py-3 text-sm text-muted-foreground">
                          {t.noChildDepartments}
                        </p>
                      )}
                      </div>
                    </div>
                  ) : null}
                </div>
              ) : (
                <p className="mt-3 text-sm text-muted-foreground">
                  {t.noDepartmentAccess}
                </p>
              )}
            </section>
          </div>

          <footer className="sticky bottom-0 flex justify-end gap-2 border-t border-border bg-background/95 px-5 py-4 backdrop-blur">
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
              disabled={saving}
              className="h-10 rounded-lg bg-brand-cyan px-5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
            >
              {saving ? t.saving : t.save}
            </button>
          </footer>
        </form>
      </aside>
    </div>
  );
}

function PasswordResetDialog({
  locale,
  user,
  onClose,
  onDone,
}: {
  locale: StorviaLocale;
  user: AdministrationUser;
  onClose: () => void;
  onDone: () => Promise<void>;
}) {
  const t = COPY[locale];
  const [password, setPassword] = React.useState("");
  const [confirmation, setConfirmation] = React.useState("");
  const [saving, setSaving] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setSaving(true);
    setError(null);

    try {
      await resetAdministrationUserPassword(user.id, password, confirmation);
      await onDone();
    } catch (requestError) {
      setError(readableError(requestError, t.unknownError));
    } finally {
      setSaving(false);
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent dir={locale === "ar" ? "rtl" : "ltr"}>
        <form onSubmit={submit}>
          <DialogHeader>
            <DialogTitle>{t.resetPasswordTitle}</DialogTitle>
            <DialogDescription>
              {t.resetPasswordUser}: {user.name}
              <br />
              {t.resetPasswordDescription}
            </DialogDescription>
          </DialogHeader>
          <div className="mt-5 space-y-4">
            <Field label={t.password}>
              <Input
                required
                type="password"
                autoComplete="new-password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
              />
            </Field>
            <Field label={t.confirmPassword}>
              <Input
                required
                type="password"
                autoComplete="new-password"
                value={confirmation}
                onChange={(event) => setConfirmation(event.target.value)}
              />
            </Field>
            <p className="text-[11px] leading-5 text-muted-foreground">
              {t.passwordHint}
            </p>
            {error ? (
              <p className="text-xs font-medium text-destructive" role="alert">
                {error}
              </p>
            ) : null}
          </div>
          <DialogFooter className="mt-6">
            <Button
              type="button"
              variant="secondary"
              onClick={onClose}
              disabled={saving}
            >
              {t.cancel}
            </Button>
            <Button type="submit" disabled={saving}>
              {saving ? t.saving : t.resetPassword}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
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
      <legend className="mb-1.5 text-xs font-semibold text-muted-foreground">
        {label}
      </legend>
      {children}
    </fieldset>
  );
}
