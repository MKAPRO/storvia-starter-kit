"use client";

import * as React from "react";

import { BulkTrashDialog } from "@/components/file-manager/bulk-trash-dialog";
import { EmptyTrashDialog } from "@/components/file-manager/empty-trash-dialog";
import { resolveFileManagerAffiliation } from "@/components/file-manager/file-manager-affiliation";
import { hasFileManagerNodeAction } from "@/components/file-manager/file-manager-capabilities";
import { FileManagerSelectionToolbar } from "@/components/file-manager/file-manager-selection-toolbar";
import {
  FileManagerNodeList,
  type FileManagerView,
} from "@/components/file-manager/file-manager-node-list";
import { MoveNodeDialog } from "@/components/file-manager/move-node-dialog";
import { ResourceAccessDialog } from "@/components/file-manager/resource-access-dialog";
import {
  ResourceUnlockDialog,
  type ResourceUnlockTarget,
} from "@/components/file-manager/resource-unlock-dialog";
import { NodeDetailsDialog } from "@/components/file-manager/node-details-dialog";
import { TrashNodeDialog } from "@/components/file-manager/trash-node-dialog";
import { useFileManagerInlineMutations } from "@/components/file-manager/use-file-manager-inline-mutations";
import { useFileManagerSelection } from "@/components/file-manager/use-file-manager-selection";
import { Button } from "@/components/ui/button";
import { KeenIcon } from "@/components/ui/keen-icon";
import { toast } from "@/components/ui/toast";
import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import { isApiError } from "@/lib/api/api-error";
import {
  listFavoriteFileManagerNodes,
  listTrashFileManagerNodes,
  restoreFileManagerNode,
  setFileManagerFavorite,
  type FileManagerCursorPagination,
  type FileManagerFileSpace,
  type FileManagerNode,
  type FileManagerStorageQuota,
} from "@/lib/api/file-manager-client";
import type { StorviaLocale } from "@/lib/i18n";
import { cn } from "@/lib/utils";

type SpecialNodeBrowserProps = {
  kind: "favorites" | "trash";
  fileSpace: FileManagerFileSpace;
  copy: WorkspaceCopy;
  locale: StorviaLocale;
  query: string;
  onOpenFolder: (nodeId: string) => void;
  onFileSpaceUnavailable: (fileSpaceId: string) => void;
  onQuotaChanged: (quota: FileManagerStorageQuota) => void;
};

