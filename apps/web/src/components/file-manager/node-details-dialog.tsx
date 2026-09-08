"use client";

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
import { hasFileManagerNodeAction } from "@/components/file-manager/file-manager-capabilities";
import {
  NodeIcon,
  formatFileManagerBytes,
  formatFileManagerDate,
} from "@/components/file-manager/file-manager-node-list";
import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import type { FileManagerNode } from "@/lib/api/file-manager-client";
import type { StorviaLocale } from "@/lib/i18n";

type NodeDetailsDialogProps = {
  node: FileManagerNode | null;
  copy: WorkspaceCopy;
  locale: StorviaLocale;
  onOpenChange: (open: boolean) => void;
};

export function NodeDetailsDialog({
  node,
  copy,
  locale,
  onOpenChange,
}: NodeDetailsDialogProps) {
  return (
    <Dialog open={node !== null} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md" showCloseButton={false}>
        <DialogHeader>
          <div className="flex items-center gap-3">
            <NodeIcon node={node} large />
            <div className="min-w-0">
              <DialogTitle>
                <BidiText className="block truncate">
                  {node?.name ?? copy.details}
                </BidiText>
              </DialogTitle>
              <DialogDescription className="mt-1">
                {node?.type === "folder" ? copy.folder : copy.file}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        {node ? (
          <dl className="grid grid-cols-[110px_minmax(0,1fr)] gap-x-4 gap-y-3 rounded-lg border border-workspace-content-border bg-background p-4 text-xs">
            <dt className="text-muted-foreground">{copy.owner}</dt>
            <dd className="truncate font-medium">{node.owner?.name ?? "—"}</dd>
            <dt className="text-muted-foreground">{copy.size}</dt>
            <dd className="font-medium">
              {node.type === "file"
                ? formatFileManagerBytes(node.file?.size ?? null)
                : "—"}
            </dd>
            <dt className="text-muted-foreground">{copy.type}</dt>
            <dd className="font-medium">
              {node.type === "folder"
                ? copy.folder
                : node.file?.extension?.toUpperCase() || copy.file}
            </dd>
            <dt className="text-muted-foreground">{copy.modified}</dt>
            <dd className="font-medium">
              {formatFileManagerDate(node.updated_at, locale)}
            </dd>
            <dt className="text-muted-foreground">{copy.accessSummary}</dt>
            <dd>
              <NodeAllowedActions node={node} copy={copy} />
            </dd>
          </dl>
        ) : null}

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
          >
            {copy.close}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function NodeAllowedActions({
  node,
  copy,
}: {
  node: FileManagerNode;
  copy: WorkspaceCopy;
}) {
  const actions: Array<{ key: string; label: string }> = [];

  if (hasFileManagerNodeAction(node, "open")) {
    actions.push({ key: "open", label: copy.viewAccess });
  }

  if (hasFileManagerNodeAction(node, "rename")) {
    actions.push({ key: "rename", label: copy.rename });
  }

  if (hasFileManagerNodeAction(node, "move")) {
    actions.push({ key: "move", label: copy.moveToFolder });
  }

  if (hasFileManagerNodeAction(node, "favorite")) {
    actions.push({
      key: "favorite",
      label: node.is_favorite
        ? copy.removeFromFavorites
        : copy.addToFavorites,
    });
  }

  if (hasFileManagerNodeAction(node, "trash")) {
    actions.push({ key: "trash", label: copy.deleteItem });
  }

  if (hasFileManagerNodeAction(node, "restore")) {
    actions.push({ key: "restore", label: copy.restore });
  }

  return (
    <div className="flex flex-wrap gap-1.5">
      {actions.map((action) => (
        <span
          key={action.key}
          className="inline-flex min-h-6 items-center rounded-full border border-border bg-muted/35 px-2 text-[10px] font-semibold text-foreground"
        >
          {action.label}
        </span>
      ))}
    </div>
  );
}
