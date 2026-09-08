"use client";

import * as React from "react";
import Image from "next/image";

import type { FileManagerAffiliation } from "@/components/file-manager/file-manager-affiliation";
import { BidiText } from "@/components/ui/bidi-text";
import { InlineNodeNameEditor } from "@/components/file-manager/inline-node-name-editor";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { KeenIcon } from "@/components/ui/keen-icon";
import { toast } from "@/components/ui/toast";
import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import { isApiError } from "@/lib/api/api-error";
import {
  downloadFileManagerFile,
  getFileManagerSpaceUploadPolicy,
  triggerFileManagerBrowserDownload,
  type FileManagerFileTypeDescriptor,
  type FileManagerNode,
} from "@/lib/api/file-manager-client";
import type { StorviaLocale } from "@/lib/i18n";
import { cn } from "@/lib/utils";

export type FileManagerView = "list" | "grid";

export type InlineCreateFolderState = {
  value: string;
  error: string | null;
  pending: boolean;
  onChange: (value: string) => void;
  onConfirm: () => void;
  onCancel: () => void;
};

export type InlineRenameState = {
  nodeId: string;
  value: string;
  error: string | null;
  pending: boolean;
  selectionEnd: number;
  onChange: (value: string) => void;
  onConfirm: () => void;
  onCancel: () => void;
};

export type FileManagerNodeSelectionState = {
  selectedIds: ReadonlySet<string>;
  allSelected: boolean;
  partiallySelected: boolean;
  isSelectable: (node: FileManagerNode) => boolean;
  onToggleNode: (nodeId: string, checked: boolean) => void;
  onToggleAll: (checked: boolean) => void;
};

type FileManagerNodeListProps = {
  nodes: FileManagerNode[];
  fileSpaceId: string;
  copy: WorkspaceCopy;
  locale: StorviaLocale;
  view: FileManagerView;
  affiliation: FileManagerAffiliation;
  createFolder?: InlineCreateFolderState | null;
  rename?: InlineRenameState | null;
  busyNodeId?: string | null;
  selection?: FileManagerNodeSelectionState | null;
  onOpenNode?: (node: FileManagerNode) => void;
  onUnlockNode?: (node: FileManagerNode) => void;
  onManageAccess?: (node: FileManagerNode) => void;
  onStartRename?: (node: FileManagerNode) => void;
  onMoveNode?: (node: FileManagerNode) => void;
  onToggleFavorite?: (node: FileManagerNode) => void;
  onTrashNode?: (node: FileManagerNode) => void;
  onRestoreNode?: (node: FileManagerNode) => void;
};

