"use client";

import * as React from "react";

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogMedia,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { KeenIcon } from "@/components/ui/keen-icon";
import { toast } from "@/components/ui/toast";
import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import { isApiError } from "@/lib/api/api-error";
import {
  getFileManagerSpaceUploadPolicy,
  type FileManagerStorageQuota,
  type FileManagerUploadPolicy,
} from "@/lib/api/file-manager-client";
import { useAuth } from "@/lib/auth";
import { formatStorageBytes } from "@/components/file-manager/storage-quota-summary";
import type { StorviaLocale } from "@/lib/i18n";
import {
  UploadQueueController,
  type UploadQueueAddResult,
  type UploadQueueItem,
} from "@/lib/upload/upload-queue";
import { cn } from "@/lib/utils";

type UploadCenterDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  fileSpaceId: string;
  parentId: string | null;
  quota: FileManagerStorageQuota;
  locale: StorviaLocale;
  copy: WorkspaceCopy;
  onUploadsChanged: () => void;
  onFileSpaceUnavailable: (fileSpaceId: string) => void;
};

type UploadCenterSessionProps = Omit<UploadCenterDialogProps, "open">;

export function UploadCenterDialog(props: UploadCenterDialogProps) {
  if (!props.open) {
    return null;
  }

  return (
    <UploadCenterSession
      key={`${props.fileSpaceId}:${props.parentId ?? "root"}`}
      onOpenChange={props.onOpenChange}
      fileSpaceId={props.fileSpaceId}
      parentId={props.parentId}
      quota={props.quota}
      locale={props.locale}
      copy={props.copy}
      onUploadsChanged={props.onUploadsChanged}
      onFileSpaceUnavailable={props.onFileSpaceUnavailable}
    />
  );
}

function UploadCenterSession({
  onOpenChange,
  fileSpaceId,
  parentId,
  quota,
  locale,
  copy,
  onUploadsChanged,
  onFileSpaceUnavailable,
}: UploadCenterSessionProps) {
  const { refreshSession } = useAuth();
  const [policy, setPolicy] = React.useState<FileManagerUploadPolicy | null>(null);
  const [policyError, setPolicyError] = React.useState(false);

  React.useEffect(() => {
    const controller = new AbortController();

    void getFileManagerSpaceUploadPolicy(fileSpaceId, controller.signal)
      .then((nextPolicy) => {
        if (controller.signal.aborted) {
          return;
        }

        setPolicy(nextPolicy);
      })
      .catch((error: unknown) => {
        if (controller.signal.aborted) {
          return;
        }

        setPolicyError(true);

        if (isAuthFailure(error)) {
          void refreshSession();
        }
      });

    return () => controller.abort();
  }, [fileSpaceId, refreshSession]);

  if (!policy) {
    return (
      <Dialog open onOpenChange={onOpenChange}>
        <DialogContent className="max-w-2xl gap-0 overscroll-contain p-0">
          <div className="border-b border-border px-5 py-4 pe-12 sm:px-6">
            <DialogHeader className="gap-1 pe-0">
              <DialogTitle>{copy.uploadCenterTitle}</DialogTitle>
              <DialogDescription className="text-[12px] leading-5">
                {copy.uploadCenterDescription}
              </DialogDescription>
            </DialogHeader>
          </div>

          <div className="p-5 sm:p-6">
            <div className="flex min-h-32 w-full flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border/70 bg-muted/10 px-5 py-6 text-center opacity-75">
              <span className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <KeenIcon
                  name={policyError ? "cross" : "loading"}
                  className={cn(
                    "text-[23px]",
                    !policyError && "animate-spin",
                  )}
                />
              </span>
              <span className="text-sm font-semibold text-foreground">
                {policyError
                  ? copy.uploadPolicyUnavailable
                  : copy.uploadPolicyLoading}
              </span>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    );
  }

  return (
    <UploadCenterReady
      onOpenChange={onOpenChange}
      fileSpaceId={fileSpaceId}
      parentId={parentId}
      quota={quota}
      locale={locale}
      copy={copy}
      policy={policy}
      onUploadsChanged={onUploadsChanged}
      onFileSpaceUnavailable={onFileSpaceUnavailable}
    />
  );
}

