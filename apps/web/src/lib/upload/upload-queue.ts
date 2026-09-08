import { ApiError } from "@/lib/api/api-error";
import {
  type FileManagerNode,
  type FileManagerUploadPolicy,
  uploadFileManagerFileWithProgress,
} from "@/lib/api/file-manager-client";

export const DEFAULT_UPLOAD_CONCURRENCY = 2;

export type UploadQueueItemStatus =
  | "queued"
  | "uploading"
  | "completed"
  | "failed"
  | "cancelled";

export type UploadQueueItemError = {
  code: string;
  message: string;
  status: number;
};

export type UploadQueueItem = {
  id: string;
  file: File;
  status: UploadQueueItemStatus;
  loaded: number;
  total: number;
  percent: number;
  error: UploadQueueItemError | null;
  result: FileManagerNode | null;
};

export type UploadQueueSnapshot = {
  items: UploadQueueItem[];
  active_count: number;
  queued_count: number;
  completed_count: number;
  failed_count: number;
  cancelled_count: number;
  total_bytes: number;
  completed_bytes: number;
};

export type UploadQueueRejectedFile = {
  file: File;
  reason: "MAX_FILES_PER_BATCH" | "FILE_TOO_LARGE" | "FILE_TYPE_NOT_ALLOWED";
};

export type UploadQueueAddResult = {
  accepted_ids: string[];
  rejected: UploadQueueRejectedFile[];
};

export type UploadQueueControllerOptions = {
  fileSpaceId: string;
  parentId?: string | null;
  policy: FileManagerUploadPolicy;
  concurrency?: number;
  transport?: typeof uploadFileManagerFileWithProgress;
};

type Listener = () => void;

let fallbackId = 0;

function createQueueItemId(): string {
  if (
    typeof globalThis.crypto !== "undefined" &&
    typeof globalThis.crypto.randomUUID === "function"
  ) {
    return globalThis.crypto.randomUUID();
  }

  fallbackId += 1;
  return `upload-${Date.now()}-${fallbackId}`;
}

function emptySnapshot(): UploadQueueSnapshot {
  return {
    items: [],
    active_count: 0,
    queued_count: 0,
    completed_count: 0,
    failed_count: 0,
    cancelled_count: 0,
    total_bytes: 0,
    completed_bytes: 0,
  };
}

function clampConcurrency(value: number): number {
  if (!Number.isFinite(value)) {
    return DEFAULT_UPLOAD_CONCURRENCY;
  }

  return Math.max(1, Math.floor(value));
}

function normalizedFinalExtension(filename: string): string | null {
  const normalized = filename.trim().replace(/[. ]+$/, "");
  const dot = normalized.lastIndexOf(".");

  if (dot <= 0 || dot === normalized.length - 1) return null;

  const extension = normalized.slice(dot + 1).toLowerCase();
  return /^[a-z0-9][a-z0-9+-]{0,31}$/.test(extension) ? extension : null;
}

function toQueueError(error: unknown): UploadQueueItemError {
  if (error instanceof ApiError) {
    return {
      code: error.code,
      message: error.message,
      status: error.status,
    };
  }

  return {
    code: "UPLOAD_FAILED",
    message: error instanceof Error ? error.message : "The upload failed.",
    status: 0,
  };
}

const FATAL_UPLOAD_CONTEXT_CODES = new Set([
  "AUTH_REQUIRED",
  "SESSION_EXPIRED",
  "USER_DISABLED",
  "ACCESS_DENIED",
  "RESOURCE_NOT_FOUND",
]);

function isFatalUploadContextError(error: UploadQueueItemError): boolean {
  return (
    error.status === 401 ||
    error.status === 403 ||
    FATAL_UPLOAD_CONTEXT_CODES.has(error.code)
  );
}

export class UploadQueueController {
  private readonly fileSpaceId: string;
  private readonly parentId: string | null;
  private readonly policy: FileManagerUploadPolicy;
  private readonly concurrency: number;
  private readonly transport: typeof uploadFileManagerFileWithProgress;
  private readonly listeners = new Set<Listener>();
  private readonly abortControllers = new Map<string, AbortController>();
  private readonly requested = new Set<string>();
  private readonly pendingRemoval = new Set<string>();

  private items: UploadQueueItem[] = [];
  private snapshot: UploadQueueSnapshot = emptySnapshot();
  private autoStart = false;
  private disposed = false;

  constructor(options: UploadQueueControllerOptions) {
    this.fileSpaceId = options.fileSpaceId;
    this.parentId = options.parentId ?? null;
    this.policy = options.policy;
    this.concurrency = clampConcurrency(
      options.concurrency ?? DEFAULT_UPLOAD_CONCURRENCY,
    );
    this.transport = options.transport ?? uploadFileManagerFileWithProgress;
  }

