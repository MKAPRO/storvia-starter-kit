"use client";

import * as React from "react";

import { BulkTrashDialog } from "@/components/file-manager/bulk-trash-dialog";
import {
  hasFileManagerNodeAction,
  hasFileManagerSpaceAction,
  isFileManagerNodeSelectable,
} from "@/components/file-manager/file-manager-capabilities";
import { resolveFileManagerAffiliation } from "@/components/file-manager/file-manager-affiliation";
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
import { StorageQuotaSummary } from "@/components/file-manager/storage-quota-summary";
import { TrashNodeDialog } from "@/components/file-manager/trash-node-dialog";
import { UploadCenterDialog } from "@/components/file-manager/upload-center-dialog";
import { useFileManagerInlineMutations } from "@/components/file-manager/use-file-manager-inline-mutations";
import { useFileManagerSelection } from "@/components/file-manager/use-file-manager-selection";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { KeenIcon } from "@/components/ui/keen-icon";
import { toast } from "@/components/ui/toast";
import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import { isApiError } from "@/lib/api/api-error";
import {
  browseFileManagerNodes,
  setFileManagerFavorite,
  type FileManagerBreadcrumb,
  type FileManagerBrowseQuery,
  type FileManagerBrowseResult,
  type FileManagerFileSpace,
  type FileManagerNode,
} from "@/lib/api/file-manager-client";
import type { StorviaLocale } from "@/lib/i18n";
import { cn } from "@/lib/utils";

type DriveBrowserProps = {
  copy: WorkspaceCopy;
  locale: StorviaLocale;
  fileSpace: FileManagerFileSpace;
  query: string;
  parentId: string | null;
  onParentChange: (parentId: string | null) => void;
  onBreadcrumbsChange: (breadcrumbs: FileManagerBreadcrumb[]) => void;
  onDirectoryFoldersChange: (folders: FileManagerNode[]) => void;
  onFileSpaceUnavailable: (fileSpaceId: string) => void;
};

type PagingState = {
  scopeKey: string;
  page: number;
};

