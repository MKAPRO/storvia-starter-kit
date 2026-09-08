import type { StorviaLocale } from "@/lib/i18n";
import type { WorkspaceSection } from "@/lib/routing/workspace-routes";

export type StorviaPageTitleKey = WorkspaceSection | "login";

const PAGE_TITLES: Record<StorviaLocale, Record<StorviaPageTitleKey, string>> = {
  en: {
    dashboard: "Dashboard",
    files: "My Files",
    favorites: "Favorites",
    trash: "Trash",
    "file-settings": "File Settings",
    administration: "Administration",
    "admin-dashboard": "Admin Dashboard",
    users: "Users",
    departments: "Departments",
    roles: "Roles",
    permissions: "Permissions",
    "storage-quotas": "Storage Quotas",
    "file-types": "File Types",
    settings: "Settings",
    login: "Sign In",
  },
  ar: {
    dashboard: "لوحة التحكم",
    files: "ملفاتي",
    favorites: "المفضلة",
    trash: "سلة المحذوفات",
    "file-settings": "إعدادات الملفات",
    administration: "الإدارة",
    "admin-dashboard": "لوحة تحكم الإدارة",
    users: "المستخدمون",
    departments: "الإدارات والأقسام",
    roles: "الأدوار",
    permissions: "الصلاحيات",
    "storage-quotas": "حصص التخزين",
    "file-types": "أنواع الملفات",
    settings: "الإعدادات",
    login: "تسجيل الدخول",
  },
};

export function storviaDocumentTitle(
  locale: StorviaLocale,
  key: StorviaPageTitleKey,
): string {
  return `STORVIA - ${PAGE_TITLES[locale][key]}`;
}