  subscribe = (listener: Listener): (() => void) => {
    this.listeners.add(listener);

    return () => {
      this.listeners.delete(listener);
    };
  };

  getSnapshot = (): UploadQueueSnapshot => this.snapshot;

  addFiles(files: Iterable<File>): UploadQueueAddResult {
    this.assertActive();

    const acceptedIds: string[] = [];
    const rejected: UploadQueueRejectedFile[] = [];
    let remainingSlots = Math.max(
      0,
      this.policy.max_files_per_batch - this.items.length,
    );

    for (const file of files) {
      if (remainingSlots <= 0) {
        rejected.push({ file, reason: "MAX_FILES_PER_BATCH" });
        continue;
      }

      if (file.size > this.policy.max_file_size_bytes) {
        rejected.push({ file, reason: "FILE_TOO_LARGE" });
        continue;
      }

      const extension = normalizedFinalExtension(file.name);
      if (
        extension === null ||
        !this.policy.accepted_extensions.includes(extension)
      ) {
        rejected.push({ file, reason: "FILE_TYPE_NOT_ALLOWED" });
        continue;
      }

      const id = createQueueItemId();
      this.items.push({
        id,
        file,
        status: "queued",
        loaded: 0,
        total: file.size,
        percent: 0,
        error: null,
        result: null,
      });
      acceptedIds.push(id);
      remainingSlots -= 1;
    }

    this.publish();

    if (this.autoStart) {
      this.pump();
    }

    return {
      accepted_ids: acceptedIds,
      rejected,
    };
  }

  startAll(): void {
    this.assertActive();
    this.autoStart = true;
    this.pump();
  }

  start(itemId: string): void {
    this.assertActive();

    const item = this.findItem(itemId);
    if (!item) {
      return;
    }

    if (item.status === "failed" || item.status === "cancelled") {
      this.resetForRetry(itemId);
    }

    const refreshed = this.findItem(itemId);
    if (!refreshed || refreshed.status !== "queued") {
      return;
    }

    this.requested.add(itemId);
    this.pump();
  }

  retry(itemId: string): void {
    this.assertActive();

    const item = this.findItem(itemId);
    if (!item || (item.status !== "failed" && item.status !== "cancelled")) {
      return;
    }

    this.resetForRetry(itemId);
    this.requested.add(itemId);
    this.pump();
  }

  cancel(itemId: string): void {
    this.assertActive();

    const item = this.findItem(itemId);
    if (!item || item.status === "completed") {
      return;
    }

    this.requested.delete(itemId);

    if (item.status === "uploading") {
      this.updateItem(itemId, {
        status: "cancelled",
        error: null,
      });
      this.abortControllers.get(itemId)?.abort();
      return;
    }

    if (item.status === "queued" || item.status === "failed") {
      this.updateItem(itemId, {
        status: "cancelled",
        error: null,
      });
    }
  }

  remove(itemId: string): void {
    this.assertActive();

    const item = this.findItem(itemId);
    if (!item) {
      return;
    }

    this.requested.delete(itemId);

    if (item.status === "uploading") {
      this.pendingRemoval.add(itemId);
      this.updateItem(itemId, {
        status: "cancelled",
        error: null,
      });
      this.abortControllers.get(itemId)?.abort();
      return;
    }

    this.removeImmediately(itemId);
  }

  removeAll(): void {
    this.assertActive();
    this.autoStart = false;
    this.requested.clear();

    const activeIds = new Set(this.abortControllers.keys());

    for (const item of this.items) {
      if (activeIds.has(item.id)) {
        this.pendingRemoval.add(item.id);
        this.abortControllers.get(item.id)?.abort();
      }
    }

    this.items = this.items.filter((item) => activeIds.has(item.id));

    for (const item of this.items) {
      item.status = "cancelled";
      item.error = null;
    }

    this.publish();
  }

  dispose(): void {
    if (this.disposed) {
      return;
    }

    this.disposed = true;
    this.autoStart = false;
    this.requested.clear();
    this.pendingRemoval.clear();

    for (const controller of this.abortControllers.values()) {
      controller.abort();
    }

    this.abortControllers.clear();
    this.listeners.clear();
  }

  private assertActive(): void {
    if (this.disposed) {
      throw new Error("The upload queue has been disposed.");
    }
  }

  private findItem(itemId: string): UploadQueueItem | undefined {
    return this.items.find((item) => item.id === itemId);
  }

  private resetForRetry(itemId: string): void {
    const item = this.findItem(itemId);
    if (!item) {
      return;
    }

    this.updateItem(itemId, {
      status: "queued",
      loaded: 0,
      total: item.file.size,
      percent: 0,
      error: null,
      result: null,
    });
  }

