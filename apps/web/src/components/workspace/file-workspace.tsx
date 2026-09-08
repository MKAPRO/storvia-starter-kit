"use client";

import * as React from "react";
import Image from "next/image";

import { StorviaMark } from "@/components/brand/storvia-mark";
import { DriveBrowser } from "@/components/file-manager/drive-browser";
import { resolveDepartmentPathLabel } from "@/components/file-manager/file-manager-department-path";
import {
  departmentLeafLabel,
  groupDepartmentSpaces,
  type DepartmentSpaceGroup,
} from "@/components/file-manager/file-space-navigation";
import { FileSpaceSwitcher } from "@/components/file-manager/file-space-switcher";
import { SpecialNodeBrowser } from "@/components/file-manager/special-node-browser";
import { Button } from "@/components/ui/button";
import { KeenIcon } from "@/components/ui/keen-icon";
import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import {
  listFileManagerSpaces,
  type FileManagerBreadcrumb,
  type FileManagerFileSpace,
  type FileManagerNode,
  type FileManagerStorageQuota,
} from "@/lib/api/file-manager-client";
import type { StorviaLocale } from "@/lib/i18n";

type FileWorkspaceProps = {
  copy: WorkspaceCopy;
  locale: StorviaLocale;
  query: string;
  section: string;
  currentUserId: string;
  personalSpaceEnabled: boolean;
  hasOrganizationalFileAccess: boolean;
  requestedSpaceId: string | null;
  requestedDepartmentId: string | null;
  parentId: string | null;
  onRouteChange: (
    spaceId: string | null,
    folderId: string | null,
    replace?: boolean,
  ) => void;
  onDepartmentNavigationChange: (departmentId: string) => void;
  onBreadcrumbsChange: (breadcrumbs: FileManagerBreadcrumb[]) => void;
  onSectionChange: (section: string, explicitSpaceId?: string | null) => void;
  onFileSpaceContextChange: (space: FileManagerFileSpace) => void;
  onDepartmentNavigationContextChange: (path: string[]) => void;
  onDirectoryFoldersChange: (folders: FileManagerNode[]) => void;
};

