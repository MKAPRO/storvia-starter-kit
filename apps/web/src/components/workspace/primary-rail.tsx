"use client";

import * as React from "react";

import { StorviaMark } from "@/components/brand/storvia-mark";
import { KeenIcon } from "@/components/ui/keen-icon";
import { RailFlyout } from "@/components/workspace/rail-flyout";
import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import type { StorviaLocale } from "@/lib/i18n";
import { cn } from "@/lib/utils";

type RailItem = {
  key: string;
  label: string;
  icon: string;
  kind: "leaf" | "group";
};

type PrimaryRailProps = {
  copy: WorkspaceCopy;
  locale: StorviaLocale;
  activeSection: string;
  onSectionChange: (section: string) => void;
  personalSpaceEnabled: boolean;
  hasOrganizationalFileAccess: boolean;
  showAdministration: boolean;
  canViewAdminDashboard: boolean;
  canViewUsers: boolean;
  canViewDepartments: boolean;
  canViewRoles: boolean;
  canViewPermissions: boolean;
  canManageStorageQuotas: boolean;
  canManageFileTypes: boolean;
  compact?: boolean;
};

const FILE_MANAGER_SECTIONS = new Set([
  "files",
  "favorites",
  "trash",
  "file-settings",
]);

export function PrimaryRail({
  copy,
  locale,
  activeSection,
  onSectionChange,
  personalSpaceEnabled,
  hasOrganizationalFileAccess,
  showAdministration,
  canViewAdminDashboard,
  canViewUsers,
  canViewDepartments,
  canViewRoles,
  canViewPermissions,
  canManageStorageQuotas,
  canManageFileTypes,
  compact = false,
}: PrimaryRailProps) {
  const railRef = React.useRef<HTMLElement>(null);
  const [openFlyout, setOpenFlyout] = React.useState<string | null>(null);
  const hasFileWorkspaceAccess =
    personalSpaceEnabled || hasOrganizationalFileAccess;

  React.useEffect(() => {
    if (compact || !openFlyout) {
      return;
    }

    const closeOnPointerDown = (event: PointerEvent) => {
      if (!railRef.current?.contains(event.target as Node)) {
        setOpenFlyout(null);
      }
    };
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setOpenFlyout(null);
      }
    };

    document.addEventListener("pointerdown", closeOnPointerDown);
    document.addEventListener("keydown", closeOnEscape);

    return () => {
      document.removeEventListener("pointerdown", closeOnPointerDown);
      document.removeEventListener("keydown", closeOnEscape);
    };
  }, [compact, openFlyout]);

  const handleSectionClick = (item: RailItem) => {
    if (compact || item.kind === "leaf") {
      setOpenFlyout(null);
      onSectionChange(item.key);
      return;
    }

    setOpenFlyout((current) => (current === item.key ? null : item.key));
  };

  const primaryItems: RailItem[] = [
    {
      key: "dashboard",
      label: copy.dashboard,
      icon: "element-11",
      kind: "leaf",
    },
    {
      key: "files",
      label: copy.files,
      icon: "folder",
      kind: "group",
    },
  ];

  return (
    <aside
      ref={railRef}
      className={cn(
        "relative z-40 flex shrink-0 flex-col bg-workspace-rail text-workspace-rail-foreground",
        compact ? "w-full" : "w-[68px] border-e border-workspace-rail-border",
      )}
    >
      {!compact ? (
        <div className="flex h-16 items-center justify-center border-b border-workspace-rail-border">
          <div className="flex size-9 items-center justify-center rounded-lg bg-brand-blue text-white shadow-sm ring-1 ring-white/10">
            <StorviaMark className="size-5" />
          </div>
        </div>
      ) : null}

      <nav
        aria-label={copy.files}
        className={cn(
          "flex min-h-0 flex-1 flex-col",
          compact ? "gap-1 px-2 py-2" : "items-center gap-2 px-2 py-4",
        )}
      >
        {primaryItems.map((item) => {
          const active =
            item.key === "files"
              ? FILE_MANAGER_SECTIONS.has(activeSection)
              : activeSection === item.key;
          const hasFlyout = !compact && item.kind === "group";

          return (
            <React.Fragment key={item.key}>
              <button
                type="button"
                title={item.label}
                aria-label={item.label}
                aria-current={item.kind === "leaf" && active ? "page" : undefined}
                aria-haspopup={hasFlyout ? "dialog" : undefined}
                aria-expanded={hasFlyout ? openFlyout === item.key : undefined}
                onClick={() => handleSectionClick(item)}
                className={cn(
                  "group relative flex items-center rounded-lg outline-none transition-[background-color,color,box-shadow] duration-150",
                  "focus-visible:ring-2 focus-visible:ring-brand-cyan/45",
                  compact
                    ? "h-9 w-full gap-3 px-3 text-sm font-medium"
                    : "size-10 justify-center",
                  active
                    ? "bg-white/[0.10] text-white shadow-sm ring-1 ring-white/[0.06]"
                    : "text-workspace-rail-muted hover:bg-white/[0.07] hover:text-white",
                )}
              >
                <KeenIcon
                  name={item.icon}
                  variant={active ? "solid" : "outline"}
                  className={compact ? "text-[17px]" : "text-[19px]"}
                />
                {compact ? <span>{item.label}</span> : null}
              </button>

              {compact && item.key === "files" ? (
                <div className="ms-5 mt-1 space-y-1 border-s border-white/10 ps-2">
                  {hasFileWorkspaceAccess ? (
                    <CompactNavigationItem
                      label={personalSpaceEnabled ? copy.myFiles : copy.companyDrive}
                      icon="folder"
                      active={activeSection === "files"}
                      onClick={() => onSectionChange("files")}
                    />
                  ) : null}
                  {hasFileWorkspaceAccess ? (
                    <>
                      <CompactNavigationItem
                        label={copy.favorites}
                        icon="star"
                        active={activeSection === "favorites"}
                        onClick={() => onSectionChange("favorites")}
                      />
                      <CompactNavigationItem
                        label={copy.trash}
                        icon="trash"
                        active={activeSection === "trash"}
                        onClick={() => onSectionChange("trash")}
                      />
                    </>
                  ) : null}
                </div>
              ) : null}
            </React.Fragment>
          );
        })}

        {showAdministration ? (
          <>
            <div
              className={cn(
                "bg-white/10",
                compact ? "my-2 h-px w-full" : "my-2 h-px w-8",
              )}
            />
            <RailAction
              label={copy.administration}
              icon="people"
              active={
                activeSection === "administration" ||
                activeSection === "admin-dashboard" ||
                activeSection === "users" ||
                activeSection === "departments" ||
                activeSection === "roles" ||
                activeSection === "permissions" ||
                activeSection === "storage-quotas" ||
                activeSection === "file-types"
              }
              compact={compact}
              hasFlyout={!compact}
              expanded={!compact && openFlyout === "administration"}
              onClick={() =>
                handleSectionClick({
                  key: "administration",
                  label: copy.administration,
                  icon: "people",
                  kind: "group",
                })
              }
            />
            {compact ? (
              <div className="ms-5 mt-1 space-y-1 border-s border-white/10 ps-2">
                {canViewAdminDashboard ? (
                  <CompactNavigationItem
                    label={copy.adminDashboard}
                    icon="element-11"
                    active={activeSection === "admin-dashboard"}
                    onClick={() => onSectionChange("admin-dashboard")}
                  />
                ) : null}
                {canViewUsers ? (
                  <CompactNavigationItem
                    label={copy.users}
                    icon="people"
                    active={activeSection === "users"}
                    onClick={() => onSectionChange("users")}
                  />
                ) : null}
                {canViewDepartments ? (
                  <CompactNavigationItem
                    label={copy.departments}
                    icon="element-11"
                    active={activeSection === "departments"}
                    onClick={() => onSectionChange("departments")}
                  />
                ) : null}
                {canViewRoles ? (
                  <CompactNavigationItem
                    label={copy.roles}
                    icon="people"
                    active={activeSection === "roles"}
                    onClick={() => onSectionChange("roles")}
                  />
                ) : null}
                {canViewPermissions ? (
                  <CompactNavigationItem
                    label={copy.permissions}
                    icon="shield-tick"
                    active={activeSection === "permissions"}
                    onClick={() => onSectionChange("permissions")}
                  />
                ) : null}
                {canManageStorageQuotas ? (
                  <CompactNavigationItem
                    label={copy.storageQuotas}
                    icon="folder"
                    active={activeSection === "storage-quotas"}
                    onClick={() => onSectionChange("storage-quotas")}
                  />
                ) : null}
                {canManageFileTypes ? (
                  <CompactNavigationItem
                    label={copy.fileTypes}
                    icon="document"
                    active={activeSection === "file-types"}
                    onClick={() => onSectionChange("file-types")}
                  />
                ) : null}
              </div>
            ) : null}
          </>
        ) : null}
      </nav>

      <div
        className={cn(
          "border-t border-workspace-rail-border",
          compact ? "space-y-1 p-2" : "flex flex-col items-center gap-2 px-2 py-4",
        )}
      >
        <RailAction
          label={copy.settings}
          icon="setting-2"
          active={activeSection === "settings"}
          compact={compact}
          hasFlyout={!compact}
          expanded={!compact && openFlyout === "settings"}
          onClick={() =>
            handleSectionClick({
              key: "settings",
              label: copy.settings,
              icon: "setting-2",
              kind: "group",
            })
          }
        />
        {!compact ? (
          <div className="flex size-9 items-center justify-center rounded-full bg-brand-cyan/12 text-brand-cyan ring-1 ring-brand-cyan/20">
            <KeenIcon name="shield-tick" className="text-[17px]" />
          </div>
        ) : null}
      </div>

      {!compact && openFlyout ? (
        <RailFlyout
          locale={locale}
          section={openFlyout}
          activeSection={activeSection}
          personalSpaceEnabled={personalSpaceEnabled}
          hasOrganizationalFileAccess={hasOrganizationalFileAccess}
          showAdministration={showAdministration}
          canViewAdminDashboard={canViewAdminDashboard}
          canViewUsers={canViewUsers}
          canViewDepartments={canViewDepartments}
          canViewRoles={canViewRoles}
          canViewPermissions={canViewPermissions}
          canManageStorageQuotas={canManageStorageQuotas}
          canManageFileTypes={canManageFileTypes}
          onSelect={onSectionChange}
          onClose={() => setOpenFlyout(null)}
        />
      ) : null}
    </aside>
  );
}

