"use client"

import { Search, X } from "lucide-react"
import type {
  RowData,
  Table as TanStackTable,
} from "@tanstack/react-table"

import { Input } from "@/components/ui/input"

import type { StorviaTableFeatures } from "./data-table-features"
import type { DataTableLabels } from "./data-table-labels"
import { DataTableViewOptions } from "./data-table-view-options"

type DataTableToolbarProps<TData extends RowData> = {
  table: TanStackTable<StorviaTableFeatures, TData>
  searchPlaceholder?: string
  labels: DataTableLabels
}

export function DataTableToolbar<TData extends RowData>({
  table,
  searchPlaceholder = "Search...",
  labels,
}: DataTableToolbarProps<TData>) {
  const globalFilter = String(
    table.store.state.globalFilter ?? ""
  )

  const selectedRows =
    table.getFilteredSelectedRowModel().rows.length

  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center">
        <div className="relative w-full sm:max-w-sm">
          <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />

          <Input
            value={globalFilter}
            onChange={(event) =>
              table.setGlobalFilter(event.target.value)
            }
            placeholder={searchPlaceholder}
            className="ps-9 pe-9"
          />

          {globalFilter ? (
            <button
              type="button"
              onClick={() => table.resetGlobalFilter(true)}
              aria-label={labels.clearSearch}
              className="absolute end-2 top-1/2 flex size-6 -translate-y-1/2 items-center justify-center rounded-sm text-muted-foreground hover:bg-muted hover:text-foreground"
            >
              <X className="size-3.5" />
            </button>
          ) : null}
        </div>

        {selectedRows > 0 ? (
          <div className="text-sm text-muted-foreground">
            {labels.rowsSelected(
              selectedRows,
              table.getFilteredRowModel().rows.length
            )}
          </div>
        ) : null}
      </div>

      <DataTableViewOptions table={table} labels={labels} />
    </div>
  )
}
