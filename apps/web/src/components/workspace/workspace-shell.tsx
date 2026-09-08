"use client";

import * as React from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";

import { AdminDashboard } from "@/components/administration/dashboard/admin-dashboard";
import { DepartmentManagement } from "@/components/administration/departments/department-management";
import { FileTypeManagement } from "@/components/administration/file-types/file-type-management";
import { UserDashboard } from "@/components/dashboard/user-dashboard";
import { PermissionCatalog } from "@/components/administration/permissions/permission-catalog";
import { RoleManagement } from "@/components/administration/roles/role-management";
import { StorageQuotaManagement } from "@/components/administration/storage-quotas/storage-quota-management";
import { UserManagement } from "@/components/administration/users/user-management";
import { KeenIcon } from "@/components/ui/keen-icon";
import { ContextPanel } from "@/components/workspace/context-panel";
import { FileWorkspace } from "@/components/workspace/file-workspace";
import { PrimaryRail } from "@/components/workspace/primary-rail";
import { WorkspaceFooter } from "@/components/workspace/workspace-footer";
import { WorkspaceHeader } from "@/components/workspace/workspace-header";
import { WORKSPACE_COPY } from "@/components/workspace/workspace-copy";
import type {
  FileManagerBreadcrumb,
  FileManagerFileSpace,
  FileManagerNode,
} from "@/lib/api/file-manager-client";
import { useAuth } from "@/lib/auth";
import { useI18n, type StorviaLocale } from "@/lib/i18n";
import {
  canAccessWorkspaceSection,
  canonicalWorkspaceHref,
  DEFAULT_WORKSPACE_ROUTE,
  fileManagerRoute,
  fileManagerRouteContext,
  fileManagerSectionRoute,
  loginPathForWorkspace,
  workspaceRouteForSection,
  workspaceSectionFromPathname,
} from "@/lib/routing/workspace-routes";
import { storviaDocumentTitle } from "@/lib/routing/page-titles";
import { cn } from "@/lib/utils";

const FILE_MANAGER_SECTIONS = new Set([
  "files",
  "favorites",
  "trash",
  "file-settings",
]);

function areStringArraysEqual(left: string[], right: string[]): boolean {
  return (
    left.length === right.length &&
    left.every((value, index) => value === right[index])
  );
}