function CompactNavigationItem({
  label,
  icon,
  active,
  onClick,
}: {
  label: string;
  icon: string;
  active: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-current={active ? "page" : undefined}
      className={cn(
        "flex h-8 w-full items-center gap-2 rounded-md px-2.5 text-start text-xs font-medium transition-colors",
        active
          ? "bg-white/[0.10] text-white"
          : "text-workspace-rail-muted hover:bg-white/[0.07] hover:text-white",
      )}
    >
      <KeenIcon
        name={icon}
        variant={active ? "solid" : "outline"}
        className="text-[15px]"
      />
      <span>{label}</span>
    </button>
  );
}

function RailAction({
  label,
  icon,
  active,
  compact,
  hasFlyout,
  expanded,
  onClick,
}: {
  label: string;
  icon: string;
  active: boolean;
  compact: boolean;
  hasFlyout: boolean;
  expanded: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      title={label}
      aria-label={label}
      aria-current={!hasFlyout && active ? "page" : undefined}
      aria-haspopup={hasFlyout ? "dialog" : undefined}
      aria-expanded={hasFlyout ? expanded : undefined}
      onClick={onClick}
      className={cn(
        "flex items-center rounded-lg outline-none transition-colors focus-visible:ring-2 focus-visible:ring-brand-cyan/45",
        compact
          ? "h-9 w-full gap-3 px-3 text-sm font-medium"
          : "size-10 justify-center",
        active
          ? "bg-white/[0.10] text-white ring-1 ring-white/[0.06]"
          : "text-workspace-rail-muted hover:bg-white/[0.07] hover:text-white",
      )}
    >
      <KeenIcon
        name={icon}
        variant={active ? "solid" : "outline"}
        className={compact ? "text-[17px]" : "text-[19px]"}
      />
      {compact ? <span>{label}</span> : null}
    </button>
  );
}
