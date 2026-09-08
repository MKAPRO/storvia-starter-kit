"use client";

import * as React from "react";

import {
  departmentLeafLabel,
  departmentRoot,
  groupDepartmentSpaces,
} from "@/components/file-manager/file-space-navigation";
import { KeenIcon } from "@/components/ui/keen-icon";
import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import type { FileManagerFileSpace } from "@/lib/api/file-manager-client";
import type { StorviaLocale } from "@/lib/i18n";
import { cn } from "@/lib/utils";

type FileSpaceSwitcherProps = {
  copy: WorkspaceCopy;
  locale: StorviaLocale;
  personalSpace: FileManagerFileSpace | null;
  departmentSpaces: FileManagerFileSpace[];
  activeSpace: FileManagerFileSpace | null;
  activeDepartmentNavigationId: string | null;
  onSpaceChange: (space: FileManagerFileSpace) => void;
  onDepartmentNavigationChange: (departmentId: string) => void;
};

export function FileSpaceSwitcher({
  copy,
  locale,
  personalSpace,
  departmentSpaces,
  activeSpace,
  activeDepartmentNavigationId,
  onSpaceChange,
  onDepartmentNavigationChange,
}: FileSpaceSwitcherProps) {
  const departmentGroups = React.useMemo(
    () => groupDepartmentSpaces(departmentSpaces, locale),
    [departmentSpaces, locale],
  );

  const activeDepartmentKey =
    activeSpace?.type === "department" ? departmentRoot(activeSpace).key : null;
  const [selectedDepartmentKey, setSelectedDepartmentKey] = React.useState<
    string | null
  >(null);
  const resolvedDepartmentKey =
    activeDepartmentNavigationId &&
    departmentGroups.some((group) => group.key === activeDepartmentNavigationId)
      ? activeDepartmentNavigationId
      : selectedDepartmentKey &&
          departmentGroups.some((group) => group.key === selectedDepartmentKey)
        ? selectedDepartmentKey
        : activeDepartmentKey ?? departmentGroups[0]?.key ?? null;

  if (departmentGroups.length === 0) {
    return personalSpace ? (
      <section aria-label={copy.fileSpaceScope} className="space-y-3">
        <div className="grid gap-3 sm:grid-cols-2">
          <ScopeCard
            active={activeSpace?.id === personalSpace.id}
            icon="folder"
            title={copy.myFiles}
            subtitle={copy.personalScope}
            onClick={() => onSpaceChange(personalSpace)}
          />
        </div>
      </section>
    ) : null;
  }

  const selectedDepartment =
    departmentGroups.find((group) => group.key === resolvedDepartmentKey) ??
    departmentGroups[0] ??
    null;
  const departmentActive =
    activeSpace?.type === "department" || activeDepartmentNavigationId !== null;

  const openDepartment = (group: (typeof departmentGroups)[number]) => {
    setSelectedDepartmentKey(group.key);

    if (group.rootSpace) {
      onSpaceChange(group.rootSpace);
      return;
    }

    if (group.sections.length === 1) {
      onSpaceChange(group.sections[0]);
      return;
    }

    if (group.sections.length > 1) {
      onDepartmentNavigationChange(group.key);
    }
  };

  const openCompanyDrive = () => {
    if (selectedDepartment) {
      openDepartment(selectedDepartment);
    }
  };

  return (
    <section aria-label={copy.fileSpaceScope} className="space-y-3">
      <div className="grid gap-3 sm:grid-cols-2">
        {personalSpace ? (
          <ScopeCard
            active={activeSpace?.id === personalSpace.id}
            icon="folder"
            title={copy.myFiles}
            subtitle={copy.personalScope}
            onClick={() => onSpaceChange(personalSpace)}
          />
        ) : null}

        <ScopeCard
          active={departmentActive}
          icon="element-11"
          title={copy.companyDrive}
          subtitle={
            departmentActive && selectedDepartment
              ? selectedDepartment.name
              : copy.chooseDepartment
          }
          onClick={openCompanyDrive}
        />
      </div>

      {departmentActive ? (
        <div className="grid gap-3 lg:grid-cols-2">
          <SelectionCard
            icon="element-11"
            eyebrow={copy.companyDrive}
            title={copy.departmentSelector}
            description={copy.chooseDepartment}
          >
            <div className="space-y-1">
              {departmentGroups.map((group) => {
                const active = group.key === selectedDepartment?.key;

                return (
                  <button
                    key={group.key}
                    type="button"
                    onClick={() => openDepartment(group)}
                    aria-pressed={active}
                    className={cn(
                      "flex min-h-10 w-full items-center gap-2.5 rounded-lg px-2.5 text-start outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring/30",
                      active
                        ? "bg-brand-blue/10 text-brand-blue"
                        : "text-foreground hover:bg-accent",
                    )}
                  >
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-brand-blue/8 text-brand-blue">
                      <KeenIcon
                        name="folder"
                        variant={active ? "solid" : "outline"}
                        className="text-[15px]"
                      />
                    </span>
                    <span className="min-w-0 flex-1 truncate text-xs font-semibold">
                      {group.name}
                    </span>
                    {active ? (
                      <KeenIcon name="check" className="text-[14px]" />
                    ) : (
                      <KeenIcon
                        name="right"
                        className="text-[13px] text-muted-foreground rtl:rotate-180"
                      />
                    )}
                  </button>
                );
              })}
            </div>
          </SelectionCard>

          <SelectionCard
            icon="folder"
            eyebrow={selectedDepartment?.name ?? copy.companyDrive}
            title={copy.sectionSelector}
            description={
              selectedDepartment?.rootSpace
                ? copy.chooseSection
                : copy.chooseAllowedSection
            }
          >
            {selectedDepartment && selectedDepartment.sections.length > 0 ? (
              <div className="space-y-1">
                {selectedDepartment.sections.map((space) => {
                  const active = space.id === activeSpace?.id;
                  const label = departmentLeafLabel(space);

                  return (
                    <button
                      key={space.id}
                      type="button"
                      onClick={() => onSpaceChange(space)}
                      aria-pressed={active}
                      title={label}
                      className={cn(
                        "flex min-h-10 w-full items-center gap-2.5 rounded-lg px-2.5 text-start outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring/30",
                        active
                          ? "bg-brand-blue/10 text-brand-blue"
                          : "text-foreground hover:bg-accent",
                      )}
                    >
                      <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-brand-cyan/10 text-brand-cyan">
                        <KeenIcon
                          name="folder"
                          variant={active ? "solid" : "outline"}
                          className="text-[15px]"
                        />
                      </span>
                      <span className="min-w-0 flex-1 truncate text-xs font-semibold">
                        {label}
                      </span>
                      {active ? (
                        <KeenIcon name="check" className="text-[14px]" />
                      ) : null}
                    </button>
                  );
                })}
              </div>
            ) : (
              <div className="flex min-h-24 items-center justify-center rounded-lg border border-dashed border-workspace-content-border bg-muted/20 px-4 text-center text-[11px] leading-5 text-muted-foreground">
                {copy.noSectionsAvailable}
              </div>
            )}
          </SelectionCard>
        </div>
      ) : null}
    </section>
  );
}

