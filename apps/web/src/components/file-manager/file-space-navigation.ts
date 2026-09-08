import type { FileManagerFileSpace } from "@/lib/api/file-manager-client";
import type { StorviaLocale } from "@/lib/i18n";

export type DepartmentSpaceGroup = {
  key: string;
  name: string;
  rootSpace: FileManagerFileSpace | null;
  sections: FileManagerFileSpace[];
};

export function departmentNavigationPath(space: FileManagerFileSpace) {
  return space.department_navigation_path.length > 0
    ? space.department_navigation_path
    : space.department_path;
}

export function departmentRoot(space: FileManagerFileSpace) {
  const path = departmentNavigationPath(space);
  const first = path[0];

  return {
    key: first?.id ?? space.department_id ?? space.id,
    name: first?.name ?? space.department_name ?? "",
  };
}

export function departmentLeafLabel(space: FileManagerFileSpace) {
  const path = departmentNavigationPath(space);

  if (path.length > 1) {
    return path
      .slice(1)
      .map((segment) => segment.name)
      .join(" \\ ");
  }

  return space.department_name ?? "";
}

export function groupDepartmentSpaces(
  spaces: FileManagerFileSpace[],
  locale: StorviaLocale,
): DepartmentSpaceGroup[] {
  const groups = new Map<string, DepartmentSpaceGroup>();

  for (const space of spaces) {
    const root = departmentRoot(space);
    const path = departmentNavigationPath(space);
    const existing = groups.get(root.key) ?? {
      key: root.key,
      name: root.name,
      rootSpace: null,
      sections: [],
    };

    if (path.length <= 1) {
      existing.rootSpace = space;
    } else {
      existing.sections.push(space);
    }

    groups.set(root.key, existing);
  }

  return [...groups.values()]
    .map((group) => ({
      ...group,
      sections: [...group.sections].sort((left, right) =>
        departmentLeafLabel(left).localeCompare(
          departmentLeafLabel(right),
          locale === "ar" ? "ar" : "en",
          { sensitivity: "base" },
        ),
      ),
    }))
    .sort((left, right) =>
      left.name.localeCompare(right.name, locale === "ar" ? "ar" : "en", {
        sensitivity: "base",
      }),
    );
}