export function FileWorkspace({
  copy,
  locale,
  query,
  section,
  currentUserId,
  personalSpaceEnabled,
  hasOrganizationalFileAccess,
  requestedSpaceId,
  requestedDepartmentId,
  parentId,
  onRouteChange,
  onDepartmentNavigationChange,
  onBreadcrumbsChange,
  onSectionChange,
  onFileSpaceContextChange,
  onDepartmentNavigationContextChange,
  onDirectoryFoldersChange,
}: FileWorkspaceProps) {
  const [spaces, setSpaces] = React.useState<FileManagerFileSpace[]>([]);
  const [loadingSpaces, setLoadingSpaces] = React.useState(true);
  const [spaceError, setSpaceError] = React.useState(false);
  const [reloadKey, setReloadKey] = React.useState(0);
  const hasFileWorkspaceAccess =
    personalSpaceEnabled || hasOrganizationalFileAccess;

  React.useEffect(() => {
    if (!hasFileWorkspaceAccess) {
      return;
    }

    const controller = new AbortController();
    const timeout = window.setTimeout(() => {
      void (async () => {
        setLoadingSpaces(true);
        setSpaceError(false);

        try {
          const items = await listFileManagerSpaces(controller.signal);

          if (!controller.signal.aborted) {
            setSpaces(items);
          }
        } catch {
          if (!controller.signal.aborted) {
            setSpaceError(true);
          }
        } finally {
          if (!controller.signal.aborted) {
            setLoadingSpaces(false);
          }
        }
      })();
    }, 0);

    return () => {
      window.clearTimeout(timeout);
      controller.abort();
    };
  }, [hasFileWorkspaceAccess, reloadKey]);

  const visibleSpaces = hasFileWorkspaceAccess ? spaces : [];
  const personalSpace = personalSpaceEnabled
    ? (visibleSpaces.find(
        (space) =>
          space.type === "personal" && space.owner_id === currentUserId,
      ) ?? null)
    : null;
  const departmentSpaces = visibleSpaces.filter(
    (space) => space.type === "department" && space.department_id !== null,
  );
  const departmentGroups = React.useMemo(
    () => groupDepartmentSpaces(departmentSpaces, locale),
    [departmentSpaces, locale],
  );
  const explicitlySelectedSpace = requestedSpaceId
    ? (visibleSpaces.find((space) => space.id === requestedSpaceId) ?? null)
    : null;
  const requestedSpaceMissing =
    requestedSpaceId !== null && explicitlySelectedSpace === null;
  const requestedDepartmentGroup = requestedDepartmentId
    ? (departmentGroups.find((group) => group.key === requestedDepartmentId) ?? null)
    : null;
  const requestedDepartmentTargetSpace = requestedDepartmentGroup
    ? requestedDepartmentGroup.rootSpace ??
      (requestedDepartmentGroup.sections.length === 1
        ? requestedDepartmentGroup.sections[0]
        : null)
    : null;
  const requestedNavigationGroup =
    requestedDepartmentGroup?.rootSpace === null &&
    requestedDepartmentGroup.sections.length > 1
      ? requestedDepartmentGroup
      : null;
  const requestedNavigationMissing =
    requestedDepartmentId !== null && requestedDepartmentGroup === null;
  const defaultNavigationGroup =
    requestedSpaceId === null &&
    requestedDepartmentId === null &&
    personalSpace === null &&
    departmentGroups.length === 1 &&
    departmentGroups[0].rootSpace === null &&
    departmentGroups[0].sections.length > 1
      ? departmentGroups[0]
      : null;
  const navigationGroup =
    requestedSpaceId === null
      ? requestedNavigationGroup ?? defaultNavigationGroup
      : null;
  const activeSpace =
    requestedSpaceMissing || requestedNavigationMissing || navigationGroup
      ? null
      : explicitlySelectedSpace ??
        requestedDepartmentTargetSpace ??
        personalSpace ??
        departmentSpaces[0] ??
        null;
  const activeSpaceCapabilityKey =
    activeSpace?.allowed_actions.join("|") ?? "";
  const activeSpaceTitle = navigationGroup
    ? copy.companyDrive
    : activeSpace?.type === "department"
      ? copy.companyDrive
      : copy.myFiles;
  const activeDepartmentPathLabel = navigationGroup
    ? navigationGroup.name
    : activeSpace?.type === "department"
      ? resolveDepartmentPathLabel(activeSpace)
      : null;
  const sectionTitle =
    section === "favorites"
      ? copy.favorites
      : section === "trash"
        ? copy.trash
        : section === "file-settings"
          ? copy.fileManagerSettings
          : activeSpaceTitle;

  React.useEffect(() => {
    if (requestedDepartmentId && requestedDepartmentTargetSpace) {
      onRouteChange(requestedDepartmentTargetSpace.id, null, true);
    }
  }, [
    onRouteChange,
    requestedDepartmentId,
    requestedDepartmentTargetSpace,
  ]);

  React.useEffect(() => {
    if (activeSpace) {
      onFileSpaceContextChange(activeSpace);
      return;
    }

    if (navigationGroup) {
      onDepartmentNavigationContextChange([navigationGroup.name]);
    }
  }, [
    activeSpace,
    navigationGroup,
    onDepartmentNavigationContextChange,
    onFileSpaceContextChange,
  ]);

  const switchFileSpace = React.useCallback(
    (space: FileManagerFileSpace) => {
      if (space.id === activeSpace?.id) {
        return;
      }

      onBreadcrumbsChange([]);
      onDirectoryFoldersChange([]);

      if (section === "favorites" || section === "trash") {
        onSectionChange(section, space.id);
        return;
      }

      onRouteChange(space.id, null);
    },
    [
      activeSpace?.id,
      onBreadcrumbsChange,
      onDirectoryFoldersChange,
      onRouteChange,
      onSectionChange,
      section,
    ],
  );

  const openDepartmentNavigation = React.useCallback(
    (departmentId: string) => {
      onBreadcrumbsChange([]);
      onDirectoryFoldersChange([]);
      onDepartmentNavigationChange(departmentId);
    }, [
      onBreadcrumbsChange,
      onDepartmentNavigationChange,
      onDirectoryFoldersChange,
    ],
  );

  const updateFileSpaceQuota = React.useCallback(
    (fileSpaceId: string, quota: FileManagerStorageQuota) => {
      setSpaces((current) =>
        current.map((space) =>
          space.id === fileSpaceId ? { ...space, quota } : space,
        ),
      );
    },
    [],
  );

  const refreshFileSpaceScope = React.useCallback(
    (fileSpaceId: string) => {
      onBreadcrumbsChange([]);
      onDirectoryFoldersChange([]);
      onRouteChange(fileSpaceId, null, true);
      setReloadKey((current) => current + 1);
    }, [onBreadcrumbsChange, onDirectoryFoldersChange, onRouteChange],
  );

  React.useEffect(() => {
    if (section !== "files") {
      onDirectoryFoldersChange([]);
    }
  }, [onDirectoryFoldersChange, section]);

  const loading = hasFileWorkspaceAccess && loadingSpaces;
  const invalidRequestedDestination =
    requestedSpaceMissing || requestedNavigationMissing;

  return (
    <section className="min-w-0 space-y-5">
      <section
        className="relative overflow-hidden rounded-xl border border-workspace-content-border bg-card shadow-xs"
        aria-labelledby="workspace-overview-title"
      >
        <div className="relative flex min-h-[118px] items-center px-4 py-4 sm:px-5 lg:px-6">
          <div className="relative z-10 flex min-w-0 items-center gap-3.5 lg:max-w-[52%]">
            <div className="flex size-11 shrink-0 items-center justify-center rounded-full border border-brand-blue/20 bg-brand-blue/5 text-brand-blue">
              <StorviaMark className="size-5.5" />
            </div>

            <div className="min-w-0">
              <h1
                id="workspace-overview-title"
                className="truncate text-[17px] font-bold tracking-tight sm:text-lg"
              >
                {copy.fileManager}
              </h1>
              <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10.5px] font-medium text-muted-foreground">
                <span className="text-brand-blue">STORVIA</span>
                <span aria-hidden="true">|</span>
                <span>{sectionTitle}</span>
                {activeDepartmentPathLabel ? (
                  <>
                    <span aria-hidden="true">|</span>
                    <span>{activeDepartmentPathLabel}</span>
                  </>
                ) : null}
                {activeSpace?.root_nodes_count != null && section === "files" ? (
                  <>
                    <span aria-hidden="true">|</span>
                    <span>
                      {activeSpace.root_nodes_count} {copy.items}
                    </span>
                  </>
                ) : null}
              </div>
            </div>
          </div>

          <div
            className="pointer-events-none absolute inset-y-0 end-0 hidden w-[52%] overflow-hidden lg:block"
            aria-hidden="true"
          >
            <Image
              src="/assets/illustrations/storvia/file-manager-light.png"
              alt=""
              fill
              sizes="(min-width: 1024px) 52vw"
              className="object-cover object-center dark:hidden"
            />
            <Image
              src="/assets/illustrations/storvia/file-manager-dark.png"
              alt=""
              fill
              sizes="(min-width: 1024px) 52vw"
              className="hidden object-cover object-center dark:block"
            />
          </div>
        </div>

        <div className="flex h-10 items-end gap-5 border-t border-workspace-content-border px-4 sm:px-5 lg:px-6">
          <button
            type="button"
            onClick={() => onSectionChange("files")}
            className="relative h-full text-[11.5px] font-semibold"
          >
            <span
              className={
                section !== "file-settings"
                  ? "text-brand-blue"
                  : "text-muted-foreground"
              }
            >
              {copy.files}
            </span>
            {section !== "file-settings" ? (
              <span className="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-brand-blue" />
            ) : null}
          </button>
          {hasFileWorkspaceAccess ? (
            <button
              type="button"
              onClick={() => onSectionChange("file-settings")}
              className="relative h-full text-[11.5px] font-semibold"
            >
              <span
                className={
                  section === "file-settings"
                    ? "text-brand-blue"
                    : "text-muted-foreground"
                }
              >
                {copy.settings}
              </span>
              {section === "file-settings" ? (
                <span className="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-brand-blue" />
              ) : null}
            </button>
          ) : null}
        </div>
      </section>

      {hasFileWorkspaceAccess &&
      section !== "file-settings" &&
      (activeSpace || navigationGroup || personalSpace || departmentSpaces.length) ? (
        <FileSpaceSwitcher
          copy={copy}
          locale={locale}
          personalSpace={personalSpace}
          departmentSpaces={departmentSpaces}
          activeSpace={activeSpace}
          activeDepartmentNavigationId={navigationGroup?.key ?? null}
          onSpaceChange={switchFileSpace}
          onDepartmentNavigationChange={openDepartmentNavigation}
        />
      ) : null}

      {!hasFileWorkspaceAccess ? (
        <NoWorkspaceEntitlement copy={copy} />
      ) : section === "file-settings" ? (
        <PendingSection
          icon="setting-2"
          title={copy.fileManagerSettings}
          description={copy.sectionPendingDescription}
        />
      ) : loading ? (
        <StatusCard icon="loading" title={copy.loadingFiles} spinning />
      ) : spaceError || invalidRequestedDestination ? (
        <StatusCard
          icon="information-2"
          title={copy.couldNotLoadFiles}
          action={
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => setReloadKey((current) => current + 1)}
            >
              <KeenIcon name="arrows-circle" className="text-[16px]" />
              {copy.retry}
            </Button>
          }
        />
      ) : navigationGroup ? (
        <DepartmentNavigationContainer
          copy={copy}
          group={navigationGroup}
          onSpaceChange={switchFileSpace}
        />
      ) : activeSpace === null ? (
        <PendingSection
          icon="folder"
          title={copy.personalSpaceUnavailable}
          description={copy.personalSpaceUnavailableDescription}
        />
      ) : section === "favorites" || section === "trash" ? (
        <SpecialNodeBrowser
          key={`${activeSpace.id}:${section}:${activeSpaceCapabilityKey}`}
          kind={section}
          fileSpace={activeSpace}
          copy={copy}
          locale={locale}
          query={query}
          onFileSpaceUnavailable={refreshFileSpaceScope}
          onQuotaChanged={(quota) => {
            updateFileSpaceQuota(activeSpace.id, quota);
            onFileSpaceContextChange({ ...activeSpace, quota });
          }}
          onOpenFolder={(nodeId) => {
            onBreadcrumbsChange([]);
            onRouteChange(activeSpace.id, nodeId);
          }}
        />
      ) : (
        <DriveBrowser
          key={`${activeSpace.id}:${activeSpaceCapabilityKey}`}
          copy={copy}
          locale={locale}
          fileSpace={activeSpace}
          query={query}
          onFileSpaceUnavailable={refreshFileSpaceScope}
          parentId={parentId}
          onParentChange={(nextParentId) =>
            onRouteChange(activeSpace.id, nextParentId)
          }
          onBreadcrumbsChange={onBreadcrumbsChange}
          onDirectoryFoldersChange={onDirectoryFoldersChange}
        />
      )}
    </section>
  );
}

