"use client";

import * as React from "react";

import { hasFileManagerNodeAction } from "@/components/file-manager/file-manager-capabilities";
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
import { KeenIcon } from "@/components/ui/keen-icon";
import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import { isApiError } from "@/lib/api/api-error";
import {
  trashFileManagerNode,
  type FileManagerNode,
} from "@/lib/api/file-manager-client";
import type { StorviaLocale } from "@/lib/i18n";

type BulkTrashDialogProps = {
  nodes: FileManagerNode[];
  fileSpaceId: string;
  copy: WorkspaceCopy;
  locale: StorviaLocale;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onTrashed: (nodes: FileManagerNode[]) => void;
};

export function BulkTrashDialog({
  nodes,
  fileSpaceId,
  copy,
  locale,
  open,
  onOpenChange,
  onTrashed,
}: BulkTrashDialogProps) {
  const [pending, setPending] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  const canTrashAll =
    nodes.length > 0 &&
    nodes.every((node) => hasFileManagerNodeAction(node, "trash"));

  const confirmTrash = async () => {
    if (!canTrashAll || pending) {
      return;
    }

    setPending(true);
    setError(null);

    const results = await Promise.allSettled(
      nodes.map((node) => trashFileManagerNode(fileSpaceId, node.id)),
    );
    const trashed = results.flatMap((result) =>
      result.status === "fulfilled" ? [result.value] : [],
    );
    const failed = results.filter((result) => result.status === "rejected");

    if (trashed.length > 0) {
      onTrashed(trashed);
    }

    if (failed.length === 0) {
      setPending(false);
      onOpenChange(false);
      return;
    }

    setPending(false);
    setError(bulkErrorMessage(failed[0], copy));
  };

  const formattedCount = new Intl.NumberFormat(locale === "ar" ? "ar-YE" : "en-US").format(nodes.length);

  return (
    <AlertDialog
      open={open && canTrashAll}
      onOpenChange={(nextOpen) => {
        if (!pending) {
          setError(null);
          onOpenChange(nextOpen);
        }
      }}
    >
      <AlertDialogContent size="sm">
        <AlertDialogHeader>
          <AlertDialogMedia>
            <KeenIcon name="trash" className="text-[20px]" />
          </AlertDialogMedia>
          <AlertDialogTitle>{copy.trashSelectedConfirmTitle}</AlertDialogTitle>
          <AlertDialogDescription>
            {copy.trashSelectedConfirmDescription.replace("{count}", formattedCount)}
          </AlertDialogDescription>
        </AlertDialogHeader>

        {error ? (
          <p role="alert" className="text-xs font-medium text-destructive">
            {error}
          </p>
        ) : null}

        <AlertDialogFooter>
          <AlertDialogCancel disabled={pending}>{copy.cancel}</AlertDialogCancel>
          <AlertDialogAction
            type="button"
            variant="destructive"
            disabled={pending || !canTrashAll}
            onClick={() => void confirmTrash()}
          >
            {pending ? (
              <KeenIcon name="loading" className="animate-spin text-[15px]" />
            ) : (
              <KeenIcon name="trash" className="text-[15px]" />
            )}
            {copy.deleteSelected}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}

function bulkErrorMessage(
  result: PromiseRejectedResult,
  copy: WorkspaceCopy,
): string {
  const error = result.reason;

  if (!isApiError(error)) {
    return copy.selectionActionPartial;
  }

  if (error.code === "NETWORK_ERROR") {
    return copy.networkActionFailed;
  }

  if (error.code === "ACCESS_DENIED") {
    return copy.actionNotAllowed;
  }

  return copy.selectionActionPartial;
}