function ScopeCard({
  active,
  icon,
  title,
  subtitle,
  onClick,
}: {
  active: boolean;
  icon: string;
  title: string;
  subtitle: string;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      aria-pressed={active}
      onClick={onClick}
      className={cn(
        "flex min-h-[68px] min-w-0 items-center gap-3 rounded-xl border px-3.5 text-start shadow-xs outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring/30",
        active
          ? "border-brand-blue/25 bg-brand-blue/5 text-brand-blue"
          : "border-workspace-content-border bg-card text-foreground hover:bg-accent/50",
      )}
    >
      <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-blue/10 text-brand-blue">
        <KeenIcon name={icon} variant="outline" className="text-[18px]" />
      </span>
      <span className="min-w-0 flex-1">
        <span className="block truncate text-xs font-bold">{title}</span>
        <span className="mt-0.5 block truncate text-[10.5px] text-muted-foreground">
          {subtitle}
        </span>
      </span>
      {active ? (
        <KeenIcon name="check" className="shrink-0 text-[15px]" />
      ) : null}
    </button>
  );
}

function SelectionCard({
  icon,
  eyebrow,
  title,
  description,
  children,
}: {
  icon: string;
  eyebrow: string;
  title: string;
  description: string;
  children: React.ReactNode;
}) {
  return (
    <section className="overflow-hidden rounded-xl border border-workspace-content-border bg-card shadow-xs">
      <div className="flex items-center gap-3 border-b border-workspace-content-border px-3.5 py-3">
        <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
          <KeenIcon name={icon} variant="outline" className="text-[16px]" />
        </span>
        <div className="min-w-0">
          <p className="truncate text-[9.5px] font-semibold uppercase tracking-[0.06em] text-muted-foreground">
            {eyebrow}
          </p>
          <p className="truncate text-xs font-bold text-foreground">{title}</p>
          <p className="truncate text-[10px] text-muted-foreground">
            {description}
          </p>
        </div>
      </div>
      <div className="max-h-56 overflow-y-auto p-2.5">{children}</div>
    </section>
  );
}