  private pump(): void {
    if (this.disposed) {
      return;
    }

    let available = this.concurrency - this.abortControllers.size;

    while (available > 0) {
      const next = this.items.find(
        (item) =>
          item.status === "queued" &&
          (this.autoStart || this.requested.has(item.id)),
      );

      if (!next) {
        break;
      }

      this.requested.delete(next.id);
      this.startUpload(next.id);
      available -= 1;
    }

    if (
      this.autoStart &&
      this.abortControllers.size === 0 &&
      !this.items.some((item) => item.status === "queued")
    ) {
      this.autoStart = false;
    }
  }

  private startUpload(itemId: string): void {
    const item = this.findItem(itemId);
    if (!item || item.status !== "queued" || this.disposed) {
      return;
    }

    const controller = new AbortController();
    this.abortControllers.set(itemId, controller);
    this.updateItem(itemId, {
      status: "uploading",
      loaded: 0,
      total: item.file.size,
      percent: 0,
      error: null,
      result: null,
    });

    void this.transport(this.fileSpaceId, item.file, {
      parentId: this.parentId,
      signal: controller.signal,
      onProgress: (progress) => {
        const current = this.findItem(itemId);
        if (!current || current.status !== "uploading") {
          return;
        }

        this.updateItem(itemId, {
          loaded: progress.loaded,
          total: progress.total,
          percent: progress.percent,
        });
      },
    })
      .then((result) => {
        if (this.pendingRemoval.has(itemId)) {
          return;
        }

        const current = this.findItem(itemId);
        if (!current || current.status === "cancelled") {
          return;
        }

        this.updateItem(itemId, {
          status: "completed",
          loaded: Math.max(current.total, current.file.size),
          total: Math.max(current.total, current.file.size),
          percent: 100,
          error: null,
          result,
        });
      })
      .catch((error: unknown) => {
        if (this.pendingRemoval.has(itemId)) {
          return;
        }

        const current = this.findItem(itemId);
        if (!current || current.status === "cancelled") {
          return;
        }

        const queueError = toQueueError(error);

        this.updateItem(itemId, {
          status: "failed",
          error: queueError,
          result: null,
        });

        if (isFatalUploadContextError(queueError)) {
          this.haltAfterFatalContextFailure(itemId);
          return;
        }

        if (queueError.code === "NETWORK_ERROR") {
          this.pauseAutomaticUploads();
        }
      })
      .finally(() => {
        this.abortControllers.delete(itemId);

        if (this.pendingRemoval.delete(itemId)) {
          this.removeImmediately(itemId);
        }

        this.pump();
      });
  }

  private pauseAutomaticUploads(): void {
    this.autoStart = false;
    this.requested.clear();
  }

  private haltAfterFatalContextFailure(failedItemId: string): void {
    this.pauseAutomaticUploads();

    const activeIdsToAbort: string[] = [];

    this.items = this.items.map((item) => {
      if (
        item.id === failedItemId ||
        item.status === "completed" ||
        item.status === "failed" ||
        item.status === "cancelled"
      ) {
        return item;
      }

      if (item.status === "uploading") {
        activeIdsToAbort.push(item.id);
      }

      return {
        ...item,
        status: "cancelled",
        error: null,
      };
    });

    this.publish();

    for (const activeId of activeIdsToAbort) {
      this.abortControllers.get(activeId)?.abort();
    }
  }

  private updateItem(
    itemId: string,
    patch: Partial<Omit<UploadQueueItem, "id" | "file">>,
  ): void {
    this.items = this.items.map((item) =>
      item.id === itemId ? { ...item, ...patch } : item,
    );
    this.publish();
  }

  private removeImmediately(itemId: string): void {
    this.requested.delete(itemId);
    this.pendingRemoval.delete(itemId);
    this.items = this.items.filter((item) => item.id !== itemId);
    this.publish();
  }

  private publish(): void {
    const items = this.items.map((item) => ({ ...item }));
    const completedBytes = items.reduce((total, item) => {
      if (item.status === "completed") {
        return total + item.file.size;
      }

      if (item.status === "uploading") {
        return total + Math.min(item.loaded, item.file.size);
      }

      return total;
    }, 0);

    this.snapshot = {
      items,
      active_count: items.filter((item) => item.status === "uploading").length,
      queued_count: items.filter((item) => item.status === "queued").length,
      completed_count: items.filter((item) => item.status === "completed").length,
      failed_count: items.filter((item) => item.status === "failed").length,
      cancelled_count: items.filter((item) => item.status === "cancelled").length,
      total_bytes: items.reduce((total, item) => total + item.file.size, 0),
      completed_bytes: completedBytes,
    };

    for (const listener of this.listeners) {
      listener();
    }
  }
}
