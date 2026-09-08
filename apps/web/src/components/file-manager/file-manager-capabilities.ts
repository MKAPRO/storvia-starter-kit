import type {
  FileManagerFileSpace,
  FileManagerNode,
  FileManagerNodeAction,
  FileManagerSpaceAction,
} from "@/lib/api/file-manager-client";

export function hasFileManagerSpaceAction(
  fileSpace: FileManagerFileSpace,
  action: FileManagerSpaceAction,
): boolean {
  return fileSpace.allowed_actions.includes(action);
}

export function hasFileManagerNodeAction(
  node: FileManagerNode,
  action: FileManagerNodeAction,
): boolean {
  return node.allowed_actions.includes(action);
}

export function isFileManagerNodeSelectable(node: FileManagerNode): boolean {
  return (
    hasFileManagerNodeAction(node, "favorite") ||
    hasFileManagerNodeAction(node, "trash") ||
    hasFileManagerNodeAction(node, "restore")
  );
}