export function DriveBrowser({
  copy,
  locale,
  fileSpace,
  query,
  parentId,
  onParentChange,
  onBreadcrumbsChange,
  onDirectoryFoldersChange,
  onFileSpaceUnavailable,
}: DriveBrowserProps) {
  const [view, setView] = React.useState<FileManagerView>("list");
  const [localQuery, setLocalQuery] = React.useState("");
  const [debouncedSearch, setDebouncedSearch] = React.useState("");
  const [sort, setSort] =
    React.useState<FileManagerBrowseQuery["sort"]>("name");
  const [direction, setDirection] =
    React.useState<FileManagerBrowseQuery["direction"]>("asc");
  const [paging, setPaging] = React.useState<PagingState>({
    scopeKey: "",
    page: 1,
  });
  const [result, setResult] = React.useState<FileManagerBrowseResult | null>(
    null,
  );
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState<string | null>(null);
  const [reloadKey, setReloadKey] = React.useState(0);
  const [moveNode, setMoveNode] = React.useState<FileManagerNode | null>(null);
  const [trashNode, setTrashNode] = React.useState<FileManagerNode | null>(null);
  const [detailsNode, setDetailsNode] = React.useState<FileManagerNode | null>(null);
  const [accessNode, setAccessNode] = React.useState<FileManagerNode | null>(null);
  const [unlockTarget, setUnlockTarget] = React.useState<ResourceUnlockTarget | null>(null);
  const [lockedParent, setLockedParent] = React.useState<ResourceUnlockTarget | null>(null);
  const [busyNodeId, setBusyNodeId] = React.useState<string | null>(null);
  const [bulkTrashOpen, setBulkTrashOpen] = React.useState(false);
  const [selectionPending, setSelectionPending] = React.useState(false);
  const [uploadCenterOpen, setUploadCenterOpen] = React.useState(false);

  const effectiveSearch = localQuery.trim() || query.trim();
  const scopeKey = `${fileSpace.id}|${parentId ?? "root"}|${debouncedSearch}|${sort}|${direction}`;
  const page = paging.scopeKey === scopeKey ? paging.page : 1;

  React.useEffect(() => {
    const timer = window.setTimeout(() => {
      setDebouncedSearch(effectiveSearch);
    }, 250);

    return () => window.clearTimeout(timer);
  }, [effectiveSearch]);

  const loadNodes = React.useCallback(
    async (signal: AbortSignal) => {
      setLoading(true);
      setError(null);
      setResult(null);
      setLockedParent(null);

      try {
        const payload = await browseFileManagerNodes(fileSpace.id, {
          parentId,
          search: debouncedSearch,
          sort,
          direction,
          page,
          perPage: 50,
          signal,
        });

        if (signal.aborted) {
          return;
        }

        setResult(payload);
        setLockedParent(null);
        onBreadcrumbsChange(payload.meta.breadcrumbs);
      } catch (loadError) {
        if (!signal.aborted) {
          if (
            parentId &&
            isApiError(loadError) &&
            loadError.code === "RESOURCE_PASSWORD_REQUIRED"
          ) {
            const target: ResourceUnlockTarget = {
              id: parentId,
              name: locale === "ar" ? "مجلد محمي" : "Protected folder",
              channel: "normal",
            };
            setLockedParent(target);
            setUnlockTarget(target);
          } else if (isFileSpaceAvailabilityError(loadError)) {
            onFileSpaceUnavailable(fileSpace.id);
          } else {
            setError(copy.couldNotLoadFiles);
          }
        }
      } finally {
        if (!signal.aborted) {
          setLoading(false);
        }
      }
    },
    [
      copy.couldNotLoadFiles,
      debouncedSearch,
      direction,
      fileSpace.id,
      locale,
      onBreadcrumbsChange,
      onFileSpaceUnavailable,
      page,
      parentId,
      sort,
    ],
  );

  React.useEffect(() => {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => {
      void loadNodes(controller.signal);
    }, 0);

    return () => {
      window.clearTimeout(timeout);
      controller.abort();
    };
  }, [loadNodes, reloadKey]);

  const {
    createFolder,
    renameState,
    cancelInlineEditing,
    startCreateFolder,
    changeCreateFolderName,
    confirmCreateFolder,
    cancelCreateFolder,
    startRename,
    changeRenameName,
    confirmRename,
    cancelRename,
  } = useFileManagerInlineMutations({
    copy,
    fileSpaceId: fileSpace.id,
    parentId,
    setResult,
  });

  const navigateToParent = React.useCallback(
    (nextParentId: string | null) => {
      cancelInlineEditing();
      onParentChange(nextParentId);
    },
    [cancelInlineEditing, onParentChange],
  );

  const updatePage = (nextPage: number) => {
    cancelInlineEditing();
    setPaging({
      scopeKey,
      page: nextPage,
    });
  };

  const activeFileSpace = result?.meta.file_space ?? fileSpace;
  const canBrowse = hasFileManagerSpaceAction(activeFileSpace, "browse");
  const canCreateFolder = hasFileManagerSpaceAction(
    activeFileSpace,
    "create_folder",
  );
  const canUpload = hasFileManagerSpaceAction(activeFileSpace, "upload_file");
  const affiliation = resolveFileManagerAffiliation(activeFileSpace, copy);
  const nodes = React.useMemo(() => result?.data ?? [], [result]);
  const pagination = result?.meta.pagination;

  React.useEffect(() => {
    onDirectoryFoldersChange(nodes.filter((node) => node.type === "folder"));
  }, [nodes, onDirectoryFoldersChange]);
  const breadcrumbs = result?.meta.breadcrumbs ?? [];
  const selectableNodes = React.useMemo(
    () => nodes.filter(isFileManagerNodeSelectable),
    [nodes],
  );
  const selection = useFileManagerSelection(
    selectableNodes,
    `${scopeKey}|page:${page}`,
  );
  const selectedNodes = selection.selectedNodes;
  const canBulkTrash =
    selectedNodes.length > 0 &&
    selectedNodes.every((node) => node.allowed_actions.includes("trash"));
  const canBulkFavorite =
    selectedNodes.length > 0 &&
    selectedNodes.every((node) => node.allowed_actions.includes("favorite"));
  const favoriteTarget = canBulkFavorite
    ? !selectedNodes.every((node) => node.is_favorite)
    : null;
  const searching = debouncedSearch !== "";
  const empty = !loading && !error && nodes.length === 0 && !createFolder;
  const activeRenameNode = renameState
    ? nodes.find((node) => node.id === renameState.nodeId) ?? null
    : null;
  const activeRename =
    renameState && activeRenameNode?.allowed_actions.includes("rename")
      ? renameState
      : null;

  React.useEffect(() => {
    if (!result) {
      return;
    }

    const timeout = window.setTimeout(() => {
      if (createFolder && !canCreateFolder) {
        cancelCreateFolder();
      }

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
    canCreateFolder,
    cancelCreateFolder,
    cancelRename,
    createFolder,
    nodes,
    renameState,
    result,
  ]);

  const handleStartCreateFolder = () => {
    if (!canCreateFolder || loading || error) {
      return;
    }

    selection.clearSelection();
    startCreateFolder();
  };

  const handleStartUpload = () => {
    if (!canUpload || loading || error) {
      return;
    }

    cancelInlineEditing();
    selection.clearSelection();
    setUploadCenterOpen(true);
  };

  const handleOpenNode = React.useCallback(
    (node: FileManagerNode) => {
      if (node.resource_access?.locked) {
        setUnlockTarget({
          id: node.id,
          name: node.name,
          channel: "normal",
          navigateAfterUnlock: node.type === "folder",
        });
        return;
      }

      if (!node.allowed_actions.includes("open")) {
        return;
      }

      if (node.type === "folder") {
        navigateToParent(node.id);
        return;
      }

      setDetailsNode(node);
    },
    [navigateToParent],
  );

  const handleToggleFavorite = React.useCallback(
    async (node: FileManagerNode) => {
      if (!node.allowed_actions.includes("favorite") || busyNodeId) {
        return;
      }

      setBusyNodeId(node.id);

      try {
        const updated = await setFileManagerFavorite(
          fileSpace.id,
          node.id,
          !node.is_favorite,
        );

        setResult((current) => replaceBrowseNode(current, updated));
        setDetailsNode((current) =>
          current?.id === updated.id ? updated : current,
        );
      } catch (favoriteError) {
        toast.add({
          type: "error",
          title: copy.couldNotFavorite,
          description: actionErrorMessage(
            favoriteError,
            copy,
            copy.couldNotFavorite,
          ),
        });
      } finally {
        setBusyNodeId(null);
      }
    },
    [busyNodeId, copy, fileSpace.id],
  );

  const handleBulkFavorite = React.useCallback(
    async (favorite: boolean) => {
      const actionableNodes = selectedNodes.filter((node) =>
        node.allowed_actions.includes("favorite"),
      );

      if (
        actionableNodes.length !== selectedNodes.length ||
        actionableNodes.length === 0 ||
        selectionPending
      ) {
        return;
      }

      setSelectionPending(true);

      const results = await Promise.allSettled(
        actionableNodes.map((node) =>
          setFileManagerFavorite(fileSpace.id, node.id, favorite),
        ),
      );
      const updatedNodes = results.flatMap((item) =>
        item.status === "fulfilled" ? [item.value] : [],
      );
      const failed = results.filter((item) => item.status === "rejected");

      if (updatedNodes.length > 0) {
        setResult((current) =>
          updatedNodes.reduce<FileManagerBrowseResult | null>(
            (next, updated) => replaceBrowseNode(next, updated),
            current,
          ),
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
    },
    [
      copy.couldNotFavorite,
      copy.selectionActionPartial,
      fileSpace.id,
      selectedNodes,
      selection,
      selectionPending,
    ],
  );

  const handleBulkTrashed = React.useCallback(
    (trashedNodes: FileManagerNode[]) => {
      const trashedIds = new Set(trashedNodes.map((node) => node.id));
      setResult((current) =>
        Array.from(trashedIds).reduce<FileManagerBrowseResult | null>(
          (next, nodeId) => removeBrowseNode(next, nodeId),
          current,
        ),
      );
      setDetailsNode((current) =>
        current && trashedIds.has(current.id) ? null : current,
      );
    },
    [],
  );

  const handleMoved = React.useCallback((updated: FileManagerNode) => {
    setResult((current) => removeBrowseNode(current, updated.id));
    setMoveNode(null);
    setDetailsNode((current) =>
      current?.id === updated.id ? updated : current,
    );
  }, []);

  const handleTrashed = React.useCallback((updated: FileManagerNode) => {
    setResult((current) => removeBrowseNode(current, updated.id));
    setTrashNode(null);
    setDetailsNode((current) =>
      current?.id === updated.id ? null : current,
    );
  }, []);

  return (
    <section
      className="overflow-hidden rounded-xl border border-workspace-content-border bg-card shadow-xs"
      aria-labelledby="file-manager-list-title"
      aria-busy={loading}
    >
      <span className="sr-only" aria-live="polite">
        {loading
          ? copy.loadingFiles
          : error ?? `${pagination?.total ?? 0} ${copy.items}`}
      </span>
      <div className="flex flex-wrap items-center gap-2.5 border-b border-workspace-content-border px-4 py-3 sm:px-5">
        <div className="relative min-w-[210px] flex-1 sm:max-w-[300px]">
          <KeenIcon
            name="magnifier"
            className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-[17px] text-muted-foreground"
          />
          <Input
            value={localQuery}
            onChange={(event) => setLocalQuery(event.target.value)}
            placeholder={copy.searchPlaceholder}
            aria-label={copy.searchPlaceholder}
            className="h-9 bg-workspace-search ps-9 text-[12px] shadow-none"
          />
        </div>

        <div className="flex w-full flex-wrap items-center gap-1.5 sm:ms-auto sm:w-auto">
          {canUpload ? (
            <Button
              type="button"
              size="xs"
              variant="secondary"
              onClick={handleStartUpload}
              disabled={loading || Boolean(error)}
              className="h-8 px-2.5 text-[11px]"
            >
              <KeenIcon name="cloud-add" className="text-[15px]" />
              {copy.upload}
            </Button>
          ) : null}

          {canCreateFolder ? (
            <Button
              type="button"
              size="xs"
              onClick={handleStartCreateFolder}
              disabled={loading || Boolean(error) || Boolean(createFolder)}
              className="h-8 px-2.5 text-[11px]"
            >
              <KeenIcon name="folder-added" className="text-[15px]" />
              {copy.newFolder}
            </Button>
          ) : null}

          <DropdownMenu>
            <DropdownMenuTrigger
              render={
                <Button
                  type="button"
                  variant="ghost"
                  size="xs"
                  className="h-8 px-2.5 text-[11px]"
                />
              }
            >
              <KeenIcon name="arrow-up-down" className="text-[15px]" />
              {copy.sort}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-44">
              <DropdownMenuItem onClick={() => setSort("name")}>
                <KeenIcon
                  name={sort === "name" ? "check" : "text"}
                  className="text-[15px]"
                />
                {copy.sortByName}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => setSort("updated_at")}>
                <KeenIcon
                  name={sort === "updated_at" ? "check" : "time"}
                  className="text-[15px]"
                />
                {copy.sortByModified}
              </DropdownMenuItem>
              <DropdownMenuItem
                onClick={() =>
                  setDirection((current) =>
                    current === "asc" ? "desc" : "asc",
                  )
                }
              >
                <KeenIcon
                  name={direction === "asc" ? "arrow-up" : "arrow-down"}
                  className="text-[15px]"
                />
                {direction === "asc" ? copy.ascending : copy.descending}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <div className="flex rounded-md border border-border bg-background p-0.5">
            <button
              type="button"
              title={copy.listView}
              aria-label={copy.listView}
              aria-pressed={view === "list"}
              onClick={() => setView("list")}
              className={cn(
                "flex size-7 items-center justify-center rounded-[5px] text-muted-foreground transition-colors",
                view === "list" &&
                  "bg-accent text-accent-foreground shadow-xs",
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
                view === "grid" &&
                  "bg-accent text-accent-foreground shadow-xs",
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

      <StorageQuotaSummary
        quota={activeFileSpace.quota}
        locale={locale}
        copy={copy}
      />

      <div
        className="flex flex-wrap items-center gap-1.5 border-b border-workspace-content-border bg-muted/20 px-4 py-2 sm:px-5"
        aria-label={copy.accessSummary}
      >
        <span className="me-0.5 text-[10.5px] font-medium text-muted-foreground">
          {copy.accessSummary}
        </span>

        {canBrowse ? (
          <span className="inline-flex min-h-6 items-center gap-1 rounded-full border border-border bg-background px-2 text-[10px] font-semibold text-foreground">
            <KeenIcon name="eye" className="text-[12px] text-brand-blue" />
            {copy.viewAccess}
          </span>
        ) : null}

        {canCreateFolder ? (
          <span className="inline-flex min-h-6 items-center gap-1 rounded-full border border-border bg-background px-2 text-[10px] font-semibold text-foreground">
            <KeenIcon name="folder-added" className="text-[12px] text-brand-blue" />
            {copy.createFolderAccess}
          </span>
        ) : null}

        {canUpload ? (
          <span className="inline-flex min-h-6 items-center gap-1 rounded-full border border-border bg-background px-2 text-[10px] font-semibold text-foreground">
            <KeenIcon name="cloud-add" className="text-[12px] text-brand-blue" />
            {copy.uploadAccess}
          </span>
        ) : null}
      </div>

      <FileManagerSelectionToolbar
        count={selection.selectedCount}
        locale={locale}
        copy={copy}
        allSelected={selection.allSelected}
        pending={selectionPending}
        canTrash={canBulkTrash}
        favoriteTarget={favoriteTarget}
        onSelectAll={() => selection.toggleAll(true)}
        onClear={selection.clearSelection}
        onTrash={() => setBulkTrashOpen(true)}
        onSetFavorite={(favorite) => void handleBulkFavorite(favorite)}
      />

      <div className="flex flex-wrap items-center gap-2 border-b border-workspace-content-border px-4 py-2.5 sm:px-5">
        <nav
          aria-label={copy.breadcrumb}
          className="flex min-w-0 flex-wrap items-center gap-1.5 text-[11px] font-medium text-muted-foreground"
        >
          <button
            type="button"
            onClick={() => navigateToParent(null)}
            className={cn(
              "rounded-md px-2 py-1 transition-colors hover:bg-brand-blue/10 hover:text-brand-blue",
              breadcrumbs.length === 0 &&
                "bg-brand-blue/10 text-brand-blue",
            )}
          >
            {copy.myFiles}
          </button>

          {breadcrumbs.map((crumb, index) => (
            <React.Fragment key={crumb.id}>
              <KeenIcon
                name="right"
                className="text-[10px] text-muted-foreground/60 rtl:rotate-180"
              />
              <button
                type="button"
                onClick={() => navigateToParent(crumb.id)}
                aria-current={
                  index === breadcrumbs.length - 1 ? "page" : undefined
                }
                className={cn(
                  "max-w-44 truncate rounded-md px-1.5 py-1 transition-colors hover:bg-accent hover:text-accent-foreground",
                  index === breadcrumbs.length - 1 && "text-foreground",
                )}
              >
                {crumb.name}
              </button>
            </React.Fragment>
          ))}
        </nav>

        <span className="ms-auto rounded-full bg-brand-blue px-2 py-0.5 text-[10px] font-semibold text-white">
          {pagination?.total ?? 0} {copy.items}
        </span>
      </div>

      <div className="flex min-h-12 items-center justify-between gap-2 border-b border-workspace-content-border px-4 py-2 sm:px-5">
        <h2 id="file-manager-list-title" className="text-[12px] font-semibold">
          {breadcrumbs.at(-1)?.name ?? copy.myFiles}
        </h2>

        {loading ? (
          <span className="inline-flex items-center gap-1.5 text-[11px] text-muted-foreground">
            <KeenIcon name="loading" className="animate-spin text-[14px]" />
            {copy.loadingFiles}
          </span>
        ) : null}
      </div>

      {error ? (
        <div className="flex min-h-72 flex-col items-center justify-center px-6 text-center">
          <div className="flex size-12 items-center justify-center rounded-xl bg-destructive/10 text-destructive">
            <KeenIcon name="information-2" className="text-[21px]" />
          </div>
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
        <div className="flex min-h-72 items-center justify-center">
          <div className="inline-flex items-center gap-2 text-sm text-muted-foreground">
            <KeenIcon
              name="loading"
              className="animate-spin text-[17px] text-brand-blue"
            />
            {copy.loadingFiles}
          </div>
        </div>
      ) : lockedParent ? (
        <div className="flex min-h-72 flex-col items-center justify-center px-6 text-center">
          <div className="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
            <KeenIcon name="lock" variant="outline" className="text-[22px]" />
          </div>
          <h3 className="mt-3 text-sm font-semibold">
            {locale === "ar" ? "هذا المجلد محمي بكلمة مرور" : "This folder is password protected"}
          </h3>
          <Button
            type="button"
            variant="secondary"
            size="sm"
            className="mt-4"
            onClick={() => setUnlockTarget(lockedParent)}
          >
            <KeenIcon name="lock-2" className="text-[16px]" />
            {locale === "ar" ? "فتح المجلد" : "Unlock folder"}
          </Button>
        </div>
      ) : empty ? (
        <div className="flex min-h-72 flex-col items-center justify-center px-6 text-center">
          <div className="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
            <KeenIcon
              name={searching ? "magnifier" : "folder"}
              variant="outline"
              className="text-[22px]"
            />
          </div>
          <h3 className="mt-3 text-sm font-semibold">
            {searching ? copy.noResults : copy.emptyFolder}
          </h3>
          <p className="mt-1 max-w-sm text-xs text-muted-foreground">
            {searching
              ? copy.noResultsDescription
              : copy.emptyFolderDescription}
          </p>
        </div>
      ) : (
        <FileManagerNodeList
          fileSpaceId={fileSpace.id}
          nodes={nodes}
          copy={copy}
          locale={locale}
          view={view}
          affiliation={affiliation}
          busyNodeId={busyNodeId}
          selection={{
            selectedIds: selection.selectedIds,
            allSelected: selection.allSelected,
            partiallySelected: selection.partiallySelected,
            isSelectable: isFileManagerNodeSelectable,
            onToggleNode: selection.toggleNode,
            onToggleAll: selection.toggleAll,
          }}
          onOpenNode={handleOpenNode}
          onUnlockNode={(node) =>
            setUnlockTarget({
              id: node.id,
              name: node.name,
              channel: "normal",
              navigateAfterUnlock: false,
            })
          }
          onManageAccess={(node) => {
            selection.clearSelection();
            setAccessNode(node);
          }}
          createFolder={
            createFolder
              ? {
                  value: createFolder.value,
                  error: createFolder.error,
                  pending: createFolder.pending,
                  onChange: changeCreateFolderName,
                  onConfirm: confirmCreateFolder,
                  onCancel: cancelCreateFolder,
                }
              : null
          }
          rename={
            activeRename
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
          onStartRename={(node) => {
            selection.clearSelection();
            startRename(node);
          }}
          onMoveNode={(node) => {
            if (!hasFileManagerNodeAction(node, "move")) {
              return;
            }

            selection.clearSelection();
            setMoveNode(node);
          }}
          onToggleFavorite={(node) => void handleToggleFavorite(node)}
          onTrashNode={(node) => {
            if (!hasFileManagerNodeAction(node, "trash")) {
              return;
            }

            selection.clearSelection();
            setTrashNode(node);
          }}
        />
      )}

      {pagination && pagination.last_page > 1 ? (
        <div className="flex items-center justify-between gap-3 border-t border-workspace-content-border px-4 py-3 sm:px-5">
          <span className="text-[11px] text-muted-foreground">
            {pagination.from ?? 0}–{pagination.to ?? 0} / {pagination.total}
          </span>
          <div className="flex items-center gap-1.5">
            <Button
              type="button"
              variant="secondary"
              size="xs"
              disabled={pagination.current_page <= 1}
              onClick={() =>
                updatePage(Math.max(1, pagination.current_page - 1))
              }
            >
              <KeenIcon
                name="left"
                className="text-[12px] rtl:rotate-180"
              />
              {copy.previousPage}
            </Button>
            <Button
              type="button"
              variant="secondary"
              size="xs"
              disabled={pagination.current_page >= pagination.last_page}
              onClick={() =>
                updatePage(
                  Math.min(
                    pagination.last_page,
                    pagination.current_page + 1,
                  ),
                )
              }
            >
              {copy.nextPage}
              <KeenIcon
                name="right"
                className="text-[12px] rtl:rotate-180"
              />
            </Button>
          </div>
        </div>
      ) : null}

      <UploadCenterDialog
        open={uploadCenterOpen}
        onOpenChange={setUploadCenterOpen}
        fileSpaceId={fileSpace.id}
        parentId={parentId}
        quota={activeFileSpace.quota}
        locale={locale}
        copy={copy}
        onUploadsChanged={() => setReloadKey((current) => current + 1)}
        onFileSpaceUnavailable={onFileSpaceUnavailable}
      />
      <BulkTrashDialog
        nodes={selectedNodes}
        fileSpaceId={fileSpace.id}
        copy={copy}
        locale={locale}
        open={bulkTrashOpen}
        onOpenChange={setBulkTrashOpen}
        onTrashed={handleBulkTrashed}
      />
      <MoveNodeDialog
        key={moveNode?.id ?? "move-dialog"}
        node={moveNode}
        fileSpaceId={fileSpace.id}
        copy={copy}
        onOpenChange={(open) => {
          if (!open) setMoveNode(null);
        }}
        onMoved={handleMoved}
      />
      <TrashNodeDialog
        key={trashNode?.id ?? "trash-dialog"}
        node={trashNode}
        fileSpaceId={fileSpace.id}
        copy={copy}
        onOpenChange={(open) => {
          if (!open) setTrashNode(null);
        }}
        onTrashed={handleTrashed}
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
        fileSpaceId={fileSpace.id}
        locale={locale}
        onOpenChange={(open) => {
          if (!open) setAccessNode(null);
        }}
        onChanged={() => setReloadKey((current) => current + 1)}
      />
      <ResourceUnlockDialog
        key={unlockTarget ? `${unlockTarget.channel}:${unlockTarget.id}` : "unlock-closed"}
        target={unlockTarget}
        fileSpaceId={fileSpace.id}
        locale={locale}
        onOpenChange={(open) => {
          if (!open) setUnlockTarget(null);
        }}
        onUnlocked={(fullyUnlocked) => {
          setReloadKey((current) => current + 1);

          if (fullyUnlocked && unlockTarget?.navigateAfterUnlock) {
            const targetId = unlockTarget.id;
            setUnlockTarget(null);
            navigateToParent(targetId);
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

  return fallback;
}

function replaceBrowseNode(
  result: FileManagerBrowseResult | null,
  updated: FileManagerNode,
): FileManagerBrowseResult | null {
  if (!result) {
    return result;
  }

  return {
    ...result,
    data: result.data.map((node) =>
      node.id === updated.id ? updated : node,
    ),
  };
}

function removeBrowseNode(
  result: FileManagerBrowseResult | null,
  nodeId: string,
): FileManagerBrowseResult | null {
  if (!result || !result.data.some((node) => node.id === nodeId)) {
    return result;
  }

  const data = result.data.filter((node) => node.id !== nodeId);
  const total = Math.max(0, result.meta.pagination.total - 1);
  const from = data.length === 0 ? null : result.meta.pagination.from;
  const to = from === null ? null : from + data.length - 1;
  const lastPage = Math.max(1, Math.ceil(total / result.meta.pagination.per_page));

  return {
    ...result,
    data,
    meta: {
      ...result.meta,
      pagination: {
        ...result.meta.pagination,
        total,
        last_page: lastPage,
        from,
        to,
      },
    },
  };
}
