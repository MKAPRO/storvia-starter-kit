"use client";

import * as React from "react";

import { formatStorageBytes, quotaProgressPercentage } from "@/components/file-manager/storage-quota-summary";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { KeenIcon } from "@/components/ui/keen-icon";
import { LoadingState } from "@/components/ui/loading-state";
import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import {
  getUserDashboard,
  type UserDashboard,
  type UserDashboardRecentFile,
} from "@/lib/api/dashboard-client";
import { formatDateTime, formatNumber } from "@/lib/i18n/formatters";
import type { StorviaLocale } from "@/lib/i18n";
import { cn } from "@/lib/utils";

type UserDashboardProps = {
  copy: WorkspaceCopy;
  locale: StorviaLocale;
  personalSpaceEnabled: boolean;
  hasOrganizationalFileAccess: boolean;
  onSectionChange: (section: string) => void;
};

export function UserDashboard({
  copy,
  locale,
  personalSpaceEnabled,
  hasOrganizationalFileAccess,
  onSectionChange,
}: UserDashboardProps) {
  const [dashboard, setDashboard] = React.useState<UserDashboard | null>(null);
  const [loading, setLoading] = React.useState(true);
  const [failed, setFailed] = React.useState(false);
  const [reloadKey, setReloadKey] = React.useState(0);
  const hasFileWorkspaceAccess =
    personalSpaceEnabled || hasOrganizationalFileAccess;

  const loadDashboard = React.useCallback(async (signal: AbortSignal) => {
    setLoading(true);
    setFailed(false);

    try {
      const result = await getUserDashboard(signal);

      if (!signal.aborted) {
        setDashboard(result);
      }
    } catch {
      if (!signal.aborted) {
        setFailed(true);
      }
    } finally {
      if (!signal.aborted) {
        setLoading(false);
      }
    }
  }, []);

  React.useEffect(() => {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => {
      if (!hasFileWorkspaceAccess) {
        setDashboard(null);
        setFailed(false);
        setLoading(false);
        return;
      }

      void loadDashboard(controller.signal);
    }, 0);

    return () => {
      window.clearTimeout(timeout);
      controller.abort();
    };
  }, [hasFileWorkspaceAccess, loadDashboard, reloadKey]);

  if (!hasFileWorkspaceAccess && !loading) {
    return (
      <main className="min-w-0 flex-1 overflow-y-auto bg-workspace-background p-4 sm:p-5 lg:p-6">
        <div className="mx-auto max-w-[1320px] space-y-5">
          <div className="rounded-xl border border-workspace-content-border bg-card p-5 shadow-xs sm:p-6">
            <div role="alert" className="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4">
              <div className="flex items-start gap-3">
                <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-700 dark:text-amber-300">
                  <KeenIcon name="information-2" className="text-[18px]" />
                </span>
                <div className="min-w-0">
                  <h1 className="text-sm font-bold text-foreground">
                    {copy.noFileAccessTitle}
                  </h1>
                  <p className="mt-1 text-xs leading-5 text-muted-foreground">
                    {copy.noFileAccessDescription}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    );
  }

  if (loading && dashboard === null) {
    return (
      <main className="min-w-0 flex-1 overflow-y-auto bg-workspace-background p-4 sm:p-5 lg:p-6">
        <div className="mx-auto max-w-[1320px] rounded-xl border border-workspace-content-border bg-card shadow-xs">
          <LoadingState
            size="lg"
            label={copy.loadingDashboard}
            description={copy.dashboardDescription}
          />
        </div>
      </main>
    );
  }

  if ((failed && dashboard === null) || dashboard === null) {
    return (
      <main className="min-w-0 flex-1 overflow-y-auto bg-workspace-background p-4 sm:p-5 lg:p-6">
        <div className="mx-auto max-w-[1320px] rounded-xl border border-workspace-content-border bg-card shadow-xs">
          <ErrorState
            title={copy.dashboardCouldNotLoad}
            description={copy.dashboardCouldNotLoadDescription}
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
      </main>
    );
  }

  const quota = dashboard.personal_space?.quota ?? null;
  const quotaProgress = quota === null ? 0 : quotaProgressPercentage(quota);
  const primaryFileLabel = personalSpaceEnabled ? copy.myFiles : copy.companyDrive;
  const summaryCards = [
    {
      key: "files",
      label: copy.files,
      value: dashboard.summary.files_count,
      icon: "document",
      target: "files",
    },
    {
      key: "folders",
      label: copy.folders,
      value: dashboard.summary.folders_count,
      icon: "folder",
      target: "files",
    },
    {
      key: "favorites",
      label: copy.favorites,
      value: dashboard.summary.favorites_count,
      icon: "star",
      target: "favorites",
    },
    {
      key: "departments",
      label: copy.departments,
      value: dashboard.summary.assigned_departments_count,
      icon: "people",
      target: "files",
    },
  ] as const;

  return (
    <main className="min-w-0 flex-1 overflow-y-auto bg-workspace-background p-4 sm:p-5 lg:p-6">
      <div className="mx-auto max-w-[1320px] space-y-5">
        <section className="overflow-hidden rounded-xl border border-workspace-content-border bg-card shadow-xs">
          <div className="flex flex-col gap-4 px-4 py-5 sm:px-5 lg:flex-row lg:items-center lg:justify-between lg:px-6">
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-blue/10 text-brand-blue">
                  <KeenIcon name="element-11" className="text-[18px]" />
                </span>
                <div>
                  <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-brand-blue">
                    STORVIA
                  </p>
                  <h1 className="text-lg font-bold tracking-tight">
                    {copy.dashboard}
                  </h1>
                </div>
              </div>
              <p className="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground">
                {copy.dashboardDescription}
              </p>
            </div>

            <Button
              type="button"
              variant="outline"
              onClick={() => onSectionChange("files")}
            >
              <KeenIcon name="folder" className="text-[15px]" />
              {primaryFileLabel}
            </Button>
          </div>
        </section>

        <section
          className={cn(
            "grid gap-5",
            quota !== null &&
              "xl:grid-cols-[minmax(0,1.5fr)_minmax(320px,0.85fr)]",
          )}
        >
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            {summaryCards.map((card) => (
              <button
                key={card.key}
                type="button"
                onClick={() => onSectionChange(card.target)}
                className="group rounded-xl border border-workspace-content-border bg-card p-4 text-start shadow-xs outline-none transition-[border-color,background-color,box-shadow,transform] hover:-translate-y-0.5 hover:border-ring/35 hover:bg-surface-interactive/35 hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring/30"
              >
                <span className="flex size-9 items-center justify-center rounded-lg bg-muted/70 text-muted-foreground transition-colors group-hover:bg-brand-blue/10 group-hover:text-brand-blue">
                  <KeenIcon name={card.icon} className="text-[17px]" />
                </span>
                <span className="mt-4 block text-2xl font-bold tabular-nums tracking-tight text-foreground">
                  {formatNumber(card.value, locale)}
                </span>
                <span className="mt-1 block text-[11px] font-semibold text-muted-foreground">
                  {card.label}
                </span>
              </button>
            ))}
          </div>

          {quota !== null ? (
            <section className="rounded-xl border border-workspace-content-border bg-card p-4 shadow-xs sm:p-5">
              <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-sm font-bold">{copy.storageQuota}</p>
                <p className="mt-1 text-[11px] leading-5 text-muted-foreground">
                  {copy.dashboardPersonalStorageDescription}
                </p>
              </div>
              <Badge
                variant={quota.is_over_limit ? "destructive" : "secondary"}
              >
                {quota.is_over_limit
                  ? copy.storageOverLimit
                  : quota.is_unlimited
                    ? copy.storageUnlimited
                    : copy.storageAvailable}
              </Badge>
            </div>

            <div className="mt-4 grid grid-cols-3 gap-2 text-[10.5px]">
              <QuotaMetric
                label={copy.storageUsedLabel}
                value={formatStorageBytes(quota.used_bytes, locale)}
              />
              <QuotaMetric
                label={copy.storageLimitLabel}
                value={
                  quota.is_unlimited
                    ? copy.storageUnlimited
                    : formatStorageBytes(quota.limit_bytes ?? 0, locale)
                }
              />
              <QuotaMetric
                label={copy.storageRemainingLabel}
                value={
                  quota.remaining_bytes === null
                    ? copy.storageUnlimited
                    : formatStorageBytes(quota.remaining_bytes, locale)
                }
              />
            </div>

              {!quota.is_unlimited ? (
                <div className="mt-4">
                  <div className="mb-1.5 flex items-center justify-between text-[10px] text-muted-foreground">
                    <span>{copy.storage}</span>
                    <span className="tabular-nums">{quotaProgress}%</span>
                  </div>
                  <div className="h-2 overflow-hidden rounded-full bg-muted">
                    <div
                      className={cn(
                        "h-full rounded-full transition-[width] duration-200",
                        quota.is_over_limit ? "bg-destructive" : "bg-primary",
                      )}
                      style={{ width: `${quotaProgress}%` }}
                    />
                  </div>
                </div>
              ) : null}
            </section>
          ) : null}
        </section>

        <section className="overflow-hidden rounded-xl border border-workspace-content-border bg-card shadow-xs">
          <div className="flex items-center justify-between gap-3 border-b border-workspace-content-border px-4 py-3.5 sm:px-5">
            <div>
              <h2 className="text-sm font-bold">{copy.recentFiles}</h2>
              <p className="mt-0.5 text-[10.5px] text-muted-foreground">
                {copy.dashboardRecentDescription}
              </p>
            </div>
            <Button
              type="button"
              size="sm"
              variant="ghost"
              onClick={() => onSectionChange("files")}
            >
              {copy.viewAll}
              <KeenIcon
                name="arrow-right"
                className="text-[13px] rtl:rotate-180"
              />
            </Button>
          </div>

          {dashboard.recent_files.length === 0 ? (
            <EmptyState
              size="compact"
              icon={<KeenIcon name="document" className="text-[18px]" />}
              title={copy.dashboardNoRecentFiles}
              description={copy.dashboardNoRecentFilesDescription}
              action={
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={() => onSectionChange("files")}
                >
                  <KeenIcon name="folder" className="text-[14px]" />
                  {primaryFileLabel}
                </Button>
              }
            />
          ) : (
            <div className="divide-y divide-workspace-content-border">
              {dashboard.recent_files.map((file) => (
                <RecentFileRow
                  key={file.id}
                  file={file}
                  locale={locale}
                  copy={copy}
                />
              ))}
            </div>
          )}

          {failed && dashboard !== null ? (
            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-warning/20 bg-warning/5 px-4 py-2.5 text-[11px] text-muted-foreground sm:px-5">
              <span>{copy.dashboardRefreshFailed}</span>
              <Button
                type="button"
                size="xs"
                variant="ghost"
                aria-busy={loading}
                onClick={() => setReloadKey((current) => current + 1)}
              >
                <KeenIcon
                  name="arrows-circle"
                  className={cn("text-[13px]", loading && "animate-spin")}
                />
                {copy.retry}
              </Button>
            </div>
          ) : null}
        </section>
      </div>
    </main>
  );
}