function UploadCenterReady({
  onOpenChange,
  fileSpaceId,
  parentId,
  quota,
  locale,
  copy,
  policy,
  onUploadsChanged,
  onFileSpaceUnavailable,
}: UploadCenterSessionProps & { policy: FileManagerUploadPolicy }) {
  const { refreshSession } = useAuth();
  const inputRef = React.useRef<HTMLInputElement>(null);
  const completedIdsRef = React.useRef(new Set<string>());
  const failedAvailabilityIdsRef = React.useRef(new Set<string>());
  const failedAuthIdsRef = React.useRef(new Set<string>());
  const quotaFailureIdsRef = React.useRef(new Set<string>());
  const quotaRefreshTimerRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);
  const uploadsChangedRef = React.useRef(false);
  const [queue] = React.useState(
    () =>
      new UploadQueueController({
        fileSpaceId,
        parentId,
        policy,
      }),
  );
  const snapshot = React.useSyncExternalStore(
    queue.subscribe,
    queue.getSnapshot,
    queue.getSnapshot,
  );
  const [dragging, setDragging] = React.useState(false);
  const [removeAllOpen, setRemoveAllOpen] = React.useState(false);

  const disposeTimerRef = React.useRef<ReturnType<typeof setTimeout> | null>(
    null,
  );

  React.useEffect(() => {
    if (disposeTimerRef.current !== null) {
      clearTimeout(disposeTimerRef.current);
      disposeTimerRef.current = null;
    }

    return () => {
      disposeTimerRef.current = setTimeout(() => {
        queue.dispose();
        disposeTimerRef.current = null;
      }, 0);
    };
  }, [queue]);

  const scheduleQuotaRefresh = React.useCallback(() => {
    if (quotaRefreshTimerRef.current !== null) {
      clearTimeout(quotaRefreshTimerRef.current);
    }

    quotaRefreshTimerRef.current = setTimeout(() => {
      quotaRefreshTimerRef.current = null;
      onUploadsChanged();
    }, 150);
  }, [onUploadsChanged]);

  React.useEffect(() => {
    return () => {
      if (quotaRefreshTimerRef.current !== null) {
        clearTimeout(quotaRefreshTimerRef.current);
      }
    };
  }, []);

  React.useEffect(() => {
    for (const item of snapshot.items) {
      if (
        item.status === "completed" &&
        item.result &&
        !completedIdsRef.current.has(item.id)
      ) {
        completedIdsRef.current.add(item.id);
        uploadsChangedRef.current = true;

        toast.add({
          type: "success",
          title: copy.uploadComplete,
          description: item.file.name,
        });
        scheduleQuotaRefresh();
      }

      if (
        item.status === "failed" &&
        item.error &&
        (item.error.code === "RESOURCE_NOT_FOUND" ||
          item.error.code === "ACCESS_DENIED") &&
        !failedAvailabilityIdsRef.current.has(item.id)
      ) {
        failedAvailabilityIdsRef.current.add(item.id);
        onFileSpaceUnavailable(fileSpaceId);
      }

      if (
        item.status === "failed" &&
        item.error?.code === "STORAGE_QUOTA_EXCEEDED" &&
        !quotaFailureIdsRef.current.has(item.id)
      ) {
        quotaFailureIdsRef.current.add(item.id);
        scheduleQuotaRefresh();
      }

      if (
        item.status === "failed" &&
        item.error &&
        isAuthFailure(item.error) &&
        !failedAuthIdsRef.current.has(item.id)
      ) {
        failedAuthIdsRef.current.add(item.id);
        void refreshSession();
      }
    }
  }, [
    copy.uploadComplete,
    fileSpaceId,
    onFileSpaceUnavailable,
    refreshSession,
    scheduleQuotaRefresh,
    snapshot.items,
  ]);

  const requestOpenChange = (nextOpen: boolean) => {
    if (!nextOpen && snapshot.active_count > 0) {
      toast.add({
        type: "warning",
        title: copy.uploadCenterTitle,
        description: copy.uploadCloseBlocked,
      });
      return;
    }

    if (!nextOpen && uploadsChangedRef.current) {
      onUploadsChanged();
      uploadsChangedRef.current = false;
    }

    onOpenChange(nextOpen);
  };

  const addFiles = (files: FileList | File[]) => {
    const result = queue.addFiles(Array.from(files));
    reportRejectedFiles(result, copy);
  };

  const selectFiles = () => {
    inputRef.current?.click();
  };

  const aggregatePercent =
    snapshot.total_bytes > 0
      ? Math.min(
          100,
          Math.round((snapshot.completed_bytes / snapshot.total_bytes) * 100),
        )
      : 0;

  const queuedOrActive =
    snapshot.queued_count > 0 || snapshot.active_count > 0;
  const hasItems = snapshot.items.length > 0;

  return (
    <>
      <Dialog open onOpenChange={requestOpenChange}>
        <DialogContent className="max-w-2xl gap-0 overscroll-contain p-0">
          <div className="border-b border-border px-5 py-4 pe-12 sm:px-6">
            <DialogHeader className="gap-1 pe-0">
              <DialogTitle>{copy.uploadCenterTitle}</DialogTitle>
              <DialogDescription className="text-[12px] leading-5">
                {copy.uploadCenterDescription}
              </DialogDescription>
            </DialogHeader>
          </div>

          <div className="flex min-h-0 flex-col gap-4 p-5 sm:p-6">
            <input
              ref={inputRef}
              type="file"
              multiple
              accept={policy.accepted_extensions.map((extension) => `.${extension}`).join(",")}
              className="sr-only"
              tabIndex={-1}
              aria-hidden="true"
              onChange={(event) => {
                if (event.currentTarget.files) {
                  addFiles(event.currentTarget.files);
                }
                event.currentTarget.value = "";
              }}
            />

            <button
              type="button"
              onClick={selectFiles}
              onDragEnter={(event) => {
                event.preventDefault();
                setDragging(true);
              }}
              onDragOver={(event) => {
                event.preventDefault();
                setDragging(true);
              }}
              onDragLeave={(event) => {
                event.preventDefault();
                if (event.currentTarget === event.target) {
                  setDragging(false);
                }
              }}
              onDrop={(event) => {
                event.preventDefault();
                setDragging(false);
                if (event.dataTransfer.files.length > 0) {
                  addFiles(event.dataTransfer.files);
                }
              }}
              className={cn(
                "flex min-h-32 w-full flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-5 py-6 text-center transition-colors",
                "border-border bg-muted/20 hover:border-primary/45 hover:bg-primary/[0.035]",
                dragging && "border-primary bg-primary/[0.06]",
              )}
            >
              <span className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <KeenIcon name="cloud-add" className="text-[23px]" />
              </span>
              <span className="text-sm font-semibold text-foreground">
                {copy.dropFilesHere}
              </span>
              <span className="text-[11px] text-muted-foreground">
                {copy.uploadLimitSummary
                  .replace("{count}", String(policy.max_files_per_batch))
                  .replace(
                    "{size}",
                    formatBytes(policy.max_file_size_bytes, locale),
                  )}
              </span>
              <span className="max-w-full truncate text-[10px] text-muted-foreground" dir="ltr" title={policy.accepted_extensions.map((extension) => `.${extension}`).join(", ")}>
                {copy.uploadAcceptedTypes.replace(
                  "{types}",
                  policy.accepted_extensions.length > 0
                    ? policy.accepted_extensions.map((extension) => `.${extension}`).join(", ")
                    : "—",
                )}
              </span>
              <span className="text-[11px] font-medium text-foreground">
                {quota.remaining_bytes === null
                  ? `${copy.storageQuota}: ${copy.storageUnlimited}`
                  : copy.uploadQuotaRemaining.replace(
                      "{size}",
                      formatStorageBytes(quota.remaining_bytes, locale),
                    )}
              </span>
            </button>

            <div className="flex flex-wrap items-center gap-2">
              <Button type="button" size="sm" onClick={selectFiles}>
                <KeenIcon name="file-added" className="text-[15px]" />
                {copy.attachFiles}
              </Button>

              <Button
                type="button"
                size="sm"
                variant="secondary"
                onClick={() => queue.startAll()}
                disabled={snapshot.queued_count === 0}
              >
                <KeenIcon
                  name={snapshot.active_count > 0 ? "loading" : "cloud-add"}
                  className={cn(
                    "text-[15px]",
                    snapshot.active_count > 0 && "animate-spin",
                  )}
                />
                {copy.uploadAll}
              </Button>

              <Button
                type="button"
                size="sm"
                variant="ghost"
                onClick={() => setRemoveAllOpen(true)}
                disabled={!hasItems}
              >
                <KeenIcon name="trash" className="text-[15px]" />
                {copy.removeAll}
              </Button>

              {hasItems ? (
                <span className="ms-auto text-[11px] font-medium text-muted-foreground">
                  {copy.uploadQueueSummary
                    .replace("{count}", String(snapshot.items.length))
                    .replace("{size}", formatBytes(snapshot.total_bytes, locale))}
                </span>
              ) : null}
            </div>

            {hasItems ? (
              <div className="min-h-0 overflow-hidden rounded-xl border border-border bg-background">
                <div className="max-h-[42vh] overflow-y-auto">
                  {snapshot.items.map((item) => (
                    <UploadQueueRow
                      key={item.id}
                      item={item}
                      locale={locale}
                      copy={copy}
                      onStart={() => queue.start(item.id)}
                      onRetry={() => queue.retry(item.id)}
                      onCancel={() => queue.cancel(item.id)}
                      onRemove={() => queue.remove(item.id)}
                    />
                  ))}
                </div>

                <div className="border-t border-border bg-muted/20 px-4 py-3">
                  <div className="mb-1.5 flex items-center justify-between gap-3 text-[10.5px] font-medium text-muted-foreground">
                    <span>
                      {snapshot.completed_count} / {snapshot.items.length}
                    </span>
                    <span>{aggregatePercent}%</span>
                  </div>
                  <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                    <div
                      className="h-full rounded-full bg-primary transition-[width] duration-200"
                      style={{ width: `${aggregatePercent}%` }}
                    />
                  </div>
                </div>
              </div>
            ) : (
              <p className="text-center text-[11px] text-muted-foreground">
                {copy.uploadLimitSummary
                  .replace("{count}", String(policy.max_files_per_batch))
                  .replace(
                    "{size}",
                    formatBytes(policy.max_file_size_bytes, locale),
                  )}
              </p>
            )}

            {queuedOrActive ? (
              <div className="sr-only" aria-live="polite">
                {snapshot.active_count > 0
                  ? copy.uploadInProgress
                  : copy.uploadQueued}
              </div>
            ) : null}
          </div>
        </DialogContent>
      </Dialog>

      <AlertDialog open={removeAllOpen} onOpenChange={setRemoveAllOpen}>
        <AlertDialogContent size="sm">
          <AlertDialogHeader>
            <AlertDialogMedia>
              <KeenIcon name="trash" className="text-[20px]" />
            </AlertDialogMedia>
            <AlertDialogTitle>{copy.removeAllUploadsTitle}</AlertDialogTitle>
            <AlertDialogDescription>
              {copy.removeAllUploadsDescription}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{copy.cancel}</AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              onClick={() => {
                queue.removeAll();
                setRemoveAllOpen(false);
              }}
            >
              {copy.removeAllUploadsConfirm}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

function UploadQueueRow({
  item,
  locale,
  copy,
  onStart,
  onRetry,
  onCancel,
  onRemove,
}: {
  item: UploadQueueItem;
  locale: StorviaLocale;
  copy: WorkspaceCopy;
  onStart: () => void;
  onRetry: () => void;
  onCancel: () => void;
  onRemove: () => void;
}) {
  const statusLabel = queueStatusLabel(item, copy);
  const progress =
    item.status === "completed" ? 100 : Math.max(0, Math.min(100, item.percent));

  return (
    <div className="border-b border-border px-4 py-3 last:border-b-0">
      <div className="flex items-start gap-3">
        <span
          className={cn(
            "mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg",
            item.status === "completed"
              ? "bg-success/10 text-success"
              : item.status === "failed"
                ? "bg-destructive/10 text-destructive"
                : "bg-primary/10 text-primary",
          )}
        >
          <KeenIcon
            name={
              item.status === "completed"
                ? "check"
                : item.status === "failed"
                  ? "cross"
                  : "document"
            }
            className="text-[18px]"
          />
        </span>

        <div className="min-w-0 flex-1">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
              <p className="truncate text-[12px] font-semibold text-foreground">
                {item.file.name}
              </p>
              <p className="mt-0.5 text-[10.5px] text-muted-foreground">
                {formatBytes(item.file.size, locale)} • {statusLabel}
              </p>
            </div>

            <div className="flex shrink-0 items-center gap-1">
              {item.status === "queued" ? (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-xs"
                  title={copy.upload}
                  aria-label={copy.upload}
                  onClick={onStart}
                >
                  <KeenIcon name="cloud-add" />
                </Button>
              ) : null}

              {item.status === "uploading" ? (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-xs"
                  title={copy.cancel}
                  aria-label={copy.cancel}
                  onClick={onCancel}
                >
                  <KeenIcon name="cross" />
                </Button>
              ) : null}

              {item.status === "failed" || item.status === "cancelled" ? (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-xs"
                  title={copy.retry}
                  aria-label={copy.retry}
                  onClick={onRetry}
                >
                  <KeenIcon name="arrows-circle" />
                </Button>
              ) : null}

              {item.status !== "uploading" ? (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-xs"
                  title={copy.removeUpload}
                  aria-label={copy.removeUpload}
                  onClick={onRemove}
                >
                  <KeenIcon name="trash" />
                </Button>
              ) : null}
            </div>
          </div>

          <div className="mt-2 flex items-center gap-2">
            <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
              <div
                className={cn(
                  "h-full rounded-full transition-[width] duration-150",
                  item.status === "failed"
                    ? "bg-destructive"
                    : item.status === "completed"
                      ? "bg-success"
                      : "bg-primary",
                )}
                style={{ width: `${progress}%` }}
              />
            </div>
            <span className="w-9 text-end text-[10px] font-semibold text-muted-foreground">
              {progress}%
            </span>
          </div>

          {item.error ? (
            <p className="mt-1.5 text-[10.5px] leading-4 text-destructive">
              {queueErrorMessage(item, copy)}
            </p>
          ) : null}
        </div>
      </div>
    </div>
  );
}

function isAuthFailure(
  error: { code: string; status?: number } | unknown,
): boolean {
  if (isApiError(error)) {
    return (
      error.status === 401 ||
      error.code === "AUTH_REQUIRED" ||
      error.code === "SESSION_EXPIRED" ||
      error.code === "USER_DISABLED"
    );
  }

  if (
    typeof error === "object" &&
    error !== null &&
    "code" in error &&
    typeof error.code === "string"
  ) {
    const status =
      "status" in error && typeof error.status === "number" ? error.status : 0;

    return (
      status === 401 ||
      error.code === "AUTH_REQUIRED" ||
      error.code === "SESSION_EXPIRED" ||
      error.code === "USER_DISABLED"
    );
  }

  return false;
}

function queueStatusLabel(item: UploadQueueItem, copy: WorkspaceCopy): string {
  if (item.status === "uploading") return copy.uploadInProgress;
  if (item.status === "completed") return copy.uploadCompletedStatus;
  if (item.status === "failed") return copy.uploadFailedStatus;
  if (item.status === "cancelled") return copy.uploadCancelledStatus;
  return copy.uploadQueued;
}

function queueErrorMessage(item: UploadQueueItem, copy: WorkspaceCopy): string {
  const code = item.error?.code;

  if (code === "NETWORK_ERROR") return copy.networkActionFailed;
  if (code === "ACCESS_DENIED") return copy.actionNotAllowed;
  if (code === "UPLOAD_TOO_LARGE") return copy.uploadTooLarge;
  if (code === "STORAGE_QUOTA_EXCEEDED") return copy.uploadQuotaExceeded;
  if (code === "RESOURCE_CONFLICT") return copy.uploadNameConflict;
  if (code === "RESOURCE_NOT_FOUND") return copy.uploadDestinationUnavailable;
  if (code === "VALIDATION_FAILED") return copy.uploadInvalid;

  return item.error?.message || copy.couldNotUpload;
}

function reportRejectedFiles(
  result: UploadQueueAddResult,
  copy: WorkspaceCopy,
): void {
  const batchRejected = result.rejected.filter(
    (entry) => entry.reason === "MAX_FILES_PER_BATCH",
  ).length;
  const tooLargeRejected = result.rejected.filter(
    (entry) => entry.reason === "FILE_TOO_LARGE",
  ).length;
  const fileTypeRejected = result.rejected.filter(
    (entry) => entry.reason === "FILE_TYPE_NOT_ALLOWED",
  ).length;

  if (batchRejected > 0) {
    toast.add({
      type: "warning",
      title: copy.uploadCenterTitle,
      description: copy.uploadBatchLimitReached,
    });
  }

  if (tooLargeRejected > 0) {
    toast.add({
      type: "error",
      title: copy.couldNotUpload,
      description: copy.uploadFilesTooLarge,
    });
  }

  if (fileTypeRejected > 0) {
    toast.add({
      type: "warning",
      title: copy.couldNotUpload,
      description: copy.uploadFileTypeNotAllowed,
    });
  }
}

function formatBytes(value: number, locale: StorviaLocale): string {
  if (!Number.isFinite(value) || value <= 0) {
    return "0 B";
  }

  const units = ["B", "KB", "MB", "GB", "TB"];
  const index = Math.min(
    units.length - 1,
    Math.floor(Math.log(value) / Math.log(1024)),
  );
  const numeric = value / 1024 ** index;

  return `${new Intl.NumberFormat(locale === "ar" ? "ar" : "en", {
    maximumFractionDigits: numeric >= 10 || index === 0 ? 0 : 1,
  }).format(numeric)} ${units[index]}`;
}
