"use client";

import * as React from "react";

import { hasFileManagerNodeAction } from "@/components/file-manager/file-manager-capabilities";
import { BidiText } from "@/components/ui/bidi-text";
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

type TrashNodeDialogProps = {
  node: FileManagerNode | null;
  fileSpaceId: string;
  copy: WorkspaceCopy;
  onOpenChange: (open: boolean) => void;
  onTrashed: (node: FileManagerNode) => void;
};

export function TrashNodeDialog({
  node,
  fileSpaceId,
  copy,
  onOpenChange,
  onTrashed,
}: TrashNodeDialogProps) {
  const [pending, setPending] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  const confirmTrash = async () => {
    if (!node || !hasFileManagerNodeAction(node, "trash") || pending) {
      return;
    }

    setPending(true);
    setError(null);

    try {
      const trashed = await trashFileManagerNode(fileSpaceId, node.id);
      onTrashed(trashed);
      onOpenChange(false);
    } catch (trashError) {
      setPending(false);
      setError(actionErrorMessage(trashError, copy));
    }
  };

  return (
    <AlertDialog
      open={node !== null && hasFileManagerNodeAction(node, "trash")}
      onOpenChange={(open) => {
        if (!pending) {
          onOpenChange(open);
        }
      }}
    >
      <AlertDialogContent size="sm">
        <AlertDialogHeader>
          <AlertDialogMedia>
            <KeenIcon name="trash" className="text-[20px]" />
          </AlertDialogMedia>
          <AlertDialogTitle>{copy.trashConfirmTitle}</AlertDialogTitle>
          <AlertDialogDescription>
            {copy.trashConfirmDescription}
            {node ? (
              <>
                {" "}
                <BidiText className="font-medium text-foreground">
                  {node.name}
                </BidiText>
              </>
            ) : null}
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
            disabled={pending}
            onClick={() => void confirmTrash()}
          >
            {pending ? (
              <KeenIcon name="loading" className="animate-spin text-[15px]" />
            ) : (
              <KeenIcon name="trash" className="text-[15px]" />
            )}
            {copy.deleteItem}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}

function actionErrorMessage(error: unknown, copy: WorkspaceCopy): string {
  if (!isApiError(error)) {
    return copy.couldNotTrash;
  }

  if (error.code === "NETWORK_ERROR") {
    return copy.networkActionFailed;
  }

  if (error.code === "ACCESS_DENIED") {
    return copy.actionNotAllowed;
  }

  return copy.couldNotTrash;
}
