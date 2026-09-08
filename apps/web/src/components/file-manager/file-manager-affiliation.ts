import { resolveDepartmentPathNames } from "@/components/file-manager/file-manager-department-path";
import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import type { FileManagerFileSpace } from "@/lib/api/file-manager-client";

export type FileManagerAffiliation = {
  primary: string;
  secondary: string | null;
};

/**
 * Describe the authoritative namespace that owns the listed nodes.
 *
 * Personal Drive nodes do not carry an organizational department assignment;
 * presenting the user's memberships as folder ownership would be misleading.
 * Department Drive will naturally surface the FileSpace department here when
 * STAGE 15 reuses the same browser foundation.
 */
export function resolveFileManagerAffiliation(
  fileSpace: FileManagerFileSpace,
  copy: WorkspaceCopy,
): FileManagerAffiliation {
  if (fileSpace.type === "department") {
    const departmentPath = resolveDepartmentPathNames(fileSpace);
    const leaf =
      departmentPath.at(-1) ?? fileSpace.department_name ?? copy.departments;
    const ancestors = departmentPath.slice(0, -1);

    return {
      primary:
        ancestors.length > 0
          ? ancestors.join(" \\ ")
          : copy.departmentScope,
      secondary: leaf,
    };
  }

  return {
    primary: copy.personalScope,
    secondary: copy.myFiles,
  };
}
