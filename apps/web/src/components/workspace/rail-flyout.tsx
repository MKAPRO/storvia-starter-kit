"use client";

import * as React from "react";
import { KeenIcon } from "@/components/ui/keen-icon";
import type { StorviaLocale } from "@/lib/i18n";
import { cn } from "@/lib/utils";

type FlyoutItem = {
  label: string;
  icon: string;
  section?: string;
  disabled?: boolean;
};

type FlyoutGroup = {
  label: string;
  items: FlyoutItem[];
};

type RailFlyoutProps = {
  locale: StorviaLocale;
  section: string;
  activeSection: string;
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
  onSelect: (section: string) => void;
  onClose: () => void;
};

const TEXT = {
  en: {
    navigation: "Navigation",
    closeMenu: "Close menu",
    workspace: "Workspace",
    directory: "Directory",
    management: "Management",
    preferences: "Preferences",
    dashboard: "Dashboard",
    files: "Files",
    myFiles: "My Files",
    companyDrive: "Company Drive",
    recent: "Recent",
    favorites: "Favorites",
    trash: "Trash",
    projects: "Projects",
    design: "Design",
    finance: "Finance",
    archive: "Archive",
    administration: "Administration",
    adminDashboard: "Admin dashboard",
    users: "Users",
    departments: "Departments",
    roles: "Roles",
    permissions: "Permissions",
    storageQuotas: "Storage quotas",
    fileTypes: "File types",
    security: "Security",
    settings: "Settings",
    general: "General",
    appearance: "Appearance",
    localization: "Localization",
    storage: "Storage",
    comingSoon: "Foundation only",
  },
  ar: {
    navigation: "التنقل",
    closeMenu: "إغلاق القائمة",
    workspace: "مساحة العمل",
    directory: "الدليل",
    management: "الإدارة",
    preferences: "التفضيلات",
    dashboard: "لوحة التحكم",
    files: "الملفات",
    myFiles: "ملفاتي",
    companyDrive: "ملفات الشركة",
    recent: "الأخيرة",
    favorites: "المفضلة",
    trash: "سلة المحذوفات",
    projects: "المشاريع",
    design: "التصميم",
    finance: "المالية",
    archive: "الأرشيف",
    administration: "الإدارة",
    adminDashboard: "لوحة تحكم الإدارة",
    users: "المستخدمون",
    departments: "الإدارات",
    roles: "الأدوار",
    permissions: "الصلاحيات",
    storageQuotas: "حصص التخزين",
    fileTypes: "أنواع الملفات",
    security: "الأمان",
    settings: "الإعدادات",
    general: "عام",
    appearance: "المظهر",
    localization: "اللغة والمنطقة",
    storage: "التخزين",
    comingSoon: "أساس الواجهة فقط",
  },
} as const;

function buildGroups(
  locale: StorviaLocale,
  section: string,
  personalSpaceEnabled: boolean,
  hasOrganizationalFileAccess: boolean,
  showAdministration: boolean,
  canViewAdminDashboard: boolean,
  canViewUsers: boolean,
  canViewDepartments: boolean,
  canViewRoles: boolean,
  canViewPermissions: boolean,
  canManageStorageQuotas: boolean,
  canManageFileTypes: boolean,
): { title: string; groups: FlyoutGroup[] } {
  const t = TEXT[locale];

  if (["files", "recent", "favorites", "trash"].includes(section)) {
    return {
      title: t.files,
      groups: [
        {
          label: t.workspace,
          items: [
            ...(personalSpaceEnabled
              ? [{ label: t.myFiles, icon: "folder", section: "files" }]
              : hasOrganizationalFileAccess
                ? [{ label: t.companyDrive, icon: "element-11", section: "files" }]
                : []),
            ...(personalSpaceEnabled || hasOrganizationalFileAccess
              ? [
                  { label: t.favorites, icon: "star", section: "favorites" },
                  { label: t.trash, icon: "trash", section: "trash" },
                ]
              : []),
          ],
        },
        ...(personalSpaceEnabled || hasOrganizationalFileAccess
          ? [
              {
                label: t.directory,
                items: [
                  { label: t.projects, icon: "folder", disabled: true },
                  { label: t.design, icon: "folder", disabled: true },
                  { label: t.finance, icon: "folder", disabled: true },
                  { label: t.archive, icon: "folder", disabled: true },
                ],
              },
            ]
          : []),
      ],
    };
  }

  if (section === "administration") {
    return {
      title: t.administration,
      groups: [
        {
          label: t.management,
          items: [
            ...(canViewAdminDashboard
              ? [{ label: t.adminDashboard, icon: "element-11", section: "admin-dashboard" }]
              : []),
            ...(canViewUsers
              ? [{ label: t.users, icon: "people", section: "users" }]
              : []),
            ...(canViewDepartments
              ? [{ label: t.departments, icon: "element-11", section: "departments" }]
              : []),
            ...(canViewRoles
              ? [{ label: t.roles, icon: "people", section: "roles" }]
              : []),
            ...(canViewPermissions
              ? [{ label: t.permissions, icon: "shield-tick", section: "permissions" }]
              : []),
            ...(canManageStorageQuotas
              ? [{ label: t.storageQuotas, icon: "folder", section: "storage-quotas" }]
              : []),
            ...(canManageFileTypes
              ? [{ label: t.fileTypes, icon: "document", section: "file-types" }]
              : []),
            { label: t.security, icon: "shield-tick", disabled: true },
          ],
        },
      ],
    };
  }

  if (section === "settings") {
    return {
      title: t.settings,
      groups: [
        {
          label: t.preferences,
          items: [
            { label: t.general, icon: "setting-2", disabled: true },
            { label: t.appearance, icon: "setting-2", disabled: true },
            { label: t.localization, icon: "setting-2", disabled: true },
            { label: t.storage, icon: "folder", disabled: true },
          ],
        },
      ],
    };
  }

  const dashboardItems: FlyoutItem[] = [
    { label: t.dashboard, icon: "element-11", section: "dashboard" },
  ];

  if (personalSpaceEnabled || hasOrganizationalFileAccess) {
    dashboardItems.push({
      label: personalSpaceEnabled ? t.myFiles : t.companyDrive,
      icon: "folder",
      section: "files",
    });
  }

  if (showAdministration) {
    dashboardItems.push({ label: t.administration, icon: "people", section: "administration" });
  }

  return {
    title: t.navigation,
    groups: [{ label: t.workspace, items: dashboardItems }],
  };
}

