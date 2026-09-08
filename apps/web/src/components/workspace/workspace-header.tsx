"use client";

import Image from "next/image";
import { useTheme } from "@teispace/next-themes";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { KeenIcon } from "@/components/ui/keen-icon";
import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import type { AuthUser } from "@/lib/auth/auth-types";
import type { StorviaLocale } from "@/lib/i18n";

type WorkspaceHeaderProps = {
  copy: WorkspaceCopy;
  locale: StorviaLocale;
  user: AuthUser;
  activeSection: string;
  query: string;
  onQueryChange: (value: string) => void;
  onLocaleChange: (locale: StorviaLocale) => void;
  onOpenNavigation: () => void;
  onLogout: () => void;
};

const FILE_MANAGER_SECTIONS = new Set([
  "files",
  "favorites",
  "trash",
  "file-settings",
]);

export function WorkspaceHeader({
  copy,
  locale,
  user,
  activeSection,
  query,
  onQueryChange,
  onLocaleChange,
  onOpenNavigation,
  onLogout,
}: WorkspaceHeaderProps) {
  const { resolvedTheme, setTheme } = useTheme();
  const isDark = resolvedTheme === "dark";
  const pageTitle = getSectionTitle(copy, activeSection);
  const inFileManager = FILE_MANAGER_SECTIONS.has(activeSection);

  return (
    <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-workspace-header-border bg-workspace-header/95 px-3 backdrop-blur-sm sm:px-4 lg:px-5">
      <Button
        type="button"
        size="icon-sm"
        variant="ghost"
        className="lg:hidden"
        aria-label={copy.files}
        onClick={onOpenNavigation}
      >
        <KeenIcon name="menu" className="text-[19px]" />
      </Button>

      <div className="hidden min-w-[250px] lg:block xl:min-w-[290px]">
        <p className="truncate text-[14px] font-bold tracking-tight text-foreground">
          {inFileManager ? copy.fileManager : pageTitle}
        </p>
        <nav
          className="mt-0.5 flex items-center gap-1 text-[10px] font-medium text-muted-foreground"
          aria-label={copy.breadcrumb}
        >
          <span>{copy.home}</span>
          <span aria-hidden="true">•</span>
          <span className="truncate">{pageTitle}</span>
        </nav>
      </div>

      <div className="relative min-w-0 flex-1 sm:max-w-[300px] xl:max-w-[360px]">
        <KeenIcon
          name="magnifier"
          className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-[17px] text-muted-foreground"
        />
        <Input
          value={query}
          onChange={(event) => onQueryChange(event.target.value)}
          placeholder={copy.searchPlaceholder}
          aria-label={copy.searchPlaceholder}
          className="h-9 border-transparent bg-workspace-search ps-9 pe-3 text-[13px] shadow-none hover:border-border focus-visible:border-ring"
        />
      </div>

      <div className="ms-auto flex items-center gap-1 sm:gap-1.5">
        <DropdownMenu>
          <DropdownMenuTrigger
            render={
              <Button
                type="button"
                size="icon-sm"
                variant="ghost"
                className="overflow-hidden"
                aria-label={locale === "ar" ? copy.arabic : copy.english}
              />
            }
          >
            <LanguageFlag locale={locale} />
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="min-w-44">
            <DropdownMenuGroup>
              <DropdownMenuLabel>{copy.language}</DropdownMenuLabel>
              <DropdownMenuItem
                className="gap-2.5"
                onClick={() => onLocaleChange("en")}
              >
                <LanguageFlag locale="en" />
                <span className="flex-1">{copy.english}</span>
                {locale === "en" ? (
                  <KeenIcon
                    name="check"
                    variant="solid"
                    className="text-[15px] text-brand-blue"
                  />
                ) : null}
              </DropdownMenuItem>
              <DropdownMenuItem
                className="gap-2.5"
                onClick={() => onLocaleChange("ar")}
              >
                <LanguageFlag locale="ar" />
                <span className="flex-1">{copy.arabic}</span>
                {locale === "ar" ? (
                  <KeenIcon
                    name="check"
                    variant="solid"
                    className="text-[15px] text-brand-blue"
                  />
                ) : null}
              </DropdownMenuItem>
            </DropdownMenuGroup>
          </DropdownMenuContent>
        </DropdownMenu>

        <Button
          type="button"
          size="icon-sm"
          variant="ghost"
          title={isDark ? copy.lightTheme : copy.darkTheme}
          aria-label={isDark ? copy.lightTheme : copy.darkTheme}
          onClick={() => setTheme(isDark ? "light" : "dark")}
        >
          <KeenIcon
            name={isDark ? "sun" : "moon"}
            className="text-[18px]"
          />
        </Button>

        <Button
          type="button"
          size="icon-sm"
          variant="ghost"
          className="relative hidden sm:inline-flex"
          title={copy.notifications}
          aria-label={copy.notifications}
        >
          <KeenIcon name="notification-status" className="text-[18px]" />
          <span className="absolute end-2 top-2 size-1.5 rounded-full bg-brand-cyan ring-2 ring-workspace-header" />
        </Button>

        <div className="mx-1 hidden h-6 w-px bg-border sm:block" />

        <DropdownMenu>
          <DropdownMenuTrigger
            render={
              <Button
                type="button"
                variant="ghost"
                className="h-9 gap-1.5 px-1 sm:px-1.5"
              />
            }
          >
            <span className="flex size-7 items-center justify-center rounded-md bg-brand-navy text-[11px] font-bold text-white dark:bg-brand-blue/20 dark:text-brand-blue">
              {initials(user.name)}
            </span>
            <span className="hidden max-w-32 text-start xl:block">
              <span className="block truncate text-xs font-semibold leading-4">
                {user.name}
              </span>
              <span className="block truncate text-[10px] font-normal leading-4 text-muted-foreground">
                {user.roles[0] ?? copy.profile}
              </span>
            </span>
            <KeenIcon
              name="down"
              className="hidden text-[11px] text-muted-foreground xl:inline-flex"
            />
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-56">
            <DropdownMenuGroup>
              <DropdownMenuLabel className="normal-case">
                <span className="block truncate text-sm font-semibold text-foreground">
                  {user.name}
                </span>
                <span
                  className="block truncate text-[11px] font-normal text-muted-foreground"
                  dir="ltr"
                >
                  {user.email}
                </span>
              </DropdownMenuLabel>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem>
              <KeenIcon name="profile-circle" className="text-[17px]" />
              {copy.profile}
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem variant="destructive" onClick={onLogout}>
              <KeenIcon
                name="exit-right"
                className="text-[17px] rtl:rotate-180"
              />
              {copy.signOut}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  );
}

