"use client";

import * as React from "react";

import type { FileManagerNode } from "@/lib/api/file-manager-client";

type SelectionState = {
  scopeKey: string;
  ids: Set<string>;
};

export function useFileManagerSelection(
  nodes: FileManagerNode[],
  scopeKey: string,
) {
  const [state, setState] = React.useState<SelectionState>(() => ({
    scopeKey,
    ids: new Set<string>(),
  }));

  const visibleIds = React.useMemo(
    () => new Set(nodes.map((node) => node.id)),
    [nodes],
  );

  const selectedIds = React.useMemo(() => {
    if (state.scopeKey !== scopeKey) {
      return new Set<string>();
    }

    return new Set(
      Array.from(state.ids).filter((nodeId) => visibleIds.has(nodeId)),
    );
  }, [scopeKey, state, visibleIds]);

  const selectedNodes = React.useMemo(
    () => nodes.filter((node) => selectedIds.has(node.id)),
    [nodes, selectedIds],
  );

  const allSelected = nodes.length > 0 && selectedIds.size === nodes.length;
  const partiallySelected = selectedIds.size > 0 && !allSelected;

  const replaceSelection = React.useCallback(
    (ids: Iterable<string>) => {
      setState({ scopeKey, ids: new Set(ids) });
    },
    [scopeKey],
  );

  const toggleNode = React.useCallback(
    (nodeId: string, checked: boolean) => {
      const next = new Set(selectedIds);

      if (checked) {
        next.add(nodeId);
      } else {
        next.delete(nodeId);
      }

      setState({ scopeKey, ids: next });
    },
    [scopeKey, selectedIds],
  );

  const toggleAll = React.useCallback(
    (checked: boolean) => {
      replaceSelection(checked ? nodes.map((node) => node.id) : []);
    },
    [nodes, replaceSelection],
  );

  const clearSelection = React.useCallback(() => {
    replaceSelection([]);
  }, [replaceSelection]);

  return {
    selectedIds,
    selectedNodes,
    selectedCount: selectedIds.size,
    allSelected,
    partiallySelected,
    toggleNode,
    toggleAll,
    clearSelection,
  };
}