export function WorkspaceShell() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { status, user, isAuthenticated, logout } = useAuth();
  const { locale, direction, setLocale } = useI18n();
  const activeSection = workspaceSectionFromPathname(pathname) ?? "dashboard";
  const [query, setQuery] = React.useState("");
  const [mobileOpen, setMobileOpen] = React.useState(false);
  const [fileManagerBreadcrumbs, setFileManagerBreadcrumbs] = React.useState<
    FileManagerBreadcrumb[]
  >([]);
  const [fileManagerDirectoryFolders, setFileManagerDirectoryFolders] =
    React.useState<FileManagerNode[]>([]);
  const [fileManagerSpaceContext, setFileManagerSpaceContext] = React.useState<{
    id: string | null;
    type: FileManagerFileSpace["type"];
    departmentName: string | null;
    departmentPath: string[];
  }>({
    id: null,
    type: "personal",
    departmentName: null,
    departmentPath: [],
  });

  const routeFileManagerContext = fileManagerRouteContext(searchParams);
  const search = searchParams.toString();
  const currentHref = search ? `${pathname}?${search}` : pathname;
  const canonicalHref = canonicalWorkspaceHref(pathname, searchParams);
  const personalSpaceEnabled =
    user?.workspace_entitlements.personal_space_enabled ?? false;
  const hasOrganizationalFileAccess =
    user?.workspace_entitlements.has_organizational_file_access ?? false;
  const hasFileWorkspaceAccess =
    personalSpaceEnabled || hasOrganizationalFileAccess;

  const copy = WORKSPACE_COPY[locale];
  const routeAuthorized = user
    ? canAccessWorkspaceSection(user, activeSection)
    : false;

  React.useEffect(() => {
    if (status === "unauthenticated" || status === "disabled") {
      router.replace(loginPathForWorkspace(pathname, searchParams));
    }
  }, [pathname, router, searchParams, status]);

  React.useEffect(() => {
    if (
      status === "authenticated" &&
      canonicalHref &&
      currentHref !== canonicalHref
    ) {
      router.replace(canonicalHref);
    }
  }, [canonicalHref, currentHref, router, status]);

  React.useEffect(() => {
    if (status === "authenticated" && user && !routeAuthorized) {
      router.replace(DEFAULT_WORKSPACE_ROUTE);
    }
  }, [routeAuthorized, router, status, user]);

  const activeFileManagerSpaceId = routeFileManagerContext.departmentId
    ? null
    : routeFileManagerContext.spaceId ?? fileManagerSpaceContext.id;

  const changeSection = React.useCallback(
    (section: string, explicitSpaceId?: string | null) => {
      const route =
        fileManagerSectionRoute(
          section,
          explicitSpaceId === undefined
            ? activeFileManagerSpaceId
            : explicitSpaceId,
        ) ?? workspaceRouteForSection(section);

      if (!route) {
        return;
      }

      setMobileOpen(false);
      router.push(route);
    },
    [activeFileManagerSpaceId, router, setMobileOpen],
  );

  const navigateFileManager = React.useCallback(
    (
      spaceId: string | null,
      folderId: string | null,
      replace = false,
    ) => {
      setMobileOpen(false);
      const route = fileManagerRoute(spaceId, folderId);

      if (replace) {
        router.replace(route);
      } else {
        router.push(route);
      }
    },
    [router, setMobileOpen],
  );

  const navigateDepartmentNavigation = (departmentId: string) => {
    setMobileOpen(false);
    setFileManagerSpaceContext({
      id: null,
      type: "department",
      departmentName: null,
      departmentPath: [],
    });
    setFileManagerBreadcrumbs([]);
    setFileManagerDirectoryFolders([]);
    router.push(fileManagerRoute(null, null, departmentId));
  };

  const openFileManagerRoot = () => {
    setFileManagerBreadcrumbs([]);
    setFileManagerDirectoryFolders([]);
    navigateFileManager(activeFileManagerSpaceId, null);
  };

  const openFileManagerSettings = () => {
    changeSection("file-settings");
  };

  const goBackInFileManager = () => {
    if (
      activeSection !== "files" ||
      !activeFileManagerSpaceId ||
      fileManagerBreadcrumbs.length === 0
    ) {
      return;
    }

    const previous =
      fileManagerBreadcrumbs.length > 1
        ? fileManagerBreadcrumbs[fileManagerBreadcrumbs.length - 2]
        : null;

    if (previous === null) {
      setFileManagerBreadcrumbs([]);
    }

    navigateFileManager(activeFileManagerSpaceId, previous?.id ?? null);
  };

  const openFileManagerBreadcrumb = (breadcrumbId: string) => {
    const index = fileManagerBreadcrumbs.findIndex(
      (breadcrumb) => breadcrumb.id === breadcrumbId,
    );

    if (index < 0 || !activeFileManagerSpaceId) {
      return;
    }

    setFileManagerBreadcrumbs((current) => current.slice(0, index + 1));
    navigateFileManager(activeFileManagerSpaceId, breadcrumbId);
  };

  const openFileManagerDirectoryFolder = (folderId: string) => {
    if (!activeFileManagerSpaceId) {
      return;
    }

    setFileManagerDirectoryFolders([]);
    navigateFileManager(activeFileManagerSpaceId, folderId);
  };

  const handleFileManagerSpaceContextChange = React.useCallback(
    (space: FileManagerFileSpace) => {
      const departmentPath =
        space.department_navigation_path.length > 0
          ? space.department_navigation_path
          : space.department_path;
      const nextDepartmentPath =
        departmentPath.length > 0
          ? departmentPath.map((segment) => segment.name)
          : space.department_name
            ? [space.department_name]
            : [];

      setFileManagerSpaceContext((current) => {
        if (
          current.id === space.id &&
          current.type === space.type &&
          current.departmentName === space.department_name &&
          areStringArraysEqual(current.departmentPath, nextDepartmentPath)
        ) {
          return current;
        }

        return {
          id: space.id,
          type: space.type,
          departmentName: space.department_name,
          departmentPath: nextDepartmentPath,
        };
      });
    },
    [setFileManagerSpaceContext],
  );

  const handleDepartmentNavigationContextChange = React.useCallback(
    (departmentPath: string[]) => {
      const departmentName = departmentPath.at(-1) ?? null;

      setFileManagerSpaceContext((current) => {
        if (
          current.id === null &&
          current.type === "department" &&
          current.departmentName === departmentName &&
          areStringArraysEqual(current.departmentPath, departmentPath)
        ) {
          return current;
        }

        return {
          id: null,
          type: "department",
          departmentName,
          departmentPath,
        };
      });
      setFileManagerBreadcrumbs((current) =>
        current.length === 0 ? current : [],
      );
      setFileManagerDirectoryFolders((current) =>
        current.length === 0 ? current : [],
      );
    },
    [
      setFileManagerBreadcrumbs,
      setFileManagerDirectoryFolders,
      setFileManagerSpaceContext,
    ],
  );

  const handleLogout = React.useCallback(async () => {
    await logout();
    router.replace("/login");
  }, [logout, router]);

  if (status === "loading") {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background text-foreground">
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <KeenIcon
            name="loading"
            className="animate-spin text-[17px] text-primary"
          />
          <span>{copy.loadingWorkspace}</span>
        </div>
      </div>
    );
  }

  if (!isAuthenticated || !user) {
    return null;
  }

  if (!routeAuthorized) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background text-foreground">
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <KeenIcon
            name="loading"
            className="animate-spin text-[17px] text-primary"
          />
          <span>{copy.loadingWorkspace}</span>
        </div>
      </div>
    );
  }

  const isSuperAdmin = user.roles.includes("super_admin");
  const permissionSet = new Set(user.permissions);
  const canViewUsers = isSuperAdmin || permissionSet.has("users.view");
  const canViewDepartments =
    isSuperAdmin || permissionSet.has("departments.view");
  const canViewRoles = isSuperAdmin || permissionSet.has("roles.view");
  const canViewPermissions =
    isSuperAdmin || permissionSet.has("permissions.view");
  const canManageSystem = isSuperAdmin || permissionSet.has("system.manage");
  const canManageStorageQuotas = canManageSystem;
  const canManageFileTypes = canManageSystem;
  const showAdministration =
    canViewUsers ||
    canViewDepartments ||
    canViewRoles ||
    canViewPermissions ||
    canManageStorageQuotas ||
    canManageFileTypes;
  const fileManagerCanGoBack =
    activeSection === "files" &&
    routeFileManagerContext.folderId !== null &&
    fileManagerBreadcrumbs.length > 0;
  const fileManagerRootLabel =
    fileManagerSpaceContext.type === "department" ||
    (!personalSpaceEnabled && hasOrganizationalFileAccess)
      ? copy.companyDrive
      : copy.myFiles;
  const fileManagerOrganizationPath =
    fileManagerSpaceContext.type === "department"
      ? fileManagerSpaceContext.departmentPath
      : [];

  const authorizationRevision = [
    user.id,
    user.status,
    [...user.roles].sort().join(","),
    [...user.permissions].sort().join(","),
    user.workspace_entitlements.personal_space_enabled ? "personal:1" : "personal:0",
    user.workspace_entitlements.has_organizational_file_access ? "org:1" : "org:0",
    `org-scope:${user.organizational_scope_revision}`,
  ].join("|");

  const fileManagerPanel = (
    <ContextPanel
      copy={copy}
      activeSection={activeSection}
      hasFileWorkspaceAccess={hasFileWorkspaceAccess}
      onSectionChange={changeSection}
      canGoBack={fileManagerCanGoBack}
      onBack={goBackInFileManager}
      onRoot={openFileManagerRoot}
      onSettings={openFileManagerSettings}
      rootLabel={fileManagerRootLabel}
      organizationPath={fileManagerOrganizationPath}
      breadcrumbs={fileManagerBreadcrumbs}
      directoryFolders={fileManagerDirectoryFolders}
      onBreadcrumbSelect={openFileManagerBreadcrumb}
      onDirectoryFolderSelect={openFileManagerDirectoryFolder}
    />
  );

  return (
    <div
      dir={direction}
      lang={locale}
      className={cn(
        "h-dvh overflow-hidden bg-workspace-background text-foreground",
        locale === "ar" && "font-arabic",
      )}
    >
      <title>{storviaDocumentTitle(locale, activeSection)}</title>
      {/* Keep exactly one live workspace tree across responsive breakpoints.
          CSS-hidden duplicate trees still mount React effects and can issue
          File Manager requests with independent stale file-space state. */}
      <div className="grid h-full grid-cols-[minmax(0,1fr)] lg:grid-cols-[68px_minmax(0,1fr)]">
        <div className="hidden min-h-0 lg:flex">
          <PrimaryRail
            copy={copy}
            locale={locale}
            activeSection={activeSection}
            onSectionChange={changeSection}
            personalSpaceEnabled={personalSpaceEnabled}
            hasOrganizationalFileAccess={hasOrganizationalFileAccess}
            showAdministration={showAdministration}
            canViewAdminDashboard={canManageSystem}
            canViewUsers={canViewUsers}
            canViewDepartments={canViewDepartments}
            canViewRoles={canViewRoles}
            canViewPermissions={canViewPermissions}
            canManageStorageQuotas={canManageStorageQuotas}
            canManageFileTypes={canManageFileTypes}
          />
        </div>

        <div className="flex min-w-0 flex-col overflow-hidden">
          <WorkspaceHeader
            copy={copy}
            locale={locale}
            user={user}
            activeSection={activeSection}
            query={query}
            onQueryChange={setQuery}
            onLocaleChange={setLocale}
            onOpenNavigation={() => setMobileOpen(true)}
            onLogout={handleLogout}
          />
          <WorkspaceContent
            key={authorizationRevision}
            copy={copy}
            locale={locale}
            userId={user.id}
            personalSpaceEnabled={personalSpaceEnabled}
            hasOrganizationalFileAccess={hasOrganizationalFileAccess}
            canViewUsers={canViewUsers}
            canViewDepartments={canViewDepartments}
            canManageStorageQuotas={canManageStorageQuotas}
            canManageFileTypes={canManageFileTypes}
            activeSection={activeSection}
            query={query}
            onSectionChange={changeSection}
            fileManagerSpaceId={routeFileManagerContext.spaceId}
            fileManagerDepartmentId={routeFileManagerContext.departmentId}
            fileManagerParentId={routeFileManagerContext.folderId}
            onFileManagerRouteChange={navigateFileManager}
            onDepartmentNavigationChange={navigateDepartmentNavigation}
            onFileManagerBreadcrumbsChange={setFileManagerBreadcrumbs}
            onFileManagerDirectoryFoldersChange={setFileManagerDirectoryFolders}
            onFileManagerSpaceContextChange={handleFileManagerSpaceContextChange}
            onDepartmentNavigationContextChange={handleDepartmentNavigationContextChange}
            fileManagerPanel={fileManagerPanel}
          />
          <WorkspaceFooter locale={locale} />
        </div>
      </div>

      {mobileOpen ? (
        <div className="fixed inset-0 z-50 lg:hidden">
          <button
            type="button"
            aria-label={copy.closeNavigation}
            className="absolute inset-0 bg-black/45 backdrop-blur-[1px]"
            onClick={() => setMobileOpen(false)}
          />
          <aside className="absolute inset-y-0 start-0 flex w-[min(88vw,340px)] flex-col overflow-hidden border-e border-border bg-workspace-panel shadow-lg">
            <div className="flex h-14 items-center justify-between border-b border-border px-4">
              <div>
                <p className="text-sm font-bold tracking-tight">STORVIA</p>
                <p className="text-[10px] text-muted-foreground">
                  {copy.secureWorkspace}
                </p>
              </div>
              <button
                type="button"
                aria-label={copy.closeNavigation}
                onClick={() => setMobileOpen(false)}
                className="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground"
              >
                <KeenIcon name="cross" className="text-[17px]" />
              </button>
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto">
              <div className="border-b border-border bg-workspace-rail py-2 text-workspace-rail-foreground">
                <PrimaryRail
                  copy={copy}
                  locale={locale}
                  activeSection={activeSection}
                  onSectionChange={changeSection}
                  personalSpaceEnabled={personalSpaceEnabled}
                  hasOrganizationalFileAccess={hasOrganizationalFileAccess}
                  showAdministration={showAdministration}
                  canViewAdminDashboard={canManageSystem}
                  canViewUsers={canViewUsers}
                  canViewDepartments={canViewDepartments}
                  canViewRoles={canViewRoles}
                  canViewPermissions={canViewPermissions}
                  canManageStorageQuotas={canManageStorageQuotas}
                  canManageFileTypes={canManageFileTypes}
                  compact
                />
              </div>

              {FILE_MANAGER_SECTIONS.has(activeSection) ? (
                <ContextPanel
                  copy={copy}
                  activeSection={activeSection}
                  hasFileWorkspaceAccess={hasFileWorkspaceAccess}
                  onSectionChange={changeSection}
                  canGoBack={fileManagerCanGoBack}
                  onBack={goBackInFileManager}
                  onRoot={openFileManagerRoot}
                  onSettings={openFileManagerSettings}
                  rootLabel={fileManagerRootLabel}
                  organizationPath={fileManagerOrganizationPath}
                  breadcrumbs={fileManagerBreadcrumbs}
                  directoryFolders={fileManagerDirectoryFolders}
                  onBreadcrumbSelect={openFileManagerBreadcrumb}
                  onDirectoryFolderSelect={openFileManagerDirectoryFolder}
                  compact
                />
              ) : null}
            </div>
          </aside>
        </div>
      ) : null}
    </div>
  );
}

