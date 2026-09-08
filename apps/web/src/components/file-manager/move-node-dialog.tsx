"use client";

import * as React from "react";

import { hasFileManagerNodeAction } from "@/components/file-manager/file-manager-capabilities";
import { BidiText } from "@/components/ui/bidi-text";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { KeenIcon } from "@/components/ui/keen-icon";
import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import { isApiError } from "@/lib/api/api-error";
import {
  browseFileManagerNodes,
  updateFileManagerNode,
  type FileManagerBreadcrumb,
  type FileManagerNode,
} from "@/lib/api/file-manager-client";
import { cn } from "@/lib/utils";

type MoveNodeDialogProps = {
  node: FileManagerNode | null;
  fileSpaceId: string;
  copy: WorkspaceCopy;
  onOpenChange: (open: boolean) => void;
  onMoved: (node: FileManagerNode) => void;
};

export function MoveNodeDialog({
  node,
  fileSpaceId,
  copy,
  onOpenChange,
  onMoved,
}: MoveNodeDialogProps) {
  const [parentId, setParentId] = React.useState<string | null>(null);
  const [breadcrumbs, setBreadcrumbs] = React.useState<FileManagerBreadcrumb[]>([]);
  const [folders, setFolders] = React.useState<FileManagerNode[]>([]);
  const [loading, setLoading] = React.useState(false);
  const [pending, setPending] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  React.useEffect(() => {
    if (!node) {
      return;
    }

    const controller = new AbortController();

    const load = async () => {
      setLoading(true);
      setError(null);
      setFolders([]);

      try {
        const result = await browseFileManagerNodes(fileSpaceId, {
          parentId,
          type: "folder",
          sort: "name",
          direction: "asc",
          perPage: 100,
          signal: controller.signal,
        });

        if (controller.signal.aborted) {
          return;
        }

        setBreadcrumbs(result.meta.breadcrumbs);
        setFolders(result.data.filter((folder) => folder.id !== node.id));
      } catch (loadError) {
        if (!controller.signal.aborted) {
          setError(actionErrorMessage(loadError, copy, copy.couldNotLoadFiles));
        }
      } finally {
        if (!controller.signal.aborted) {
          setLoading(false);
        }
      }
    };

    const timeout = window.setTimeout(() => {
      void load();
    }, 0);

    return () => {
      window.clearTimeout(timeout);
      controller.abort();
    };
  }, [copy, fileSpaceId, node, parentId]);

  const sameLocation = node?.parent_id === parentId;

  const moveHere = async () => {
    if (
      !node ||
      !hasFileManagerNodeAction(node, "move") ||
      pending ||
      sameLocation
    ) {
      return;
    }

    setPending(true);
    setError(null);

    try {
      const updated = await updateFileManagerNode(fileSpaceId, node.id, {
        parent_id: parentId,
      });

      onMoved(updated);
      onOpenChange(false);
    } catch (moveError) {
      setPending(false);
      setError(actionErrorMessage(moveError, copy, copy.couldNotMove));
    }
  };

  return (
    <Dialog
      open={node !== null && hasFileManagerNodeAction(node, "move")}
      onOpenChange={(open) => {
        if (!pending) {
          onOpenChange(open);
        }
      }}
    >
      <DialogContent className="max-w-xl" showCloseButton={false}>
        <DialogHeader>
          <DialogTitle>{copy.moveToFolder}</DialogTitle>
          <DialogDescription>
            {copy.chooseDestination}
            {node ? (
              <>
                {" "}
                <BidiText className="font-medium text-foreground">
                  {node.name}
                </BidiText>
              </>
            ) : null}
          </DialogDescription>
        </DialogHeader>

        <div className="rounded-lg border border-workspace-content-border bg-background">
          <nav className="flex min-h-11 flex-wrap items-center gap-1 border-b border-workspace-content-border px-3 py-2 text-[11px] font-medium text-muted-foreground">
            <button
              type="button"
              onClick={() => setParentId(null)}
              disabled={loading || pending}
              className={cn(
                "rounded-md px-2 py-1 transition-colors hover:bg-brand-blue/10 hover:text-brand-blue disabled:pointer-events-none disabled:opacity-50",
                breadcrumbs.length === 0 && "bg-brand-blue/10 text-brand-blue",
              )}
            >
              {copy.rootLocation}
            </button>
            {breadcrumbs.map((crumb, index) => (
              <React.Fragment key={crumb.id}>
                <KeenIcon
                  name="right"
                  className="text-[10px] text-muted-foreground/60 rtl:rotate-180"
                />
                <button
                  type="button"
                  onClick={() => setParentId(crumb.id)}
                  disabled={loading || pending}
                  className={cn(
                    "max-w-36 truncate rounded-md px-1.5 py-1 transition-colors hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-50",
                    index === breadcrumbs.length - 1 && "text-foreground",
                  )}
                >
                  {crumb.name}
                </button>
              </React.Fragment>
            ))}
          </nav>

          <div className="max-h-72 overflow-y-auto p-2">
            {loading ? (
              <div className="flex min-h-36 items-center justify-center gap-2 text-xs text-muted-foreground">
                <KeenIcon name="loading" className="animate-spin text-[16px]" />
                {copy.loadingFiles}
              </div>
            ) : folders.length === 0 ? (
              <div className="flex min-h-36 flex-col items-center justify-center px-5 text-center">
                <KeenIcon
                  name="folder"
                  variant="outline"
                  className="text-[28px] text-muted-foreground/70"
                />
                <p className="mt-2 text-xs text-muted-foreground">
                  {copy.noFoldersHere}
                </p>
              </div>
            ) : (
              <div className="space-y-1">
                {folders.map((folder) => (
                  <button
                    key={folder.id}
                    type="button"
                    onClick={() => setParentId(folder.id)}
                    disabled={pending}
                    className="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-start transition-colors hover:bg-workspace-row-hover disabled:pointer-events-none disabled:opacity-50"
                  >
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-brand-blue/8 text-brand-blue">
                      <KeenIcon name="folder" variant="outline" className="text-[18px]" />
                    </span>
                    <BidiText className="min-w-0 flex-1 truncate text-xs font-semibold">
                      {folder.name}
                    </BidiText>
                    <KeenIcon
                      name="right"
                      className="text-[12px] text-muted-foreground rtl:rotate-180"
                    />
                  </button>
                ))}
              </div>
            )}
          </div>
        </div>

        {error ? (
          <p role="alert" className="text-xs font-medium text-destructive">
            {error}
          </p>
        ) : null}

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={pending}
          >
            {copy.cancel}
          </Button>
          <Button
            type="button"
            onClick={() => void moveHere()}
            disabled={loading || pending || sameLocation || Boolean(error && folders.length === 0)}
          >
            {pending ? (
              <KeenIcon name="loading" className="animate-spin text-[15px]" />
            ) : (
              <KeenIcon name="folder-down" className="text-[15px]" />
            )}
            {copy.moveHere}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function actionErrorMessage(
  error: unknown,
  copy: WorkspaceCopy,
  fallback: string,
): string {
  if (!isApiError(error)) {
    return fallback;
  }

  if (error.code === "NETWORK_ERROR") {
    return copy.networkActionFailed;
  }

  if (error.code === "ACCESS_DENIED") {
    return copy.actionNotAllowed;
  }

  const nameErrors = error.details?.fields?.name;
  if (
    error.code === "VALIDATION_FAILED" &&
    Array.isArray(nameErrors) &&
    nameErrors.length > 0
  ) {
    return copy.moveConflict;
  }

  if (error.code === "RESOURCE_CONFLICT") {
    return copy.moveConflict;
  }

  return fallback;
}
