"use client";

import { Button } from "@/components/ui/button";
import { KeenIcon } from "@/components/ui/keen-icon";
import type { WorkspaceCopy } from "@/components/workspace/workspace-copy";
import type { StorviaLocale } from "@/lib/i18n";

type FileManagerSelectionToolbarProps = {
  count: number;
  locale: StorviaLocale;
  copy: WorkspaceCopy;
  allSelected: boolean;
  pending?: boolean;
  canTrash?: boolean;
  canRestore?: boolean;
  favoriteTarget?: boolean | null;
  onSelectAll: () => void;
  onClear: () => void;
  onTrash?: () => void;
  onRestore?: () => void;
  onSetFavorite?: (favorite: boolean) => void;
};

export function FileManagerSelectionToolbar({
  count,
  locale,
  copy,
  allSelected,
  pending = false,
  canTrash = false,
  canRestore = false,
  favoriteTarget = null,
  onSelectAll,
  onClear,
  onTrash,
  onRestore,
  onSetFavorite,
}: FileManagerSelectionToolbarProps) {
  if (count === 0) {
    return null;
  }

  const formattedCount = new Intl.NumberFormat(locale === "ar" ? "ar-YE" : "en-US").format(count);

  return (
    <div
      role="toolbar"
      aria-label={copy.selectionActions}
      className="flex flex-wrap items-center gap-2 border-b border-brand-blue/15 bg-brand-blue/[0.035] px-4 py-2 sm:px-5"
    >
      <span className="inline-flex min-h-7 items-center gap-1.5 rounded-md bg-brand-blue/10 px-2.5 text-[11px] font-semibold text-brand-blue">
        <KeenIcon name="check" variant="outline" className="text-[14px]" />
        <span>{formattedCount}</span>
        <span>{copy.selected}</span>
      </span>

      {!allSelected ? (
        <Button
          type="button"
          variant="ghost"
          size="xs"
          disabled={pending}
          onClick={onSelectAll}
        >
          {copy.selectAll}
        </Button>
      ) : null}

      <Button
        type="button"
        variant="ghost"
        size="xs"
        disabled={pending}
        onClick={onClear}
      >
        {copy.clearSelection}
      </Button>

      <div className="flex w-full flex-wrap items-center gap-1.5 sm:ms-auto sm:w-auto">
        {favoriteTarget !== null && onSetFavorite ? (
          <Button
            type="button"
            variant="secondary"
            size="xs"
            disabled={pending}
            onClick={() => onSetFavorite(favoriteTarget)}
          >
            <KeenIcon
              name="star"
              variant={favoriteTarget ? "outline" : "solid"}
              className="text-[14px]"
            />
            {favoriteTarget
              ? copy.addSelectedToFavorites
              : copy.removeSelectedFromFavorites}
          </Button>
        ) : null}

        {canRestore && onRestore ? (
          <Button
            type="button"
            variant="secondary"
            size="xs"
            disabled={pending}
            onClick={onRestore}
          >
            <KeenIcon name="arrows-circle" className="text-[14px]" />
            {copy.restoreSelected}
          </Button>
        ) : null}

        {canTrash && onTrash ? (
          <Button
            type="button"
            variant="destructive"
            size="xs"
            disabled={pending}
            onClick={onTrash}
          >
            <KeenIcon name="trash" className="text-[14px]" />
            {copy.deleteSelected}
          </Button>
        ) : null}
      </div>
    </div>
  );
}