export function SpecialNodeBrowser({
  kind,
  fileSpace,
  copy,
  locale,
  query,
  onOpenFolder,
  onFileSpaceUnavailable,
  onQuotaChanged,
}: SpecialNodeBrowserProps) {
  const [view, setView] = React.useState<FileManagerView>("list");
  const [nodes, setNodes] = React.useState<FileManagerNode[]>([]);
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState<string | null>(null);
  const [reloadKey, setReloadKey] = React.useState(0);
  const [busyNodeId, setBusyNodeId] = React.useState<string | null>(null);
  const [moveNode, setMoveNode] = React.useState<FileManagerNode | null>(null);
  const [trashNode, setTrashNode] = React.useState<FileManagerNode | null>(null);
  const [detailsNode, setDetailsNode] = React.useState<FileManagerNode | null>(null);
  const [accessNode, setAccessNode] = React.useState<FileManagerNode | null>(null);
  const [unlockTarget, setUnlockTarget] = React.useState<ResourceUnlockTarget | null>(null);
  const [bulkTrashOpen, setBulkTrashOpen] = React.useState(false);
  const [selectionPending, setSelectionPending] = React.useState(false);
  const [canEmptyTrash, setCanEmptyTrash] = React.useState(false);
  const [emptyTrashOpen, setEmptyTrashOpen] = React.useState(false);
  const [pagination, setPagination] = React.useState<FileManagerCursorPagination | null>(null);
  const fileSpaceId = fileSpace.id;
  const normalizedQuery = query.trim();
  const scopeKey = `${kind}|${fileSpaceId}|${normalizedQuery}`;
  const [paging, setPaging] = React.useState<{
    scopeKey: string;
    cursor: string | null;
    history: Array<string | null>;
  }>({
    scopeKey: "",
    cursor: null,
    history: [],
  });
  const activePaging =
    paging.scopeKey === scopeKey
      ? paging
      : { scopeKey, cursor: null, history: [] as Array<string | null> };
  const cursor = activePaging.cursor;
  const affiliation = resolveFileManagerAffiliation(fileSpace, copy);

  const {
    renameState,
    startRename,
    changeRenameName,
    confirmRename,
    cancelRename,
  } = useFileManagerInlineMutations({
    copy,
    fileSpaceId,
    parentId: null,
    onNodeUpdated: (updated) => {
      setNodes((current) =>
        current.map((item) => (item.id === updated.id ? updated : item)),
      );
    },
  });

  React.useEffect(() => {
    const controller = new AbortController();

    const load = async () => {
      setLoading(true);
      setError(null);
      setCanEmptyTrash(false);
      setPagination(null);

      try {
        if (kind === "favorites") {
          const result = await listFavoriteFileManagerNodes(fileSpaceId, {
            search: normalizedQuery,
            cursor,
            signal: controller.signal,
          });
          if (!controller.signal.aborted) {
            setNodes(result.data);
            setPagination(result.pagination);
            setCanEmptyTrash(false);
          }
        } else {
          const result = await listTrashFileManagerNodes(fileSpaceId, {
            search: normalizedQuery,
            cursor,
            signal: controller.signal,
          });
          if (!controller.signal.aborted) {
            setNodes(result.data);
            setPagination(result.pagination);
            setCanEmptyTrash(result.can_empty_trash);
          }
        }
      } catch (loadError) {
        if (!controller.signal.aborted) {
          if (isFileSpaceAvailabilityError(loadError)) {
            onFileSpaceUnavailable(fileSpaceId);
          } else {
            setError(copy.couldNotLoadFiles);
          }
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
  }, [
    copy.couldNotLoadFiles,
    cursor,
    fileSpaceId,
    kind,
    normalizedQuery,
    onFileSpaceUnavailable,
    reloadKey,
  ]);

  const visibleNodes = nodes;
  const activeRenameNode = renameState
    ? visibleNodes.find((node) => node.id === renameState.nodeId) ?? null
    : null;
  const activeRename =
    renameState && activeRenameNode?.allowed_actions.includes("rename")
      ? renameState
      : null;
  const isNodeSelectable = React.useCallback(
    (node: FileManagerNode) =>
      kind === "favorites"
        ? node.allowed_actions.includes("favorite") ||
          node.allowed_actions.includes("trash")
        : node.allowed_actions.includes("restore"),
    [kind],
  );
  const selectableNodes = React.useMemo(
    () => visibleNodes.filter(isNodeSelectable),
    [isNodeSelectable, visibleNodes],
  );
  const selection = useFileManagerSelection(
    selectableNodes,
    `${scopeKey}|cursor:${cursor ?? "start"}`,
  );
  const selectedNodes = selection.selectedNodes;
  const canBulkTrash =
    kind === "favorites" &&
    selectedNodes.length > 0 &&
    selectedNodes.every((node) => node.allowed_actions.includes("trash"));
  const canBulkRestore =
    kind === "trash" &&
    selectedNodes.length > 0 &&
    selectedNodes.every((node) => node.allowed_actions.includes("restore"));
  const canBulkFavorite =
    kind === "favorites" &&
    selectedNodes.length > 0 &&
    selectedNodes.every((node) => node.allowed_actions.includes("favorite"));
  const favoriteTarget = canBulkFavorite
    ? !selectedNodes.every((node) => node.is_favorite)
    : null;

  const goToNextPage = () => {
    if (!pagination?.has_more || !pagination.next_cursor) {
      return;
    }

    selection.clearSelection();
    setPaging((current) => {
      const currentPage =
        current.scopeKey === scopeKey
          ? current
          : { scopeKey, cursor: null, history: [] as Array<string | null> };

      return {
        scopeKey,
        cursor: pagination.next_cursor,
        history: [...currentPage.history, currentPage.cursor],
      };
    });
  };

  const goToPreviousPage = () => {
    selection.clearSelection();
    setPaging((current) => {
      const currentPage =
        current.scopeKey === scopeKey
          ? current
          : { scopeKey, cursor: null, history: [] as Array<string | null> };

      if (currentPage.history.length === 0) {
        return currentPage;
      }

      return {
        scopeKey,
        cursor: currentPage.history[currentPage.history.length - 1] ?? null,
        history: currentPage.history.slice(0, -1),
      };
    });
  };

  React.useEffect(() => {
    if (loading || error) {
      return;
    }

    const timeout = window.setTimeout(() => {
      if (renameState && !activeRename) {
        cancelRename();
      }

      const reconcileNode = (
        current: FileManagerNode | null,
        action: "open" | "move" | "trash",
      ) => {
        if (!current) {
          return null;
        }

        const freshNode = nodes.find((node) => node.id === current.id) ?? null;

        return freshNode?.allowed_actions.includes(action) ? freshNode : null;
      };

      setDetailsNode((current) => reconcileNode(current, "open"));
      setMoveNode((current) => reconcileNode(current, "move"));
      setTrashNode((current) => reconcileNode(current, "trash"));

      if (bulkTrashOpen && !canBulkTrash) {
        setBulkTrashOpen(false);
      }
    }, 0);

    return () => window.clearTimeout(timeout);
  }, [
    activeRename,
    bulkTrashOpen,
    canBulkTrash,
    cancelRename,
    error,
    loading,
    nodes,
    renameState,
  ]);

  const handleOpenNode = (node: FileManagerNode) => {
    if (node.resource_access?.locked) {
      setUnlockTarget({
        id: node.id,
        name: node.name,
        channel: "normal",
        navigateAfterUnlock: kind === "favorites" && node.type === "folder",
      });
      return;
    }

    if (kind !== "favorites" || !node.allowed_actions.includes("open")) {
      return;
    }

    if (node.type === "folder") {
      onOpenFolder(node.id);
      return;
    }

    setDetailsNode(node);
  };

  const handleToggleFavorite = async (node: FileManagerNode) => {
    if (!node.allowed_actions.includes("favorite") || busyNodeId) {
      return;
    }

    setBusyNodeId(node.id);

    try {
      const updated = await setFileManagerFavorite(
        fileSpaceId,
        node.id,
        !node.is_favorite,
      );

      if (kind === "favorites" && !updated.is_favorite) {
        setNodes((current) => current.filter((item) => item.id !== updated.id));
      } else {
        setNodes((current) =>
          current.map((item) => (item.id === updated.id ? updated : item)),
        );
      }
    } catch (favoriteError) {
      showActionError(favoriteError, copy, copy.couldNotFavorite);
    } finally {
      setBusyNodeId(null);
    }
  };

  const handleRestore = async (node: FileManagerNode) => {
    if (!node.allowed_actions.includes("restore") || busyNodeId) {
      return;
    }

    setBusyNodeId(node.id);

    try {
      await restoreFileManagerNode(fileSpaceId, node.id);
      setNodes((current) => current.filter((item) => item.id !== node.id));
    } catch (restoreError) {
      showActionError(restoreError, copy, copy.couldNotRestore, true);
    } finally {
      setBusyNodeId(null);
    }
  };

  const handleBulkFavorite = async (favorite: boolean) => {
    if (!canBulkFavorite || selectionPending) {
      return;
    }

    setSelectionPending(true);
    const results = await Promise.allSettled(
      selectedNodes.map((node) =>
        setFileManagerFavorite(fileSpaceId, node.id, favorite),
      ),
    );
    const updatedNodes = results.flatMap((result) =>
      result.status === "fulfilled" ? [result.value] : [],
    );
    const failed = results.filter((result) => result.status === "rejected");

    if (updatedNodes.length > 0) {
      const updatedById = new Map(updatedNodes.map((node) => [node.id, node]));
      setNodes((current) =>
        current.flatMap((node) => {
          const updated = updatedById.get(node.id);
          if (!updated) {
            return [node];
          }

          return kind === "favorites" && !updated.is_favorite ? [] : [updated];
        }),
      );
    }

    if (failed.length > 0) {
      toast.add({
        type: "error",
        title: copy.couldNotFavorite,
        description: copy.selectionActionPartial,
      });
    } else {
      selection.clearSelection();
    }

    setSelectionPending(false);
  };

  const handleBulkRestore = async () => {
    if (!canBulkRestore || selectionPending) {
      return;
    }

    setSelectionPending(true);
    const results = await Promise.allSettled(
      selectedNodes.map((node) => restoreFileManagerNode(fileSpaceId, node.id)),
    );
    const restoredIds = new Set(
      results.flatMap((result) =>
        result.status === "fulfilled" ? [result.value.id] : [],
      ),
    );
    const failed = results.filter((result) => result.status === "rejected");

    if (restoredIds.size > 0) {
      setNodes((current) => current.filter((node) => !restoredIds.has(node.id)));
    }

    if (failed.length > 0) {
      toast.add({
        type: "error",
        title: copy.couldNotRestore,
        description: copy.selectionActionPartial,
      });
    } else {
      selection.clearSelection();
    }

    setSelectionPending(false);
  };

  const handleBulkTrashed = (trashedNodes: FileManagerNode[]) => {
    const trashedIds = new Set(trashedNodes.map((node) => node.id));
    setNodes((current) => current.filter((node) => !trashedIds.has(node.id)));
  };

  const title = kind === "favorites" ? copy.favorites : copy.trash;
  const emptyTitle =
    normalizedQuery !== ""
      ? copy.noResults
      : kind === "favorites"
        ? copy.favoritesEmpty
        : copy.trashEmpty;
  const emptyDescription =
    normalizedQuery !== ""
      ? copy.noResultsDescription
      : kind === "favorites"
        ? copy.favoritesEmptyDescription
        : copy.trashEmptyDescription;

  return (
    <section
      className="overflow-hidden rounded-xl border border-workspace-content-border bg-card shadow-xs"
      aria-labelledby={`file-manager-${kind}-title`}
      aria-busy={loading}
    >
      <span className="sr-only" aria-live="polite">
        {loading ? copy.loadingFiles : error ?? `${visibleNodes.length} ${copy.items}`}
      </span>
      <div className="flex min-h-12 items-center gap-3 border-b border-workspace-content-border px-4 py-2.5 sm:px-5">
        <div className="flex size-8 items-center justify-center rounded-lg bg-brand-blue/8 text-brand-blue">
          <KeenIcon
            name={kind === "favorites" ? "star" : "trash"}
            variant="outline"
            className="text-[17px]"
          />
        </div>
        <div className="min-w-0">
          <h2 id={`file-manager-${kind}-title`} className="text-[12px] font-semibold">
            {title}
          </h2>
          <p className="text-[10px] text-muted-foreground">
            {visibleNodes.length} {copy.items}
          </p>
        </div>

        <div className="ms-auto flex items-center gap-2">
          {kind === "trash" && canEmptyTrash ? (
            <Button
              type="button"
              size="xs"
              variant="destructive"
              onClick={() => setEmptyTrashOpen(true)}
            >
              <KeenIcon name="trash" className="text-[14px]" />
              {copy.emptyTrash}
            </Button>
          ) : null}

          <div className="flex rounded-md border border-border bg-background p-0.5">
          <button
            type="button"
            title={copy.listView}
            aria-label={copy.listView}
            aria-pressed={view === "list"}
            onClick={() => setView("list")}
            className={cn(
              "flex size-7 items-center justify-center rounded-[5px] text-muted-foreground transition-colors",
              view === "list" && "bg-accent text-accent-foreground shadow-xs",
            )}
          >
            <KeenIcon
              name="row-horizontal"
              variant={view === "list" ? "solid" : "outline"}
              className="text-[15px]"
            />
          </button>
          <button
            type="button"
            title={copy.gridView}
            aria-label={copy.gridView}
            aria-pressed={view === "grid"}
            onClick={() => setView("grid")}
            className={cn(
              "flex size-7 items-center justify-center rounded-[5px] text-muted-foreground transition-colors",
              view === "grid" && "bg-accent text-accent-foreground shadow-xs",
            )}
          >
            <KeenIcon
              name="element-11"
              variant={view === "grid" ? "solid" : "outline"}
              className="text-[15px]"
            />
          </button>
          </div>
        </div>
      </div>

      <FileManagerSelectionToolbar
        count={selection.selectedCount}
        locale={locale}
        copy={copy}
        allSelected={selection.allSelected}
        pending={selectionPending}
        canTrash={canBulkTrash}
        canRestore={canBulkRestore}
        favoriteTarget={favoriteTarget}
        onSelectAll={() => selection.toggleAll(true)}
        onClear={selection.clearSelection}
        onTrash={() => setBulkTrashOpen(true)}
        onRestore={() => void handleBulkRestore()}
        onSetFavorite={(favorite) => void handleBulkFavorite(favorite)}
      />

      {error ? (
        <div className="flex min-h-72 flex-col items-center justify-center px-6 text-center">
          <KeenIcon name="information-2" className="text-[24px] text-destructive" />
          <h3 className="mt-3 text-sm font-semibold">{error}</h3>
          <Button
            type="button"
            variant="secondary"
            size="sm"
            className="mt-4"
            onClick={() => setReloadKey((current) => current + 1)}
          >
            <KeenIcon name="arrows-circle" className="text-[16px]" />
            {copy.retry}
          </Button>
        </div>
      ) : loading ? (
        <div className="flex min-h-72 items-center justify-center gap-2 text-sm text-muted-foreground">
          <KeenIcon name="loading" className="animate-spin text-[17px] text-brand-blue" />
          {copy.loadingFiles}
        </div>
      ) : visibleNodes.length === 0 ? (
        <div className="flex min-h-72 flex-col items-center justify-center px-6 text-center">
          <div className="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
            <KeenIcon
              name={normalizedQuery !== "" ? "magnifier" : kind === "favorites" ? "star" : "trash"}
              variant="outline"
              className="text-[22px]"
            />
          </div>
          <h3 className="mt-3 text-sm font-semibold">{emptyTitle}</h3>
          <p className="mt-1 max-w-sm text-xs text-muted-foreground">
            {emptyDescription}
          </p>
        </div>
      ) : (
        <FileManagerNodeList
          fileSpaceId={fileSpace.id}
          nodes={visibleNodes}
          copy={copy}
          locale={locale}
          view={view}
          affiliation={affiliation}
          busyNodeId={busyNodeId}
          selection={{
            selectedIds: selection.selectedIds,
            allSelected: selection.allSelected,
            partiallySelected: selection.partiallySelected,
            isSelectable: isNodeSelectable,
            onToggleNode: selection.toggleNode,
            onToggleAll: selection.toggleAll,
          }}
          rename={
            kind === "favorites" && activeRename
              ? {
                  nodeId: activeRename.nodeId,
                  value: activeRename.value,
                  error: activeRename.error,
                  pending: activeRename.pending,
                  selectionEnd: activeRename.selectionEnd,
                  onChange: changeRenameName,
                  onConfirm: confirmRename,
                  onCancel: cancelRename,
                }
              : null
          }
          onOpenNode={handleOpenNode}
          onUnlockNode={(node) =>
            setUnlockTarget({
              id: node.id,
              name: node.name,
              channel: "normal",
              navigateAfterUnlock: false,
            })
          }
          onManageAccess={
            kind === "favorites"
              ? (node) => {
                  selection.clearSelection();
                  setAccessNode(node);
                }
              : undefined
          }
          onStartRename={
            kind === "favorites"
              ? (node) => {
                  selection.clearSelection();
                  startRename(node);
                }
              : undefined
          }
          onMoveNode={
            kind === "favorites"
              ? (node) => {
                  if (!hasFileManagerNodeAction(node, "move")) {
                    return;
                  }

                  selection.clearSelection();
                  setMoveNode(node);
                }
              : undefined
          }
          onToggleFavorite={kind === "favorites" ? (node) => void handleToggleFavorite(node) : undefined}
          onTrashNode={
            kind === "favorites"
              ? (node) => {
                  if (!hasFileManagerNodeAction(node, "trash")) {
                    return;
                  }

                  selection.clearSelection();
                  setTrashNode(node);
                }
              : undefined
          }
          onRestoreNode={kind === "trash" ? (node) => void handleRestore(node) : undefined}
        />
      )}

      {pagination && (activePaging.history.length > 0 || pagination.has_more) ? (
        <div className="flex items-center justify-between gap-3 border-t border-workspace-content-border px-4 py-3 sm:px-5">
          <span className="text-[11px] text-muted-foreground">
            {nodes.length} {copy.items}
          </span>
          <div className="flex items-center gap-1.5">
            <Button
              type="button"
              variant="secondary"
              size="xs"
              disabled={activePaging.history.length === 0}
              onClick={goToPreviousPage}
            >
              <KeenIcon name="left" className="text-[12px] rtl:rotate-180" />
              {copy.previousPage}
            </Button>
            <Button
              type="button"
              variant="secondary"
              size="xs"
              disabled={!pagination.has_more || !pagination.next_cursor}
              onClick={goToNextPage}
            >
              {copy.nextPage}
              <KeenIcon name="right" className="text-[12px] rtl:rotate-180" />
            </Button>
          </div>
        </div>
      ) : null}

      <EmptyTrashDialog
        fileSpaceId={fileSpaceId}
        locale={locale}
        copy={copy}
        open={emptyTrashOpen}
        onOpenChange={setEmptyTrashOpen}
        onEmptied={(result) => {
          setNodes([]);
          setPagination(null);
          setCanEmptyTrash(false);
          selection.clearSelection();
          onQuotaChanged(result.quota);
        }}
      />
      <BulkTrashDialog
        nodes={selectedNodes}
        fileSpaceId={fileSpaceId}
        copy={copy}
        locale={locale}
        open={bulkTrashOpen}
        onOpenChange={setBulkTrashOpen}
        onTrashed={handleBulkTrashed}
      />
      <MoveNodeDialog
        key={moveNode?.id ?? "special-move-dialog"}
        node={moveNode}
        fileSpaceId={fileSpaceId}
        copy={copy}
        onOpenChange={(open) => {
          if (!open) setMoveNode(null);
        }}
        onMoved={(updated) => {
          setNodes((current) =>
            current.map((item) => (item.id === updated.id ? updated : item)),
          );
          setMoveNode(null);
        }}
      />
      <TrashNodeDialog
        key={trashNode?.id ?? "special-trash-dialog"}
        node={trashNode}
        fileSpaceId={fileSpaceId}
        copy={copy}
        onOpenChange={(open) => {
          if (!open) setTrashNode(null);
        }}
        onTrashed={(updated) => {
          setNodes((current) => current.filter((item) => item.id !== updated.id));
          setTrashNode(null);
        }}
      />
      <NodeDetailsDialog
        node={detailsNode}
        copy={copy}
        locale={locale}
        onOpenChange={(open) => {
          if (!open) setDetailsNode(null);
        }}
      />
      <ResourceAccessDialog
        key={accessNode?.id ?? "access-closed"}
        node={accessNode}
        fileSpaceId={fileSpaceId}
        locale={locale}
        onOpenChange={(open) => {
          if (!open) setAccessNode(null);
        }}
        onChanged={() => setReloadKey((current) => current + 1)}
      />
      <ResourceUnlockDialog
        key={unlockTarget ? `${unlockTarget.channel}:${unlockTarget.id}` : "unlock-closed"}
        target={unlockTarget}
        fileSpaceId={fileSpaceId}
        locale={locale}
        onOpenChange={(open) => {
          if (!open) setUnlockTarget(null);
        }}
        onUnlocked={(fullyUnlocked) => {
          setReloadKey((current) => current + 1);

          if (fullyUnlocked && unlockTarget?.navigateAfterUnlock) {
            const targetId = unlockTarget.id;
            setUnlockTarget(null);
            onOpenFolder(targetId);
          }
        }}
      />
    </section>
  );
}

function isFileSpaceAvailabilityError(error: unknown): boolean {
  return (
    isApiError(error) &&
    (error.code === "RESOURCE_NOT_FOUND" || error.code === "ACCESS_DENIED")
  );
}

function showActionError(
  error: unknown,
  copy: WorkspaceCopy,
  fallback: string,
  restore = false,
) {
  let description = fallback;

  if (isApiError(error)) {
    if (error.code === "NETWORK_ERROR") {
      description = copy.networkActionFailed;
    } else if (error.code === "ACCESS_DENIED") {
      description = copy.actionNotAllowed;
    } else if (restore && error.code === "VALIDATION_FAILED") {
      description = copy.restoreConflict;
    }
  }

  toast.add({
    type: "error",
    title: fallback,
    description,
  });
}