export function FileManagerNodeList({
  nodes,
  fileSpaceId,
  copy,
  locale,
  view,
  affiliation,
  createFolder = null,
  rename = null,
  busyNodeId = null,
  selection = null,
  onOpenNode,
  onUnlockNode,
  onManageAccess,
  onStartRename,
  onMoveNode,
  onToggleFavorite,
  onTrashNode,
  onRestoreNode,
}: FileManagerNodeListProps) {
  const [fileTypes, setFileTypes] = React.useState<FileManagerFileTypeDescriptor[]>([]);

  React.useEffect(() => {
    const controller = new AbortController();

    void getFileManagerSpaceUploadPolicy(fileSpaceId, controller.signal)
      .then((policy) => {
        if (!controller.signal.aborted) setFileTypes(policy.file_types);
      })
      .catch(() => {
        if (!controller.signal.aborted) setFileTypes([]);
      });

    return () => controller.abort();
  }, [fileSpaceId]);

  const fileTypeByExtension = React.useMemo(
    () => new Map(fileTypes.map((fileType) => [fileType.extension, fileType])),
    [fileTypes],
  );

  if (view === "grid") {
    return (
      <div className="grid gap-2.5 p-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        {createFolder ? (
          <article className="rounded-lg border border-brand-blue/30 bg-brand-blue/[0.025] p-3 shadow-xs">
            <NodeIcon node={null} large />
            <div className="mt-3">
              <InlineNodeNameEditor
                value={createFolder.value}
                onChange={createFolder.onChange}
                onConfirm={createFolder.onConfirm}
                onCancel={createFolder.onCancel}
                error={createFolder.error}
                pending={createFolder.pending}
                placeholder={copy.folderNamePlaceholder}
                confirmLabel={copy.confirm}
                cancelLabel={copy.cancel}
                compact
              />
            </div>
          </article>
        ) : null}

        {nodes.map((node) => {
          const renaming = rename?.nodeId === node.id;
          const busy = busyNodeId === node.id;

          return (
            <article
              key={node.id}
              className={cn(
                "group rounded-lg border border-workspace-content-border bg-background p-3 transition-colors hover:border-ring/35 hover:bg-workspace-row-hover",
                renaming && "border-brand-blue/30 bg-brand-blue/[0.025]",
                selection?.selectedIds.has(node.id) &&
                  "border-brand-blue/35 bg-brand-blue/[0.045] ring-1 ring-brand-blue/10",
              )}
            >
              <div className="flex items-start justify-between gap-2">
                <div className="flex items-start gap-2.5">
                  {selection && selection.isSelectable(node) ? (
                    <SelectionCheckbox
                      checked={selection.selectedIds.has(node.id)}
                      onCheckedChange={(checked) =>
                        selection.onToggleNode(node.id, checked)
                      }
                      label={`${copy.selectItem}: ${node.name}`}
                    />
                  ) : null}
                  <GridNodeVisual
                    node={node}
                    fileSpaceId={fileSpaceId}
                    fileType={fileTypeByExtension.get(node.file?.extension?.toLowerCase() ?? "") ?? null}
                  />
                </div>
                {!renaming ? (
                  <NodeActionsMenu
                    fileSpaceId={fileSpaceId}
                    node={node}
                    copy={copy}
                    locale={locale}
                    busy={busy}
                    onOpenNode={onOpenNode}
                    onUnlockNode={onUnlockNode}
                    onManageAccess={onManageAccess}
                    onStartRename={onStartRename}
                    onMoveNode={onMoveNode}
                    onToggleFavorite={onToggleFavorite}
                    onTrashNode={onTrashNode}
                    onRestoreNode={onRestoreNode}
                  />
                ) : null}
              </div>

              {renaming && rename ? (
                <div className="mt-3">
                  <InlineNodeNameEditor
                    value={rename.value}
                    onChange={rename.onChange}
                    onConfirm={rename.onConfirm}
                    onCancel={rename.onCancel}
                    error={rename.error}
                    pending={rename.pending}
                    placeholder={copy.name}
                    confirmLabel={copy.confirm}
                    cancelLabel={copy.cancel}
                    selectionEnd={rename.selectionEnd}
                    compact
                  />
                </div>
              ) : node.type === "folder" &&
                (node.resource_access?.locked ||
                  node.allowed_actions.includes("open")) &&
                onOpenNode ? (
                <button
                  type="button"
                  onClick={() => onOpenNode(node)}
                  className="mt-3 block max-w-full text-start outline-none focus-visible:ring-2 focus-visible:ring-ring/30"
                >
                  <BidiText className="block truncate text-[12.5px] font-semibold">
                    {node.name}
                  </BidiText>
                </button>
              ) : (
                <BidiText className="mt-3 block truncate text-[12.5px] font-semibold">
                  {node.name}
                </BidiText>
              )}

              <div className="mt-2 flex items-center justify-between gap-2 text-[10px] text-muted-foreground">
                <span>
                  {node.type === "folder"
                    ? folderMeta(node, copy)
                    : formatBytes(node.file?.size ?? null)}
                </span>
                <span>{formatDate(node.trashed_at ?? node.updated_at, locale)}</span>
              </div>

              <div className="mt-2 flex min-w-0 items-center gap-1.5 text-[10px] text-muted-foreground">
                <KeenIcon name="abstract-26" variant="outline" className="shrink-0 text-[12px]" />
                <span className="shrink-0 font-medium text-foreground/75">
                  {affiliation.primary}
                </span>
                {affiliation.secondary ? (
                  <>
                    <span aria-hidden="true">·</span>
                    <span className="truncate">{affiliation.secondary}</span>
                  </>
                ) : null}
              </div>

              {node.is_favorite && node.trashed_at === null ? (
                <div className="mt-2 flex items-center gap-1 text-[10px] font-medium text-warning">
                  <KeenIcon name="star" variant="solid" className="text-[13px]" />
                  {copy.favorites}
                </div>
              ) : null}
            </article>
          );
        })}
      </div>
    );
  }

  return (
    <div
      className="overflow-x-auto focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/30"
      role="region"
      aria-label={copy.fileManager}
      tabIndex={0}
    >
      <div className="min-w-[930px]">
        <div className="grid grid-cols-[34px_minmax(280px,1.7fr)_100px_150px_130px_150px_72px] items-center gap-3 border-b border-workspace-content-border bg-workspace-table-head px-4 py-2 text-[10px] font-semibold text-muted-foreground ltr:uppercase ltr:tracking-[0.06em] sm:px-5">
          <span className="flex items-center justify-center">
            {selection ? (
              <SelectionCheckbox
                checked={selection.allSelected}
                indeterminate={selection.partiallySelected}
                onCheckedChange={selection.onToggleAll}
                label={selection.allSelected ? copy.clearSelection : copy.selectAll}
              />
            ) : null}
          </span>
          <span>{copy.name}</span>
          <span>{copy.size}</span>
          <span>{copy.modified}</span>
          <span>{copy.owner}</span>
          <span>{copy.affiliation}</span>
          <span aria-label={copy.fileActions} />
        </div>

        {createFolder ? (
          <div className="grid min-h-[68px] grid-cols-[34px_minmax(280px,1.7fr)_100px_150px_130px_150px_72px] items-start gap-3 border-b border-brand-blue/20 bg-brand-blue/[0.025] px-4 py-2.5 text-xs sm:px-5">
            <span />
            <div className="flex min-w-0 items-start gap-3">
              <NodeIcon node={null} />
              <InlineNodeNameEditor
                value={createFolder.value}
                onChange={createFolder.onChange}
                onConfirm={createFolder.onConfirm}
                onCancel={createFolder.onCancel}
                error={createFolder.error}
                pending={createFolder.pending}
                placeholder={copy.folderNamePlaceholder}
                confirmLabel={copy.confirm}
                cancelLabel={copy.cancel}
              />
            </div>
            <span className="pt-2 text-[11px] text-muted-foreground">—</span>
            <span className="pt-2 text-[11px] text-muted-foreground">—</span>
            <span className="pt-2 text-[11px] text-muted-foreground">—</span>
            <AffiliationCell affiliation={affiliation} />
            <span />
          </div>
        ) : null}

        {nodes.map((node) => {
          const renaming = rename?.nodeId === node.id;
          const busy = busyNodeId === node.id;

          return (
            <div
              key={node.id}
              className={cn(
                "group grid min-h-[58px] grid-cols-[34px_minmax(280px,1.7fr)_100px_150px_130px_150px_72px] items-center gap-3 border-b border-workspace-content-border px-4 text-xs last:border-b-0 hover:bg-workspace-row-hover sm:px-5",
                renaming && "bg-brand-blue/[0.025]",
                selection?.selectedIds.has(node.id) &&
                  "bg-brand-blue/[0.045]",
              )}
            >
              <span className="flex items-center justify-center">
                {selection && selection.isSelectable(node) ? (
                  <SelectionCheckbox
                    checked={selection.selectedIds.has(node.id)}
                    onCheckedChange={(checked) =>
                      selection.onToggleNode(node.id, checked)
                    }
                    label={`${copy.selectItem}: ${node.name}`}
                  />
                ) : null}
              </span>
              <div className="flex min-w-0 items-center gap-3 py-2">
                <NodeIcon
                  node={node}
                  fileType={fileTypeByExtension.get(node.file?.extension?.toLowerCase() ?? "") ?? null}
                />
                <div className="min-w-0 flex-1">
                  {renaming && rename ? (
                    <InlineNodeNameEditor
                      value={rename.value}
                      onChange={rename.onChange}
                      onConfirm={rename.onConfirm}
                      onCancel={rename.onCancel}
                      error={rename.error}
                      pending={rename.pending}
                      placeholder={copy.name}
                      confirmLabel={copy.confirm}
                      cancelLabel={copy.cancel}
                      selectionEnd={rename.selectionEnd}
                    />
                  ) : (
                    <>
                      {node.type === "folder" &&
                      (node.resource_access?.locked ||
                        node.allowed_actions.includes("open")) &&
                      onOpenNode ? (
                        <button
                          type="button"
                          onClick={() => onOpenNode(node)}
                          className="block max-w-full text-start outline-none focus-visible:ring-2 focus-visible:ring-ring/30"
                        >
                          <BidiText className="block truncate text-[12.5px] font-semibold text-foreground hover:text-brand-blue">
                            {node.name}
                          </BidiText>
                        </button>
                      ) : (
                        <BidiText className="block truncate text-[12.5px] font-semibold text-foreground">
                          {node.name}
                        </BidiText>
                      )}
                      <p className="mt-0.5 text-[10px] text-muted-foreground">
                        {node.type === "folder"
                          ? folderMeta(node, copy)
                          : fileMeta(node, copy)}
                      </p>
                    </>
                  )}
                </div>
                {!renaming && node.is_favorite && node.trashed_at === null ? (
                  <KeenIcon
                    name="star"
                    variant="solid"
                    className="text-[14px] text-warning"
                  />
                ) : null}
                {!renaming && node.resource_access?.locked ? (
                  <KeenIcon
                    name="lock"
                    variant="outline"
                    className="text-[14px] text-muted-foreground"
                  />
                ) : !renaming && node.resource_access?.visibility_restricted ? (
                  <KeenIcon
                    name="shield-tick"
                    variant="outline"
                    className="text-[14px] text-muted-foreground"
                  />
                ) : null}
              </div>

              <span className="text-[11px] text-muted-foreground">
                {node.type === "file"
                  ? formatBytes(node.file?.size ?? null)
                  : "—"}
              </span>
              <span className="text-[11px] text-muted-foreground">
                {formatDate(node.trashed_at ?? node.updated_at, locale)}
              </span>
              <span className="truncate text-[11px] text-muted-foreground">
                {node.owner?.name ?? "—"}
              </span>
              <AffiliationCell affiliation={affiliation} />

              {!renaming ? (
                <div className="flex items-center justify-end">
                  <NodeActionsMenu
                    fileSpaceId={fileSpaceId}
                    node={node}
                    copy={copy}
                    locale={locale}
                    busy={busy}
                    onOpenNode={onOpenNode}
                    onUnlockNode={onUnlockNode}
                    onManageAccess={onManageAccess}
                    onStartRename={onStartRename}
                    onMoveNode={onMoveNode}
                    onToggleFavorite={onToggleFavorite}
                    onTrashNode={onTrashNode}
                    onRestoreNode={onRestoreNode}
                  />
                </div>
              ) : (
                <span />
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

function AffiliationCell({
  affiliation,
}: {
  affiliation: FileManagerAffiliation;
}) {
  return (
    <span className="min-w-0 text-[10.5px] text-muted-foreground">
      <span className="block truncate font-medium text-foreground/75">
        {affiliation.primary}
      </span>
      {affiliation.secondary ? (
        <span className="mt-0.5 block truncate text-[10px]">
          {affiliation.secondary}
        </span>
      ) : null}
    </span>
  );
}

function SelectionCheckbox({
  checked,
  indeterminate = false,
  onCheckedChange,
  label,
}: {
  checked: boolean;
  indeterminate?: boolean;
  onCheckedChange: (checked: boolean) => void;
  label: string;
}) {
  const ref = React.useRef<HTMLInputElement>(null);

  React.useLayoutEffect(() => {
    if (ref.current) {
      ref.current.indeterminate = indeterminate;
    }
  }, [indeterminate]);

  return (
    <input
      ref={ref}
      type="checkbox"
      checked={checked}
      onChange={(event) => onCheckedChange(event.target.checked)}
      aria-label={label}
      className="mt-0.5 size-4 shrink-0 cursor-pointer rounded border-border accent-brand-blue focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/30"
    />
  );
}

function fileManagerDownloadErrorMessage(
  error: unknown,
  copy: WorkspaceCopy,
): string {
  if (!isApiError(error)) {
    return copy.couldNotDownload;
  }

  if (error.code === "NETWORK_ERROR") {
    return copy.networkActionFailed;
  }

  if (error.code === "ACCESS_DENIED") {
    return copy.actionNotAllowed;
  }

  if (
    error.code === "FILE_CONTENT_UNAVAILABLE" ||
    error.code === "RESOURCE_NOT_FOUND"
  ) {
    return copy.downloadUnavailable;
  }

  return copy.couldNotDownload;
}

function NodeActionsMenu({
  fileSpaceId,
  node,
  copy,
  locale,
  busy,
  onOpenNode,
  onUnlockNode,
  onManageAccess,
  onStartRename,
  onMoveNode,
  onToggleFavorite,
  onTrashNode,
  onRestoreNode,
}: {
  fileSpaceId: string;
  node: FileManagerNode;
  copy: WorkspaceCopy;
  locale: StorviaLocale;
  busy: boolean;
  onOpenNode?: (node: FileManagerNode) => void;
  onUnlockNode?: (node: FileManagerNode) => void;
  onManageAccess?: (node: FileManagerNode) => void;
  onStartRename?: (node: FileManagerNode) => void;
  onMoveNode?: (node: FileManagerNode) => void;
  onToggleFavorite?: (node: FileManagerNode) => void;
  onTrashNode?: (node: FileManagerNode) => void;
  onRestoreNode?: (node: FileManagerNode) => void;
}) {
  const [downloadPending, setDownloadPending] = React.useState(false);
  const canOpen =
    Boolean(onOpenNode) &&
    (node.resource_access?.locked === true || node.allowed_actions.includes("open"));
  const canManageAccess =
    node.resource_access?.can_manage === true && Boolean(onManageAccess);
  const canDownload =
    node.type === "file" && node.allowed_actions.includes("download");
  const canRename =
    node.allowed_actions.includes("rename") && Boolean(onStartRename);
  const canMove = node.allowed_actions.includes("move") && Boolean(onMoveNode);
  const canFavorite =
    node.allowed_actions.includes("favorite") && Boolean(onToggleFavorite);
  const canTrash =
    node.allowed_actions.includes("trash") && Boolean(onTrashNode);
  const canRestore =
    node.allowed_actions.includes("restore") && Boolean(onRestoreNode);

  const handleDownload = async () => {
    if (!canDownload || downloadPending || busy) {
      return;
    }

    setDownloadPending(true);

    try {
      const download = await downloadFileManagerFile(
        fileSpaceId,
        node.id,
        node.name,
      );

      triggerFileManagerBrowserDownload(download);

      toast.add({
        type: "success",
        title: copy.downloadComplete,
        description: node.name,
      });
    } catch (downloadError) {
      if (
        isApiError(downloadError) &&
        downloadError.code === "RESOURCE_PASSWORD_REQUIRED" &&
        onUnlockNode
      ) {
        onUnlockNode(node);
      } else {
        toast.add({
          type: "error",
          title: copy.couldNotDownload,
          description: fileManagerDownloadErrorMessage(downloadError, copy),
        });
      }
    } finally {
      setDownloadPending(false);
    }
  };

  if (
    !canOpen &&
    !canDownload &&
    !canManageAccess &&
    !canRename &&
    !canMove &&
    !canFavorite &&
    !canTrash &&
    !canRestore
  ) {
    return null;
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        render={
          <button
            type="button"
            title={copy.fileActions}
            aria-label={`${copy.fileActions}: ${node.name}`}
            disabled={busy || downloadPending}
            className="flex size-7 items-center justify-center rounded-md text-muted-foreground opacity-75 transition-[background-color,color,opacity] hover:bg-accent hover:text-accent-foreground disabled:cursor-not-allowed disabled:opacity-45 group-hover:opacity-100"
          />
        }
      >
        <KeenIcon
          name={busy || downloadPending ? "loading" : "dots-square"}
          className={cn("text-[15px]", (busy || downloadPending) && "animate-spin")}
        />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="min-w-48">
        {canOpen ? (
          <DropdownMenuItem onClick={() => onOpenNode?.(node)}>
            <KeenIcon
              name={
                node.resource_access?.locked
                  ? "lock"
                  : node.type === "folder"
                    ? "folder"
                    : "eye"
              }
            />
            {node.resource_access?.locked
              ? locale === "ar"
                ? "فتح العنصر المحمي"
                : "Unlock protected item"
              : node.type === "folder"
                ? copy.openFolder
                : copy.viewItem}
          </DropdownMenuItem>
        ) : null}

        {canDownload ? (
          <DropdownMenuItem onClick={() => void handleDownload()}>
            <KeenIcon name="document" />
            {copy.download}
          </DropdownMenuItem>
        ) : null}

        {canFavorite ? (
          <DropdownMenuItem onClick={() => onToggleFavorite?.(node)}>
            <KeenIcon
              name="star"
              variant={node.is_favorite ? "solid" : "outline"}
            />
            {node.is_favorite ? copy.removeFromFavorites : copy.addToFavorites}
          </DropdownMenuItem>
        ) : null}

        {canManageAccess ? (
          <DropdownMenuItem onClick={() => onManageAccess?.(node)}>
            <KeenIcon name="shield-tick" />
            {locale === "ar" ? "الخصوصية والوصول" : "Privacy & access"}
          </DropdownMenuItem>
        ) : null}

        {(canRename || canMove) &&
        (canOpen || canFavorite || canManageAccess) ? (
          <DropdownMenuSeparator />
        ) : null}

        {canRename ? (
          <DropdownMenuItem onClick={() => onStartRename?.(node)}>
            <KeenIcon name="pencil" />
            {copy.rename}
          </DropdownMenuItem>
        ) : null}

        {canMove ? (
          <DropdownMenuItem onClick={() => onMoveNode?.(node)}>
            <KeenIcon name="folder-down" />
            {copy.moveToFolder}
          </DropdownMenuItem>
        ) : null}

        {canRestore ? (
          <DropdownMenuItem onClick={() => onRestoreNode?.(node)}>
            <KeenIcon name="arrows-circle" />
            {copy.restore}
          </DropdownMenuItem>
        ) : null}

        {canTrash ? (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              variant="destructive"
              onClick={() => onTrashNode?.(node)}
            >
              <KeenIcon name="trash" />
              {copy.deleteItem}
            </DropdownMenuItem>
          </>
        ) : null}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export function NodeIcon({
  node,
  large = false,
  fileType = null,
}: {
  node: FileManagerNode | null;
  large?: boolean;
  fileType?: FileManagerFileTypeDescriptor | null;
}) {
  if (node === null || node.type === "folder") {
    return (
      <span
        className={cn(
          "flex shrink-0 items-center justify-center rounded-lg bg-brand-blue/8 text-brand-blue dark:bg-brand-blue/12",
          large ? "size-11" : "size-9",
        )}
        aria-hidden="true"
      >
        <KeenIcon
          name="folder"
          variant="outline"
          className={large ? "text-[24px]" : "text-[20px]"}
        />
      </span>
    );
  }

  if (fileType?.icon_svg) {
    const source = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(fileType.icon_svg)}`;

    return (
      <span
        className={cn(
          "flex shrink-0 items-center justify-center rounded-lg bg-muted p-1.5 text-muted-foreground",
          large ? "size-11" : "size-9",
        )}
        aria-hidden="true"
      >
        <Image
          src={source}
          alt=""
          width={large ? 28 : 22}
          height={large ? 28 : 22}
          unoptimized
          className="max-h-full max-w-full object-contain"
        />
      </span>
    );
  }

  const icon = fileType ? fileTypeIconName(fileType) : fileIconName(node);

  return (
    <span
      className={cn(
        "flex shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground",
        large ? "size-11" : "size-9",
      )}
      aria-hidden="true"
    >
      <KeenIcon
        name={icon}
        variant="outline"
        className={large ? "text-[22px]" : "text-[18px]"}
      />
    </span>
  );
}

function fileTypeIconName(fileType: FileManagerFileTypeDescriptor): string {
  switch (fileType.category) {
    case "image":
      return "picture";
    case "archive":
      return "archive";
    case "spreadsheet":
      return "file-sheet";
    case "text":
      return "note-2";
    case "presentation":
      return "screen";
    default:
      return "document";
  }
}

function GridNodeVisual({ node, fileSpaceId, fileType }: { node: FileManagerNode; fileSpaceId: string; fileType: FileManagerFileTypeDescriptor | null }) {
  const canPreviewImage =
    node.type === "file" &&
    fileType?.preview_mode === "image" &&
    node.allowed_actions.includes("download") &&
    (node.file?.size ?? Number.MAX_SAFE_INTEGER) <= 10 * 1024 * 1024;

  if (!canPreviewImage) {
    return <NodeIcon node={node} large fileType={fileType} />;
  }

  return (
    <ProtectedImagePreview
      fileSpaceId={fileSpaceId}
      node={node}
      fallback={<NodeIcon node={node} large fileType={fileType} />}
    />
  );
}

function ProtectedImagePreview({ fileSpaceId, node, fallback }: { fileSpaceId: string; node: FileManagerNode; fallback: React.ReactNode }) {
  const hostRef = React.useRef<HTMLSpanElement>(null);
  const [visible, setVisible] = React.useState(false);
  const [objectUrl, setObjectUrl] = React.useState<string | null>(null);
  const [failed, setFailed] = React.useState(false);

  React.useEffect(() => {
    const host = hostRef.current;
    if (!host || visible) return;

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
          setVisible(true);
          observer.disconnect();
        }
      },
      { rootMargin: "160px" },
    );
    observer.observe(host);
    return () => observer.disconnect();
  }, [visible]);

  React.useEffect(() => {
    if (!visible) return;
    let revoked = false;
    let nextUrl: string | null = null;

    void downloadFileManagerFile(fileSpaceId, node.id, node.name)
      .then((download) => {
        if (revoked) return;
        nextUrl = URL.createObjectURL(download.blob);
        setObjectUrl(nextUrl);
      })
      .catch(() => {
        if (!revoked) setFailed(true);
      });

    return () => {
      revoked = true;
      if (nextUrl) URL.revokeObjectURL(nextUrl);
    };
  }, [fileSpaceId, node.id, node.name, visible]);

  return (
    <span ref={hostRef} className="relative flex size-11 shrink-0 overflow-hidden rounded-lg bg-muted" aria-hidden="true">
      {objectUrl && !failed ? (
        <Image src={objectUrl} alt="" fill unoptimized sizes="44px" className="object-cover" />
      ) : (
        fallback
      )}
    </span>
  );
}

function fileIconName(node: FileManagerNode): string {
  const mime = node.file?.mime_type ?? "";
  const extension = node.file?.extension?.toLowerCase() ?? "";

  if (mime.startsWith("image/")) {
    return "picture";
  }

  if (["zip", "rar", "7z", "tar", "gz"].includes(extension)) {
    return "archive";
  }

  if (["xls", "xlsx", "csv"].includes(extension)) {
    return "file-sheet";
  }

  if (["js", "ts", "tsx", "jsx", "php", "json", "md"].includes(extension)) {
    return "code";
  }

  return "document";
}

function folderMeta(node: FileManagerNode, copy: WorkspaceCopy): string {
  const count = node.children_count;

  if (count == null) {
    return copy.folder;
  }

  return `${count} ${copy.items}`;
}

function fileMeta(node: FileManagerNode, copy: WorkspaceCopy): string {
  const extension = node.file?.extension?.toUpperCase();

  return extension ? `${extension} ${copy.file}` : copy.file;
}

export function formatFileManagerBytes(value: number | null): string {
  if (value == null || value < 0) {
    return "—";
  }

  if (value < 1024) {
    return `${value} B`;
  }

  const units = ["KB", "MB", "GB", "TB"];
  let size = value / 1024;
  let unitIndex = 0;

  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024;
    unitIndex += 1;
  }

  const digits = size >= 100 ? 0 : size >= 10 ? 1 : 2;

  return `${size.toFixed(digits)} ${units[unitIndex]}`;
}

function formatBytes(value: number | null): string {
  return formatFileManagerBytes(value);
}

export function formatFileManagerDate(
  value: string | null,
  locale: StorviaLocale,
): string {
  if (!value) {
    return "—";
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return "—";
  }

  return new Intl.DateTimeFormat(locale === "ar" ? "ar-YE" : "en-US", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

function formatDate(value: string | null, locale: StorviaLocale): string {
  return formatFileManagerDate(value, locale);
}