function DepartmentNavigationContainer({
  copy,
  group,
  onSpaceChange,
}: {
  copy: WorkspaceCopy;
  group: DepartmentSpaceGroup;
  onSpaceChange: (space: FileManagerFileSpace) => void;
}) {
  return (
    <section className="rounded-xl border border-workspace-content-border bg-card p-4 shadow-xs sm:p-5">
      <div className="flex items-start gap-3 rounded-lg border border-brand-blue/15 bg-brand-blue/5 px-4 py-3">
        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-blue/10 text-brand-blue">
          <KeenIcon name="element-11" className="text-[18px]" />
        </span>
        <div className="min-w-0">
          <h2 className="text-sm font-bold">{group.name}</h2>
          <p className="mt-1 text-xs leading-5 text-muted-foreground">
            {copy.navigationOnlyDepartment}
          </p>
        </div>
      </div>

      <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {group.sections.map((space) => (
          <button
            key={space.id}
            type="button"
            onClick={() => onSpaceChange(space)}
            className="flex min-h-20 items-center gap-3 rounded-xl border border-workspace-content-border bg-background px-4 text-start outline-none transition-colors hover:bg-accent/50 focus-visible:ring-2 focus-visible:ring-ring/30"
          >
            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-cyan/10 text-brand-cyan">
              <KeenIcon name="folder" className="text-[18px]" />
            </span>
            <span className="min-w-0 flex-1">
              <span className="block truncate text-xs font-bold">
                {departmentLeafLabel(space)}
              </span>
              <span className="mt-1 block text-[10.5px] text-muted-foreground">
                {copy.chooseAllowedSection}
              </span>
            </span>
            <KeenIcon
              name="right"
              className="text-[13px] text-muted-foreground rtl:rotate-180"
            />
          </button>
        ))}
      </div>
    </section>
  );
}

