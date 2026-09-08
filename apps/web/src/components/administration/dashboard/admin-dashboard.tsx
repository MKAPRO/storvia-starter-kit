"use client";

import * as React from "react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ErrorState } from "@/components/ui/error-state";
import { KeenIcon } from "@/components/ui/keen-icon";
import { LoadingState } from "@/components/ui/loading-state";
import {
  getAdminDashboard,
  type AdminDashboard as AdminDashboardData,
} from "@/lib/api/administration-dashboard-client";
import { formatNumber, intlLocale } from "@/lib/i18n/formatters";
import type { StorviaLocale } from "@/lib/i18n";
import { cn } from "@/lib/utils";

type AdminDashboardProps = {
  locale: StorviaLocale;
  canViewUsers: boolean;
  canViewDepartments: boolean;
  canManageStorageQuotas: boolean;
  onSectionChange: (section: string) => void;
};

const TEXT = {
  en: {
    title: "Admin dashboard",
    eyebrow: "Administration",
    description:
      "A system-wide summary of users, departments, file spaces, storage, and content.",
    loading: "Loading administrative dashboard…",
    loadDescription: "Reading the latest system-wide metrics from STORVIA.",
    couldNotLoad: "Could not load the admin dashboard",
    couldNotLoadDescription:
      "The administrative summary could not be loaded. Try again.",
    refreshFailed: "The latest refresh failed. Showing the last loaded summary.",
    retry: "Try again",
    users: "Users",
    activeUsers: "Active",
    inactiveUsers: "Inactive",
    departments: "Departments",
    activeDepartments: "Active",
    inactiveDepartments: "Inactive",
    fileSpaces: "File spaces",
    personalSpaces: "Personal",
    departmentSpaces: "Department",
    activeFiles: "Active files",
    activeFolders: "Active folders",
    trashRoots: "Trash roots",
    storage: "Storage",
    storageDescription:
      "Exact aggregate usage across all file spaces. Unlimited spaces are counted separately from finite limits.",
    used: "Used",
    finiteLimits: "Finite limits",
    limitedSpaces: "Limited spaces",
    unlimitedSpaces: "Unlimited spaces",
    overLimitSpaces: "Over limit",
    manageUsers: "Manage users",
    manageDepartments: "Manage departments",
    manageStorage: "Manage storage quotas",
    systemAuthority: "system.manage",
  },
  ar: {
    title: "لوحة تحكم الإدارة",
    eyebrow: "الإدارة",
    description:
      "ملخص على مستوى النظام للمستخدمين والإدارات ومساحات الملفات والتخزين والمحتوى.",
    loading: "جاري تحميل لوحة تحكم الإدارة…",
    loadDescription: "جاري قراءة أحدث مؤشرات النظام من STORVIA.",
    couldNotLoad: "تعذر تحميل لوحة تحكم الإدارة",
    couldNotLoadDescription:
      "تعذر تحميل الملخص الإداري. حاول مرة أخرى.",
    refreshFailed: "فشل آخر تحديث. يتم عرض آخر ملخص تم تحميله.",
    retry: "إعادة المحاولة",
    users: "المستخدمون",
    activeUsers: "نشط",
    inactiveUsers: "غير نشط",
    departments: "الإدارات",
    activeDepartments: "نشطة",
    inactiveDepartments: "غير نشطة",
    fileSpaces: "مساحات الملفات",
    personalSpaces: "شخصية",
    departmentSpaces: "إدارات",
    activeFiles: "الملفات النشطة",
    activeFolders: "المجلدات النشطة",
    trashRoots: "جذور سلة المحذوفات",
    storage: "التخزين",
    storageDescription:
      "إجمالي استخدام دقيق لكل مساحات الملفات. تُحسب المساحات غير المحدودة منفصلة عن الحدود المحددة.",
    used: "المستخدم",
    finiteLimits: "إجمالي الحدود المحددة",
    limitedSpaces: "مساحات محدودة",
    unlimitedSpaces: "مساحات غير محدودة",
    overLimitSpaces: "متجاوزة للحد",
    manageUsers: "إدارة المستخدمين",
    manageDepartments: "إدارة الإدارات",
    manageStorage: "إدارة حصص التخزين",
    systemAuthority: "system.manage",
  },
} as const;

