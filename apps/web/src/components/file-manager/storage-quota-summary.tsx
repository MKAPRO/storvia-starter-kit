import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import type { FileManagerStorageQuota } from "@/lib/api/file-manager-client";
import type { StorviaLocale } from "@/lib/i18n";
import { cn } from "@/lib/utils";

export function StorageQuotaSummary({
  quota,
  locale,
  copy,
  className,
}: {
  quota: FileManagerStorageQuota;
  locale: StorviaLocale;
  copy: WorkspaceCopy;
  className?: string;
}) {
  const percentage = quotaProgressPercentage(quota);

  return (
    <section
      className={cn(
        "border-b border-workspace-content-border bg-background/55 px-4 py-3 sm:px-5",
        className,
      )}
      aria-label={copy.storageQuota}
    >
      <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px]">
            <span className="font-semibold text-foreground">
              {copy.storageQuota}
            </span>
            <QuotaValue
              label={copy.storageUsedLabel}
              value={formatStorageBytes(quota.used_bytes, locale)}
            />
            <QuotaValue
              label={copy.storageLimitLabel}
              value={
                quota.is_unlimited
                  ? copy.storageUnlimited
                  : formatStorageBytes(quota.limit_bytes ?? 0, locale)
              }
            />
            <QuotaValue
              label={copy.storageRemainingLabel}
              value={
                quota.remaining_bytes === null
                  ? copy.storageUnlimited
                  : formatStorageBytes(quota.remaining_bytes, locale)
              }
            />
          </div>

          {!quota.is_unlimited ? (
            <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
              <div
                className={cn(
                  "h-full rounded-full transition-[width] duration-200",
                  quota.is_over_limit ? "bg-destructive" : "bg-primary",
                )}
                style={{ width: `${percentage}%` }}
              />
            </div>
          ) : null}
        </div>

        <span
          className={cn(
            "inline-flex w-fit items-center rounded-full border px-2 py-1 text-[10px] font-semibold",
            quota.is_over_limit
              ? "border-destructive/25 bg-destructive/10 text-destructive"
              : quota.is_unlimited
                ? "border-border bg-muted/40 text-muted-foreground"
                : "border-primary/20 bg-primary/5 text-primary",
          )}
        >
          {quota.is_over_limit
            ? copy.storageOverLimit
            : quota.is_unlimited
              ? copy.storageUnlimited
              : copy.storageAvailable}
        </span>
      </div>
    </section>
  );
}

function QuotaValue({ label, value }: { label: string; value: string }) {
  return (
    <span className="text-muted-foreground">
      {label}: <strong className="font-semibold text-foreground">{value}</strong>
    </span>
  );
}

export function quotaProgressPercentage(quota: FileManagerStorageQuota): number {
  if (quota.limit_bytes === null) {
    return 0;
  }

  if (quota.limit_bytes === 0) {
    return quota.used_bytes > 0 ? 100 : 0;
  }

  return Math.min(100, Math.round((quota.used_bytes / quota.limit_bytes) * 100));
}

export function formatStorageBytes(
  value: number,
  locale: StorviaLocale,
): string {
  if (!Number.isSafeInteger(value) || value <= 0) {
    return "0 B";
  }

  const units = ["B", "KB", "MB", "GB", "TB", "PB"];
  const index = Math.min(
    units.length - 1,
    Math.floor(Math.log(value) / Math.log(1024)),
  );
  const numeric = value / 1024 ** index;

  return `${new Intl.NumberFormat(locale === "ar" ? "ar-YE" : "en-US", {
    maximumFractionDigits: numeric >= 10 || index === 0 ? 0 : 1,
  }).format(numeric)} ${units[index]}`;
}
