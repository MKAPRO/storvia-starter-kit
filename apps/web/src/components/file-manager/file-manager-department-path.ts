import type { FileManagerFileSpace } from "@/lib/api/file-manager-client";

export function resolveDepartmentPathNames(
  fileSpace: FileManagerFileSpace,
): string[] {
  if (fileSpace.type !== "department") {
    return [];
  }

  const sourcePath =
    fileSpace.department_navigation_path.length > 0
      ? fileSpace.department_navigation_path
      : fileSpace.department_path;
  const path = sourcePath
    .map((segment) => segment.name.trim())
    .filter((name) => name !== "");

  if (path.length > 0) {
    return path;
  }

  return fileSpace.department_name ? [fileSpace.department_name] : [];
}

export function resolveDepartmentPathLabel(
  fileSpace: FileManagerFileSpace,
): string | null {
  const path = resolveDepartmentPathNames(fileSpace);

  return path.length > 0 ? path.join(" \\ ") : null;
}
