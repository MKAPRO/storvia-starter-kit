import { Suspense } from "react";

import { WorkspaceShell } from "@/components/workspace/workspace-shell";

export default function WorkspaceLayout() {
  return (
    <Suspense
      fallback={
        <div className="min-h-screen bg-workspace-background" aria-hidden="true" />
      }
    >
      <WorkspaceShell />
    </Suspense>
  );
}
