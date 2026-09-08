"use client"

import { Columns3 } from "lucide-react"
import type {
  RowData,
  Table as TanStackTable,
} from "@tanstack/react-table"

import type { StorviaTableFeatures } from "./data-table-features"
import type { DataTableLabels } from "./data-table-labels"

type DataTableViewOptionsProps<TData extends RowData> = {
  table: TanStackTable<StorviaTableFeatures, TData>
  labels: DataTableLabels
}

export function DataTableViewOptions<TData extends RowData>({
  table,
  labels,
}: DataTableViewOptionsProps<TData>) {
  const columns = table
    .getAllColumns()
    .filter(
      (column) =>
        typeof column.accessorFn !== "undefined" &&
        column.getCanHide()
    )

  if (columns.length === 0) {
    return null
  }

  return (
    <details className="group relative">
      <summary className="inline-flex h-8 cursor-pointer list-none items-center gap-1.5 rounded-md border border-border bg-background px-3 text-[0.8125rem] font-medium text-foreground shadow-xs transition-colors hover:bg-surface-interactive hover:text-surface-interactive-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/30">
        <Columns3 className="size-3.5" />
        {labels.columns}
      </summary>

      <div className="absolute end-0 z-50 mt-1 min-w-52 rounded-md border border-border bg-popover p-2 text-popover-foreground shadow-md">
        <p className="px-2 pb-2 text-xs font-semibold text-muted-foreground">
          {labels.toggleColumns}
        </p>

        <div className="space-y-1">
          {columns.map((column) => (
            <label
              key={column.id}
              className="flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground"
            >
              <input
                type="checkbox"
                checked={column.getIsVisible()}
                onChange={(event) =>
                  column.toggleVisibility(event.target.checked)
                }
                className="size-4 accent-primary"
              />

              <span>
                {labels.columnLabel(column.id)}
              </span>
            </label>
          ))}
        </div>
      </div>
    </details>
  )
}