function getSectionTitle(copy: WorkspaceCopy, section: string): string {
  if (section === "dashboard") return copy.dashboard;
  if (section === "favorites") return copy.favorites;
  if (section === "trash") return copy.trash;
  if (section === "file-settings") return copy.fileManagerSettings;
  if (section === "administration") return copy.administration;
  if (section === "admin-dashboard") return copy.adminDashboard;
  if (section === "users") return copy.users;
  if (section === "departments") return copy.departments;
  if (section === "roles") return copy.roles;
  if (section === "permissions") return copy.permissions;
  if (section === "storage-quotas") return copy.storageQuotas;
  if (section === "file-types") return copy.fileTypes;
  if (section === "settings") return copy.settings;
  return copy.myFiles;
}

function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);

  if (parts.length === 0) {
    return "S";
  }

  return parts
    .slice(0, 2)
    .map((part) => part[0])
    .join("")
    .toUpperCase();
}

function LanguageFlag({ locale }: { locale: StorviaLocale }) {
  const isArabic = locale === "ar";

  return (
    <span className="relative block h-[16px] w-[22px] shrink-0 overflow-hidden rounded-[3px] ring-1 ring-black/10 dark:ring-white/15">
      <Image
        src={
          isArabic
            ? "/assets/flags/yemen.svg"
            : "/assets/flags/united-states.svg"
        }
        alt=""
        fill
        sizes="22px"
        className="object-cover"
        unoptimized
      />
    </span>
  );
}
