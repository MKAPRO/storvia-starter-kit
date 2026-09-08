"use client";

import * as React from "react";

import { formatStorageBytes } from "@/components/file-manager/storage-quota-summary";
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
import { toast } from "@/components/ui/toast";
import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import {
  emptyFileManagerTrash,
  type EmptyFileManagerTrashResult,
} from "@/lib/api/file-manager-client";
import type { StorviaLocale } from "@/lib/i18n";

export function EmptyTrashDialog({
  fileSpaceId,
  locale,
  copy,
  open,
  onOpenChange,
  onEmptied,
}: {
  fileSpaceId: string;
  locale: StorviaLocale;
  copy: WorkspaceCopy;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onEmptied: (result: EmptyFileManagerTrashResult) => void;
}) {
  const [pending, setPending] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  const confirm = async () => {
    if (pending) return;
    setPending(true);
    setError(null);

    try {
      const result = await emptyFileManagerTrash(fileSpaceId);
      toast.add({
        type: "success",
        title: copy.trashEmptied,
        description: `${copy.releasedStorage}: ${formatStorageBytes(result.released_bytes, locale)}`,
      });
      onEmptied(result);
      onOpenChange(false);
    } catch {
      setError(copy.emptyTrashRetryDescription);
    } finally {
      setPending(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(next) => !pending && onOpenChange(next)}>
      <DialogContent>
        <DialogHeader>
          <div className="mb-2 flex size-10 items-center justify-center rounded-full bg-destructive/10 text-destructive">
            <KeenIcon name="trash" className="text-[19px]" />
          </div>
          <DialogTitle>{copy.emptyTrashTitle}</DialogTitle>
          <DialogDescription>{copy.emptyTrashDescription}</DialogDescription>
        </DialogHeader>

        <div className="rounded-lg border border-destructive/25 bg-destructive/5 p-3 text-xs font-semibold text-destructive">
          {copy.emptyTrashDescription}
        </div>

        {error ? (
          <p role="alert" className="text-xs leading-5 text-destructive">
            <span className="font-semibold">{copy.couldNotEmptyTrash}</span>{" "}
            {error}
          </p>
        ) : null}

        <DialogFooter>
          <Button type="button" variant="secondary" disabled={pending} onClick={() => onOpenChange(false)}>
            {copy.cancel}
          </Button>
          <Button type="button" variant="destructive" disabled={pending} onClick={() => void confirm()}>
            {pending ? copy.emptyingTrash : copy.emptyTrashConfirm}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