function QuotaMetric({ label, value }: { label: string; value: string }) {
  return (
    <div className="min-w-0 rounded-lg bg-muted/45 px-2.5 py-2">
      <span className="block truncate text-muted-foreground">{label}</span>
      <strong className="mt-0.5 block truncate font-semibold text-foreground" dir="auto">
        {value}
      </strong>
    </div>
  );
}

function RecentFileRow({
  file,
  locale,
  copy,
}: {
  file: UserDashboardRecentFile;
  locale: StorviaLocale;
  copy: WorkspaceCopy;
}) {
  const extension = file.file.extension?.trim().toUpperCase() || copy.file;
  const affiliation = resolveAffiliation(file, copy);

  return (
    <div className="grid gap-3 px-4 py-3.5 sm:grid-cols-[minmax(0,1.5fr)_minmax(160px,0.9fr)_auto] sm:items-center sm:px-5">
      <div className="flex min-w-0 items-center gap-3">
        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-muted/45 text-muted-foreground">
          <KeenIcon name="document" className="text-[17px]" />
        </span>
        <div className="min-w-0">
          <div className="flex min-w-0 items-center gap-1.5">
            <p className="truncate text-[12px] font-semibold text-foreground">
              {file.name}
            </p>
            {file.is_favorite ? (
              <KeenIcon
                name="star"
                variant="solid"
                className="shrink-0 text-[12px] text-warning"
              />
            ) : null}
          </div>
          <div className="mt-1 flex flex-wrap items-center gap-1.5 text-[10px] text-muted-foreground">
            <Badge variant="outline" className="h-5 px-1.5 text-[9px]" dir="ltr">
              {extension}
            </Badge>
            <span>{formatStorageBytes(file.file.size, locale)}</span>
          </div>
        </div>
      </div>

      <div className="min-w-0 text-[10.5px] text-muted-foreground">
        <span className="block truncate">{affiliation}</span>
        <span className="mt-0.5 block truncate">
          {file.file_space.type === "department"
            ? copy.departmentScope
            : copy.personalScope}
        </span>
      </div>

      <time
        dateTime={file.updated_at ?? undefined}
        className="text-[10.5px] text-muted-foreground sm:text-end"
      >
        {formatDateTime(file.updated_at, locale)}
      </time>
    </div>
  );
}

function resolveAffiliation(
  file: UserDashboardRecentFile,
  copy: WorkspaceCopy,
): string {
  if (file.file_space.type === "personal") {
    return copy.myFiles;
  }

  if (file.file_space.department_path.length > 0) {
    return file.file_space.department_path.map((segment) => segment.name).join(" \\ ");
  }

  return file.file_space.department_name ?? copy.companyDrive;
}
