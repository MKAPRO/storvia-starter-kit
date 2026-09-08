export type OrganizationFlatNode = {
  id: string;
  name: string;
  parent_id: string | null;
};

export type OrganizationDescendant<T extends OrganizationFlatNode> = {
  node: T;
  depth: number;
};

export function organizationAdministrations<T extends OrganizationFlatNode>(
  nodes: readonly T[],
): T[] {
  const ids = new Set(nodes.map((node) => node.id));

  return nodes.filter(
    (node) => node.parent_id === null || !ids.has(node.parent_id),
  );
}

export function organizationDescendants<T extends OrganizationFlatNode>(
  nodes: readonly T[],
  administrationId: string,
): OrganizationDescendant<T>[] {
  const childrenByParent = new Map<string, T[]>();

  for (const node of nodes) {
    if (!node.parent_id) {
      continue;
    }

    const siblings = childrenByParent.get(node.parent_id) ?? [];
    siblings.push(node);
    childrenByParent.set(node.parent_id, siblings);
  }

  const result: OrganizationDescendant<T>[] = [];
  const seen = new Set<string>([administrationId]);
  const queue = (childrenByParent.get(administrationId) ?? []).map((node) => ({
    node,
    depth: 1,
  }));

  while (queue.length > 0) {
    const current = queue.shift();
    if (!current || seen.has(current.node.id)) {
      continue;
    }

    seen.add(current.node.id);
    result.push(current);

    for (const child of childrenByParent.get(current.node.id) ?? []) {
      queue.push({ node: child, depth: current.depth + 1 });
    }
  }

  return result;
}