export function AdminDashboard({
  locale,
  canViewUsers,
  canViewDepartments,
  canManageStorageQuotas,
  onSectionChange,
}: AdminDashboardProps) {
  const [dashboard, setDashboard] = React.useState<AdminDashboardData | null>(
    null,
  );
  const [loading, setLoading] = React.useState(true);
  const [failed, setFailed] = React.useState(false);
  const [reloadKey, setReloadKey] = React.useState(0);
  const copy = TEXT[locale];

  React.useEffect(() => {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => {
      setLoading(true);
      setFailed(false);

      void getAdminDashboard(controller.signal)
        .then((result) => {
          if (!controller.signal.aborted) {
            setDashboard(result);
          }
        })
        .catch(() => {
          if (!controller.signal.aborted) {
            setFailed(true);
          }
        })
        .finally(() => {
          if (!controller.signal.aborted) {
            setLoading(false);
          }
        });
    }, 0);

    return () => {
      window.clearTimeout(timeout);
      controller.abort();
    };
  }, [reloadKey]);

  if (loading && dashboard === null) {
    return (
      <DashboardMain>
        <div className="rounded-xl border border-workspace-content-border bg-card shadow-xs">
          <LoadingState
            size="lg"
            label={copy.loading}
            description={copy.loadDescription}
          />
        </div>
      </DashboardMain>
    );
  }

  if (dashboard === null) {
    return (
      <DashboardMain>
        <div className="rounded-xl border border-workspace-content-border bg-card shadow-xs">
          <ErrorState
            title={copy.couldNotLoad}
            description={copy.couldNotLoadDescription}
            action={
              <Button
                type="button"
                variant="outline"
                onClick={() => setReloadKey((current) => current + 1)}
              >
                <KeenIcon name="arrows-circle" className="text-[15px]" />
                {copy.retry}
              </Button>
            }
          />
        </div>
      </DashboardMain>
    );
  }

  const metricCards = [
    {
      key: "users",
      label: copy.users,
      value: dashboard.users.total_count,
      detail: `${formatNumber(dashboard.users.active_count, locale)} ${copy.activeUsers} · ${formatNumber(dashboard.users.inactive_count, locale)} ${copy.inactiveUsers}`,
      icon: "people",
      target: canViewUsers ? "users" : null,
    },
    {
      key: "departments",
      label: copy.departments,
      value: dashboard.departments.total_count,
      detail: `${formatNumber(dashboard.departments.active_count, locale)} ${copy.activeDepartments} · ${formatNumber(dashboard.departments.inactive_count, locale)} ${copy.inactiveDepartments}`,
      icon: "element-11",
      target: canViewDepartments ? "departments" : null,
    },
    {
      key: "spaces",
      label: copy.fileSpaces,
      value: dashboard.file_spaces.total_count,
      detail: `${formatNumber(dashboard.file_spaces.personal_count, locale)} ${copy.personalSpaces} · ${formatNumber(dashboard.file_spaces.department_count, locale)} ${copy.departmentSpaces}`,
      icon: "folder",
      target: canManageStorageQuotas ? "storage-quotas" : null,
    },
    {
      key: "files",
      label: copy.activeFiles,
      value: dashboard.content.active_files_count,
      detail: copy.activeFiles,
      icon: "document",
      target: null,
    },
    {
      key: "folders",
      label: copy.activeFolders,
      value: dashboard.content.active_folders_count,
      detail: `${formatNumber(dashboard.content.trash_roots_count, locale)} ${copy.trashRoots}`,
      icon: "folder",
      target: null,
    },
  ] as const;

  return (
    <DashboardMain>
      <section className="overflow-hidden rounded-xl border border-workspace-content-border bg-card shadow-xs">
        <div className="flex flex-col gap-4 px-4 py-5 sm:px-5 lg:flex-row lg:items-center lg:justify-between lg:px-6">
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-blue/10 text-brand-blue">
                <KeenIcon name="element-11" className="text-[18px]" />
              </span>
              <div>
                <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-brand-blue">
                  {copy.eyebrow}
                </p>
                <h1 className="text-lg font-bold tracking-tight">{copy.title}</h1>
              </div>
            </div>
            <p className="mt-3 max-w-3xl text-sm leading-6 text-muted-foreground">
              {copy.description}
            </p>
          </div>

          <Badge variant="secondary">
            <KeenIcon name="shield-tick" className="me-1 text-[13px]" />
            {copy.systemAuthority}
          </Badge>
        </div>
      </section>

      {failed ? (
        <div className="flex items-center justify-between gap-3 rounded-lg border border-destructive/20 bg-destructive/5 px-3 py-2 text-xs text-destructive">
          <span>{copy.refreshFailed}</span>
          <Button
            type="button"
            size="sm"
            variant="ghost"
            onClick={() => setReloadKey((current) => current + 1)}
          >
            <KeenIcon name="arrows-circle" className="text-[13px]" />
            {copy.retry}
          </Button>
        </div>
      ) : null}

      <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        {metricCards.map((card) => {
          const cardClass =
            "rounded-xl border border-workspace-content-border bg-card p-4 text-start shadow-xs";
          const content = (
            <>
              <span className="flex size-9 items-center justify-center rounded-lg bg-muted/70 text-muted-foreground transition-colors group-hover:bg-brand-blue/10 group-hover:text-brand-blue">
                <KeenIcon name={card.icon} className="text-[17px]" />
              </span>
              <span className="mt-4 block text-2xl font-bold tabular-nums tracking-tight text-foreground">
                {formatNumber(card.value, locale)}
              </span>
              <span className="mt-1 block text-[11px] font-semibold text-muted-foreground">
                {card.label}
              </span>
              <span className="mt-2 block text-[10px] leading-4 text-muted-foreground/80">
                {card.detail}
              </span>
            </>
          );

          const target = card.target;

          return target ? (
            <button
              key={card.key}
              type="button"
              onClick={() => onSectionChange(target)}
              className={cn(
                "group outline-none transition-[border-color,background-color,box-shadow,transform] hover:-translate-y-0.5 hover:border-ring/35 hover:bg-surface-interactive/35 hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring/30",
                cardClass,
              )}
            >
              {content}
            </button>
          ) : (
            <article key={card.key} className={cardClass}>
              {content}
            </article>
          );
        })}
      </section>

      <section className="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.8fr)]">
        <section className="rounded-xl border border-workspace-content-border bg-card p-4 shadow-xs sm:p-5">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 className="text-sm font-bold">{copy.storage}</h2>
              <p className="mt-1 max-w-2xl text-[11px] leading-5 text-muted-foreground">
                {copy.storageDescription}
              </p>
            </div>
            {canManageStorageQuotas ? (
              <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => onSectionChange("storage-quotas")}
              >
                <KeenIcon name="folder" className="text-[13px]" />
                {copy.manageStorage}
              </Button>
            ) : null}
          </div>

          <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <Metric label={copy.used} value={formatDecimalBytes(dashboard.storage.used_bytes, locale)} />
            <Metric
              label={copy.finiteLimits}
              value={formatDecimalBytes(dashboard.storage.finite_limit_bytes, locale)}
            />
            <Metric
              label={copy.limitedSpaces}
              value={formatNumber(dashboard.storage.limited_space_count, locale)}
            />
            <Metric
              label={copy.unlimitedSpaces}
              value={formatNumber(dashboard.storage.unlimited_space_count, locale)}
            />
            <Metric
              label={copy.overLimitSpaces}
              value={formatNumber(dashboard.storage.over_limit_space_count, locale)}
              danger={dashboard.storage.over_limit_space_count > 0}
            />
          </div>
        </section>

      </section>

      {canViewUsers || canViewDepartments || canManageStorageQuotas ? (
        <section className="grid gap-3 sm:grid-cols-3">
          {canViewUsers ? (
            <QuickAction
              icon="people"
              label={copy.manageUsers}
              onClick={() => onSectionChange("users")}
            />
          ) : null}
          {canViewDepartments ? (
            <QuickAction
              icon="element-11"
              label={copy.manageDepartments}
              onClick={() => onSectionChange("departments")}
            />
          ) : null}
          {canManageStorageQuotas ? (
            <QuickAction
              icon="folder"
              label={copy.manageStorage}
              onClick={() => onSectionChange("storage-quotas")}
            />
          ) : null}
        </section>
      ) : null}
    </DashboardMain>
  );
}