export function RailFlyout({
  locale,
  section,
  activeSection,
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
  onSelect,
  onClose,
}: RailFlyoutProps) {
  const t = TEXT[locale];
  const menu = buildGroups(
    locale,
    section,
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
  );

  return (
    <div
      role="dialog"
      aria-label={menu.title}
      className={cn(
        "absolute start-[calc(100%-4px)] top-[84px] z-[70] w-[262px] overflow-hidden rounded-xl border border-border/80 bg-popover/98 text-popover-foreground shadow-[0_18px_48px_-18px_rgba(15,23,42,0.35)] backdrop-blur-sm",
        "dark:shadow-[0_22px_56px_-20px_rgba(0,0,0,0.7)]",
      )}
    >
      <div className="flex items-center justify-between border-b border-border/70 px-4 py-3">
        <div>
          <p className="text-[13px] font-bold tracking-tight">{menu.title}</p>
          <p className="mt-0.5 text-[10px] text-muted-foreground">{t.navigation}</p>
        </div>
        <button
          type="button"
          onClick={onClose}
          aria-label={t.closeMenu}
          className="flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
        >
          <KeenIcon name="cross" className="text-[15px]" />
        </button>
      </div>

      <div className="max-h-[min(68vh,620px)] overflow-y-auto px-2 py-2.5">
        {menu.groups.map((group, groupIndex) => (
          <div
            key={group.label}
            className={cn(groupIndex > 0 && "mt-2 border-t border-border/65 pt-2")}
          >
            <div className="flex h-8 items-center gap-2 px-2.5 text-[10px] font-bold uppercase tracking-[0.08em] text-muted-foreground">
              <span className="size-1 rounded-full bg-primary" />
              <span>{group.label}</span>
            </div>

            <div className="space-y-0.5">
              {group.items.map((item) => (
                <button
                  key={`${group.label}-${item.label}`}
                  type="button"
                  disabled={item.disabled}
                  onClick={() => {
                    if (!item.section) return;
                    onSelect(item.section);
                    onClose();
                  }}
                  className={cn(
                    "group flex h-9 w-full items-center gap-2.5 rounded-lg px-2.5 text-start text-[12px] font-medium outline-none transition-colors",
                    item.section === activeSection
                      ? "bg-primary/10 text-primary"
                      : "text-foreground/78 hover:bg-accent hover:text-accent-foreground",
                    item.disabled && "cursor-default text-muted-foreground/70 hover:bg-transparent",
                  )}
                >
                  <KeenIcon
                    name={item.icon}
                    variant={item.section === activeSection ? "solid" : "outline"}
                    className="shrink-0 text-[16px]"
                  />
                  <span className="min-w-0 flex-1 truncate">{item.label}</span>
                  {item.disabled ? (
                    <span className="text-[9px] font-normal text-muted-foreground/70">
                      {t.comingSoon}
                    </span>
                  ) : (
                    <KeenIcon
                      name="right"
                      className="text-[12px] text-muted-foreground transition-transform rtl:rotate-180"
                    />
                  )}
                </button>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
