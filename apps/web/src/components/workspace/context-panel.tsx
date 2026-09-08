"use client";

import { KeenIcon } from "@/components/ui/keen-icon";
import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import type {
  FileManagerBreadcrumb,
  FileManagerNode,
} from "@/lib/api/file-manager-client";
import { cn } from "@/lib/utils";

type ContextPanelProps = {
  copy: WorkspaceCopy;
  activeSection: string;
  hasFileWorkspaceAccess: boolean;
  onSectionChange: (section: string) => void;
  canGoBack: boolean;
  onBack: () => void;
  onRoot: () => void;
  onSettings: () => void;
  rootLabel: string;
  organizationPath: string[];
  breadcrumbs: FileManagerBreadcrumb[];
  directoryFolders: FileManagerNode[];
  onBreadcrumbSelect: (breadcrumbId: string) => void;
  onDirectoryFolderSelect: (folderId: string) => void;
  compact?: boolean;
};

const rowClass =
  "group flex h-9 w-full items-center gap-2.5 rounded-md px-2.5 text-sm font-medium outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring/30";

export function ContextPanel({
  copy,
  activeSection,
  hasFileWorkspaceAccess,
  onSectionChange,
  canGoBack,
  onBack,
  onRoot,
  onSettings,
  rootLabel,
  organizationPath,
  breadcrumbs,
  directoryFolders,
  onBreadcrumbSelect,
  onDirectoryFolderSelect,
  compact = false,
}: ContextPanelProps) {
  return (
    <aside
      className={cn(
        "overflow-hidden bg-card text-foreground",
        compact
          ? "w-full border-b border-workspace-content-border"
          : "sticky top-0 rounded-xl border border-workspace-content-border shadow-xs",
      )}
    >
      <div className="flex h-14 items-center gap-2 bg-workspace-rail px-3 text-white">
        <button
          type="button"
          title={copy.back}
          aria-label={copy.back}
          disabled={!canGoBack}
          onClick={onBack}
          className="flex size-7 items-center justify-center rounded-md text-white/70 transition-colors hover:bg-white/10 hover:text-white disabled:cursor-default disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-white/70"
        >
          <KeenIcon name="left" className="text-[15px] rtl:rotate-180" />
        </button>

        <p className="text-[13px] font-semibold">{copy.directory}</p>

        <div className="ms-auto flex items-center gap-1">
          <button
            type="button"
            title={copy.fileManagerHome}
            aria-label={copy.fileManagerHome}
            onClick={onRoot}
            className="flex size-7 items-center justify-center rounded-md text-white/70 transition-colors hover:bg-white/10 hover:text-white"
          >
            <KeenIcon name="folder-added" className="text-[16px]" />
          </button>

          <button
            type="button"
            title={copy.openFileManagerSettings}
            aria-label={copy.openFileManagerSettings}
            onClick={onSettings}
            className="flex size-7 items-center justify-center rounded-md text-white/70 transition-colors hover:bg-white/10 hover:text-white"
          >
            <KeenIcon name="file-added" className="text-[16px]" />
          </button>
        </div>
      </div>

      <div
        className={cn(
          "min-h-0 overflow-y-auto px-3 py-3",
          !compact && "max-h-[calc(100dvh-108px)]",
        )}
      >
        <p className="mb-2 px-2 text-[10px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
          {copy.fileManager}
        </p>

        <div className="space-y-1">
          {organizationPath.length > 0 ? (
            <div className="mb-2 rounded-lg border border-workspace-content-border bg-muted/30 px-2.5 py-2">
              <p className="text-[9.5px] font-semibold uppercase tracking-[0.06em] text-muted-foreground">
                {copy.affiliation}
              </p>
              <div
                className="mt-1 flex min-w-0 flex-wrap items-center gap-x-1 gap-y-0.5 text-[11px] font-semibold text-foreground"
                title={organizationPath.join(" \\ ")}
              >
                {organizationPath.map((segment, index) => (
                  <span
                    key={`${segment}:${index}`}
                    className="inline-flex min-w-0 items-center gap-1"
                  >
                    {index > 0 ? (
                      <span
                        aria-hidden="true"
                        className="text-muted-foreground"
                      >
                        \
                      </span>
                    ) : null}
                    <span className="max-w-[180px] truncate">{segment}</span>
                  </span>
                ))}
              </div>
            </div>
          ) : null}

          {hasFileWorkspaceAccess ? (
            <ContextNavRow
              active={activeSection === "files" && breadcrumbs.length === 0}
              icon="folder"
              label={rootLabel}
              onClick={onRoot}
            />
          ) : null}

          {hasFileWorkspaceAccess && activeSection === "files" && breadcrumbs.length > 0 ? (
            <nav
              aria-label={copy.directoryPath}
              className="relative ms-4 border-s border-workspace-content-border ps-3"
            >
              <p className="mb-1.5 text-[9.5px] font-semibold text-muted-foreground">
                {copy.directoryPath}
              </p>
              <div className="space-y-1">
                {breadcrumbs.map((breadcrumb, index) => {
                  const current = index === breadcrumbs.length - 1;

                  return (
                    <button
                      key={breadcrumb.id}
                      type="button"
                      onClick={() => onBreadcrumbSelect(breadcrumb.id)}
                      aria-current={current ? "page" : undefined}
                      title={breadcrumb.name}
                      className={cn(
                        "flex h-8 w-full min-w-0 items-center gap-2 rounded-md px-2 text-start text-[11.5px] font-medium outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring/30",
                        current
                          ? "bg-brand-blue/10 text-brand-blue"
                          : "text-muted-foreground hover:bg-accent hover:text-accent-foreground",
                      )}
                    >
                      <KeenIcon
                        name="folder"
                        variant={current ? "solid" : "outline"}
                        className="shrink-0 text-[14px]"
                      />
                      <span className="truncate">{breadcrumb.name}</span>
                    </button>
                  );
                })}
              </div>
            </nav>
          ) : null}
          {hasFileWorkspaceAccess && activeSection === "files" && directoryFolders.length > 0 ? (
            <nav
              aria-label={copy.folders}
              className="ms-4 border-s border-workspace-content-border ps-3"
            >
              <div className="space-y-0.5 py-1">
                {directoryFolders.map((folder) => (
                  <button
                    key={folder.id}
                    type="button"
                    onClick={() => onDirectoryFolderSelect(folder.id)}
                    title={folder.name}
                    className="group flex h-8 w-full min-w-0 items-center gap-2 rounded-md px-2 text-start text-[11.5px] font-medium text-muted-foreground outline-none transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring/30"
                  >
                    <span className="size-1 shrink-0 rounded-full bg-border transition-colors group-hover:bg-brand-blue" />
                    <KeenIcon
                      name="folder"
                      variant="outline"
                      className="shrink-0 text-[14px] text-brand-blue"
                    />
                    <span className="truncate">{folder.name}</span>
                    <KeenIcon
                      name="right"
                      className="ms-auto shrink-0 text-[11px] opacity-50 rtl:rotate-180"
                    />
                  </button>
                ))}
              </div>
            </nav>
          ) : null}

          {hasFileWorkspaceAccess ? (
            <>
              <ContextNavRow
                active={activeSection === "favorites"}
                icon="star"
                label={copy.favorites}
                onClick={() => onSectionChange("favorites")}
              />
              <ContextNavRow
                active={activeSection === "trash"}
                icon="trash"
                label={copy.trash}
                onClick={() => onSectionChange("trash")}
              />
              <ContextNavRow
                active={activeSection === "file-settings"}
                icon="setting-2"
                label={copy.settings}
                onClick={onSettings}
              />
            </>
          ) : null}
        </div>
      </div>
    </aside>
  );
}

function ContextNavRow({
  active,
  icon,
  label,
  onClick,
}: {
  active: boolean;
  icon: string;
  label: string;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-current={active ? "page" : undefined}
      className={cn(
        rowClass,
        active
          ? "bg-workspace-panel-active text-workspace-panel-active-foreground"
          : "text-muted-foreground hover:bg-accent hover:text-accent-foreground",
      )}
    >
      <KeenIcon
        name={icon}
        variant={active ? "solid" : "outline"}
        className={cn("text-[17px]", active && "text-brand-blue")}
      />
      <span>{label}</span>
    </button>
  );
}