function DashboardMain({ children }: { children: React.ReactNode }) {
  return (
    <main className="min-w-0 flex-1 overflow-y-auto bg-workspace-background p-4 sm:p-5 lg:p-6">
      <div className="mx-auto max-w-[1320px] space-y-5">{children}</div>
    </main>
  );
}

function Metric({
  label,
  value,
  danger = false,
}: {
  label: string;
  value: string;
  danger?: boolean;
}) {
  return (
    <div className="rounded-lg border border-workspace-content-border bg-muted/20 px-3 py-3">
      <p className="text-[10px] font-semibold text-muted-foreground">{label}</p>
      <p
        className={cn(
          "mt-1 text-base font-bold tabular-nums tracking-tight",
          danger ? "text-destructive" : "text-foreground",
        )}
      >
        {value}
      </p>
    </div>
  );
}

function QuickAction({
  icon,
  label,
  onClick,
}: {
  icon: string;
  label: string;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="flex items-center gap-3 rounded-xl border border-workspace-content-border bg-card p-4 text-start shadow-xs outline-none transition-[border-color,background-color,box-shadow] hover:border-ring/35 hover:bg-surface-interactive/35 hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring/30"
    >
      <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-blue/10 text-brand-blue">
        <KeenIcon name={icon} className="text-[16px]" />
      </span>
      <span className="text-sm font-semibold">{label}</span>
    </button>
  );
}

function formatDecimalBytes(value: string, locale: StorviaLocale): string {
  let bytes: bigint;

  try {
    bytes = BigInt(value);
  } catch {
    return "0 B";
  }

  if (bytes <= BigInt(0)) {
    return "0 B";
  }

  const units = ["B", "KB", "MB", "GB", "TB", "PB", "EB"];
  const base = BigInt(1024);
  let divisor = BigInt(1);
  let unitIndex = 0;

  while (unitIndex < units.length - 1 && bytes >= divisor * base) {
    divisor *= base;
    unitIndex++;
  }

  const whole = bytes / divisor;
  const remainder = bytes % divisor;
  const tenths = unitIndex === 0 ? BigInt(0) : (remainder * BigInt(10)) / divisor;
  const formatter = new Intl.NumberFormat(intlLocale(locale));
  const numeric = tenths > BigInt(0) && whole < BigInt(10)
    ? `${formatter.format(whole)}.${tenths.toString()}`
    : formatter.format(whole);

  return `${numeric} ${units[unitIndex]}`;
}
