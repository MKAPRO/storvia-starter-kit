"use client";

import * as React from "react";

import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import { isApiError } from "@/lib/api/api-error";
import {
  createFileManagerFolder,
  updateFileManagerNode,
  type FileManagerBrowseResult,
  type FileManagerNode,
} from "@/lib/api/file-manager-client";

type CreateFolderState = {
  scopeParentId: string | null;
  value: string;
  error: string | null;
  pending: boolean;
};

type RenameState = {
  nodeId: string;
  value: string;
  originalName: string;
  selectionEnd: number;
  error: string | null;
  pending: boolean;
};

type UseFileManagerInlineMutationsOptions = {
  copy: WorkspaceCopy;
  fileSpaceId: string;
  parentId: string | null;
  setResult?: React.Dispatch<
    React.SetStateAction<FileManagerBrowseResult | null>
  >;
  onNodeUpdated?: (node: FileManagerNode) => void;
};

export function useFileManagerInlineMutations({
  copy,
  fileSpaceId,
  parentId,
  setResult,
  onNodeUpdated,
}: UseFileManagerInlineMutationsOptions) {
  const [createFolderState, setCreateFolderState] =
    React.useState<CreateFolderState | null>(null);
  const [renameState, setRenameState] = React.useState<RenameState | null>(null);

  const createFolder =
    createFolderState?.scopeParentId === parentId ? createFolderState : null;

  const cancelInlineEditing = React.useCallback(() => {
    setCreateFolderState(null);
    setRenameState(null);
  }, []);

  const startCreateFolder = React.useCallback(() => {
    setRenameState(null);
    setCreateFolderState({
      scopeParentId: parentId,
      value: "",
      error: null,
      pending: false,
    });
  }, [parentId]);

  const changeCreateFolderName = React.useCallback((value: string) => {
    setCreateFolderState((current) =>
      current
        ? {
            ...current,
            value,
            error: null,
          }
        : current,
    );
  }, []);

  const confirmCreateFolder = React.useCallback(async () => {
    if (!createFolder || createFolder.pending) {
      return;
    }

    const validationError = validateNodeName(createFolder.value, copy);

    if (validationError) {
      setCreateFolderState((state) =>
        state ? { ...state, error: validationError } : state,
      );
      return;
    }

    const name = createFolder.value.trim();
    const mutationParentId = parentId;

    setCreateFolderState((state) =>
      state ? { ...state, value: name, error: null, pending: true } : state,
    );

    try {
      const created = await createFileManagerFolder(fileSpaceId, {
        name,
        parent_id: mutationParentId,
      });

      setResult?.((currentResult) => {
        if (!currentResult) {
          return currentResult;
        }

        const resultParentId = currentResult.meta.parent?.id ?? null;

        return resultParentId === mutationParentId
          ? insertCreatedNode(currentResult, created)
          : currentResult;
      });
      setCreateFolderState(null);
    } catch (mutationError) {
      setCreateFolderState((state) =>
        state
          ? {
              ...state,
              pending: false,
              error: mutationErrorMessage(
                mutationError,
                copy,
                copy.couldNotCreateFolder,
              ),
            }
          : state,
      );
    }
  }, [copy, createFolder, fileSpaceId, parentId, setResult]);

  const startRename = React.useCallback((node: FileManagerNode) => {
    if (!node.allowed_actions.includes("rename")) {
      return;
    }

    setCreateFolderState(null);
    setRenameState({
      nodeId: node.id,
      value: node.name,
      originalName: node.name,
      selectionEnd: preferredRenameSelectionEnd(node),
      error: null,
      pending: false,
    });
  }, []);

  const changeRenameName = React.useCallback((value: string) => {
    setRenameState((current) =>
      current
        ? {
            ...current,
            value,
            error: null,
          }
        : current,
    );
  }, []);

  const confirmRename = React.useCallback(async () => {
    if (!renameState || renameState.pending) {
      return;
    }

    const validationError = validateNodeName(renameState.value, copy);

    if (validationError) {
      setRenameState((state) =>
        state ? { ...state, error: validationError } : state,
      );
      return;
    }

    const name = renameState.value.trim();

    if (name === renameState.originalName) {
      setRenameState(null);
      return;
    }

    setRenameState((state) =>
      state ? { ...state, value: name, error: null, pending: true } : state,
    );

    try {
      const updated = await updateFileManagerNode(
        fileSpaceId,
        renameState.nodeId,
        { name },
      );

      setResult?.((currentResult) =>
        currentResult
          ? {
              ...currentResult,
              data: currentResult.data.map((node) =>
                node.id === updated.id ? updated : node,
              ),
            }
          : currentResult,
      );
      onNodeUpdated?.(updated);
      setRenameState(null);
    } catch (mutationError) {
      setRenameState((state) =>
        state
          ? {
              ...state,
              pending: false,
              error: mutationErrorMessage(
                mutationError,
                copy,
                copy.couldNotRename,
              ),
            }
          : state,
      );
    }
  }, [copy, fileSpaceId, onNodeUpdated, renameState, setResult]);

  const cancelCreateFolder = React.useCallback(() => {
    setCreateFolderState(null);
  }, []);

  const cancelRename = React.useCallback(() => {
    setRenameState(null);
  }, []);

  return {
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
  };
}

function validateNodeName(value: string, copy: WorkspaceCopy): string | null {
  const name = value.trim();

  if (name === "") {
    return copy.nameRequired;
  }

  if (name.length > 255) {
    return copy.nameTooLong;
  }

  return null;
}

function mutationErrorMessage(
  error: unknown,
  copy: WorkspaceCopy,
  fallback: string,
): string {
  if (!isApiError(error)) {
    return fallback;
  }

  const nameErrors = error.details?.fields?.name;

  if (
    error.code === "VALIDATION_FAILED" &&
    Array.isArray(nameErrors) &&
    nameErrors.length > 0
  ) {
    return copy.nameUnavailable;
  }

  if (error.code === "RESOURCE_CONFLICT") {
    return copy.nameUnavailable;
  }

  if (error.code === "ACCESS_DENIED") {
    return copy.actionNotAllowed;
  }

  if (error.code === "NETWORK_ERROR") {
    return copy.networkActionFailed;
  }

  return fallback;
}

function insertCreatedNode(
  result: FileManagerBrowseResult,
  created: FileManagerNode,
): FileManagerBrowseResult {
  const { pagination } = result.meta;
  const data = [created, ...result.data].slice(0, pagination.per_page);
  const total = pagination.total + 1;
  const from = pagination.from ?? (data.length > 0 ? 1 : null);
  const to = from === null ? null : from + data.length - 1;
  const lastPage = Math.max(1, Math.ceil(total / pagination.per_page));

  return {
    ...result,
    data,
    meta: {
      ...result.meta,
      pagination: {
        ...pagination,
        total,
        last_page: lastPage,
        from,
        to,
      },
    },
  };
}

function preferredRenameSelectionEnd(node: FileManagerNode): number {
  if (node.type !== "file") {
    return node.name.length;
  }

  const extension = node.file?.extension?.trim();

  if (!extension) {
    return node.name.length;
  }

  const suffix = `.${extension}`;

  return node.name.toLowerCase().endsWith(suffix.toLowerCase())
    ? Math.max(0, node.name.length - suffix.length)
    : node.name.length;
}