function WorkspaceContent({
  copy,
  locale,
  userId,
  personalSpaceEnabled,
  hasOrganizationalFileAccess,
  canViewUsers,
  canViewDepartments,
  canManageStorageQuotas,
  canManageFileTypes,
  activeSection,
  query,
  onSectionChange,
  fileManagerSpaceId,
  fileManagerDepartmentId,
  fileManagerParentId,
  onFileManagerRouteChange,
  onDepartmentNavigationChange,
  onFileManagerBreadcrumbsChange,
  onFileManagerDirectoryFoldersChange,
  onFileManagerSpaceContextChange,
  onDepartmentNavigationContextChange,
  fileManagerPanel,
}: {
  copy: (typeof WORKSPACE_COPY)[StorviaLocale];
  locale: StorviaLocale;
  userId: string;
  personalSpaceEnabled: boolean;
  hasOrganizationalFileAccess: boolean;
  canViewUsers: boolean;
  canViewDepartments: boolean;
  canManageStorageQuotas: boolean;
  canManageFileTypes: boolean;
  activeSection: string;
  query: string;
  onSectionChange: (section: string, explicitSpaceId?: string | null) => void;
  fileManagerSpaceId: string | null;
  fileManagerDepartmentId: string | null;
  fileManagerParentId: string | null;
  onFileManagerRouteChange: (
    spaceId: string | null,
    folderId: string | null,
    replace?: boolean,
  ) => void;
  onDepartmentNavigationChange: (departmentId: string) => void;
  onFileManagerBreadcrumbsChange: (
    breadcrumbs: FileManagerBreadcrumb[],
  ) => void;
  onFileManagerDirectoryFoldersChange: (folders: FileManagerNode[]) => void;
  onFileManagerSpaceContextChange: (space: FileManagerFileSpace) => void;
  onDepartmentNavigationContextChange: (path: string[]) => void;
  fileManagerPanel: React.ReactNode;
}) {
  if (FILE_MANAGER_SECTIONS.has(activeSection)) {
    return (
      <main className="min-w-0 flex-1 overflow-y-auto bg-workspace-background">
        <div className="mx-auto grid w-full max-w-[1640px] gap-5 p-4 sm:p-5 lg:grid-cols-[288px_minmax(0,1fr)] lg:p-6">
          <div className="hidden lg:block">{fileManagerPanel}</div>
          <FileWorkspace
            copy={copy}
            locale={locale}
            query={query}
            section={activeSection}
            currentUserId={userId}
            personalSpaceEnabled={personalSpaceEnabled}
            hasOrganizationalFileAccess={hasOrganizationalFileAccess}
            requestedSpaceId={fileManagerSpaceId}
            requestedDepartmentId={fileManagerDepartmentId}
            parentId={fileManagerParentId}
            onRouteChange={onFileManagerRouteChange}
            onDepartmentNavigationChange={onDepartmentNavigationChange}
            onBreadcrumbsChange={onFileManagerBreadcrumbsChange}
            onSectionChange={onSectionChange}
            onFileSpaceContextChange={onFileManagerSpaceContextChange}
            onDepartmentNavigationContextChange={onDepartmentNavigationContextChange}
            onDirectoryFoldersChange={onFileManagerDirectoryFoldersChange}
          />
        </div>
      </main>
    );
  }

  if (activeSection === "dashboard") {
    return (
      <UserDashboard
        copy={copy}
        locale={locale}
        personalSpaceEnabled={personalSpaceEnabled}
        hasOrganizationalFileAccess={hasOrganizationalFileAccess}
        onSectionChange={onSectionChange}
      />
    );
  }

  if (activeSection === "admin-dashboard") {
    return (
      <AdminDashboard
        locale={locale}
        canViewUsers={canViewUsers}
        canViewDepartments={canViewDepartments}
        canManageStorageQuotas={canManageStorageQuotas}
        onSectionChange={onSectionChange}
      />
    );
  }

  if (activeSection === "users") {
    return <UserManagement locale={locale} />;
  }

  if (activeSection === "departments") {
    return <DepartmentManagement locale={locale} />;
  }

  if (activeSection === "roles") {
    return <RoleManagement locale={locale} />;
  }

  if (activeSection === "permissions") {
    return <PermissionCatalog locale={locale} />;
  }

  if (activeSection === "storage-quotas") {
    return <StorageQuotaManagement locale={locale} />;
  }

  if (activeSection === "file-types" && canManageFileTypes) {
    return <FileTypeManagement locale={locale} />;
  }

  const config =
    activeSection === "administration"
      ? { title: copy.administration, icon: "shield-tick" }
      : { title: copy.settings, icon: "setting-2" };

  return (
    <main className="min-w-0 flex-1 overflow-y-auto bg-workspace-background p-4 sm:p-5 lg:p-6">
      <div className="mx-auto flex min-h-[420px] max-w-[980px] items-center justify-center rounded-xl border border-dashed border-workspace-content-border bg-card/55 p-8 text-center">
        <div className="max-w-md">
          <div className="mx-auto flex size-12 items-center justify-center rounded-xl bg-brand-blue/10 text-brand-blue">
            <KeenIcon name={config.icon} className="text-[21px]" />
          </div>
          <h1 className="mt-4 text-lg font-bold">{config.title}</h1>
          <p className="mt-2 text-sm leading-6 text-muted-foreground">
            {copy.sectionPendingDescription}
          </p>
        </div>
      </div>
    </main>
  );
}
function FileAccessAlert({
  copy,
}: {
  copy: (typeof WORKSPACE_COPY)[StorviaLocale];
}) {
  return (
    <section
      role="alert"
      className="rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-3"
    >
      <div className="flex items-start gap-3">
        <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-700 dark:text-amber-300">
          <KeenIcon name="information-2" className="text-[17px]" />
        </span>
        <div>
          <p className="text-sm font-semibold">{copy.noFileAccessTitle}</p>
          <p className="mt-1 text-xs leading-5 text-muted-foreground">
            {copy.noFileAccessDescription}
          </p>
        </div>
      </div>
    </section>
  );
}