function NoWorkspaceEntitlement({ copy }: { copy: WorkspaceCopy }) {
  return (
    <section
      role="alert"
      className="flex min-h-[320px] items-center justify-center rounded-xl border border-dashed border-amber-500/30 bg-amber-500/5 p-8 text-center"
    >
      <div className="max-w-lg">
        <div className="mx-auto flex size-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-700 dark:text-amber-300">
          <KeenIcon name="information-2" className="text-[22px]" />
        </div>
        <h2 className="mt-4 text-base font-bold">{copy.noFileAccessTitle}</h2>
        <p className="mt-2 text-sm leading-6 text-muted-foreground">
          {copy.noFileAccessDescription}
        </p>
      </div>
    </section>
  );
}

function PendingSection({
  icon,
  title,
  description,
}: {
  icon: string;
  title: string;
  description: string;
}) {
  return (
    <section className="flex min-h-[360px] items-center justify-center rounded-xl border border-dashed border-workspace-content-border bg-card/60 p-8 text-center">
      <div className="max-w-md">
        <div className="mx-auto flex size-12 items-center justify-center rounded-xl bg-brand-blue/10 text-brand-blue">
          <KeenIcon name={icon} variant="outline" className="text-[21px]" />
        </div>
        <h2 className="mt-4 text-base font-bold">{title}</h2>
        <p className="mt-2 text-sm leading-6 text-muted-foreground">
          {description}
        </p>
      </div>
    </section>
  );
}

function StatusCard({
  icon,
  title,
  spinning = false,
  action,
}: {
  icon: string;
  title: string;
  spinning?: boolean;
  action?: React.ReactNode;
}) {
  return (
    <section className="flex min-h-[360px] items-center justify-center rounded-xl border border-workspace-content-border bg-card p-8 text-center shadow-xs">
      <div>
        <div className="mx-auto flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
          <KeenIcon
            name={icon}
            className={`text-[21px] ${spinning ? "animate-spin" : ""}`}
          />
        </div>
        <h2 className="mt-4 text-sm font-semibold">{title}</h2>
        {action ? <div className="mt-4">{action}</div> : null}
      </div>
    </section>
  );
}
