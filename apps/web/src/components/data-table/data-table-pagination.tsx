"use client"

import {
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
} from "lucide-react"
import type {
  RowData,
  Table as TanStackTable,
} from "@tanstack/react-table"

import { Button } from "@/components/ui/button"
import { StorviaSelect } from "@/components/ui/storvia-select"

import type { StorviaTableFeatures } from "./data-table-features"
import type { DataTableLabels } from "./data-table-labels"

type DataTablePaginationProps<TData extends RowData> = {
  table: TanStackTable<StorviaTableFeatures, TData>
  pageSizeOptions?: number[]
  labels: DataTableLabels
}

export function DataTablePagination<TData extends RowData>({
  table,
  pageSizeOptions = [10, 20, 30, 50],
  labels,
}: DataTablePaginationProps<TData>) {
  const selectedRows =
    table.getFilteredSelectedRowModel().rows.length

  const filteredRows =
    table.getFilteredRowModel().rows.length

  const {
    pageIndex,
    pageSize,
  } = table.store.state.pagination

  const pageCount = Math.max(table.getPageCount(), 1)

  return (
    <div className="flex flex-col gap-4 border-t border-border pt-4 md:flex-row md:items-center md:justify-between">
      <div className="text-sm text-muted-foreground">
        {selectedRows > 0
          ? labels.rowsSelected(selectedRows, filteredRows)
          : labels.rowCount(filteredRows)}
      </div>

      <div className="flex flex-wrap items-center gap-3 sm:gap-4">
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <span>{labels.rowsPerPage}</span>

          <StorviaSelect
            value={String(pageSize)}
            onValueChange={(value) => table.setPageSize(Number(value))}
            options={pageSizeOptions.map((size) => ({
              value: String(size),
              label: String(size),
            }))}
            ariaLabel={labels.rowsPerPage}
            className="h-8 w-20 rounded-md border border-input bg-background px-2 text-sm text-foreground outline-none focus:border-ring focus:ring-2 focus:ring-ring/25"
          />
        </div>

        <div className="min-w-24 text-center text-sm">
          {labels.page} {pageIndex + 1} {labels.of} {pageCount}
        </div>

        <div className="flex items-center gap-1">
          <Button
            variant="outline"
            size="icon-sm"
            onClick={() => table.setPageIndex(0)}
            disabled={!table.getCanPreviousPage()}
            aria-label={labels.firstPage}
          >
            <ChevronsLeft className="rtl:rotate-180" />
          </Button>

          <Button
            variant="outline"
            size="icon-sm"
            onClick={() => table.previousPage()}
            disabled={!table.getCanPreviousPage()}
            aria-label={labels.previousPage}
          >
            <ChevronLeft className="rtl:rotate-180" />
          </Button>

          <Button
            variant="outline"
            size="icon-sm"
            onClick={() => table.nextPage()}
            disabled={!table.getCanNextPage()}
            aria-label={labels.nextPage}
          >
            <ChevronRight className="rtl:rotate-180" />
          </Button>

          <Button
            variant="outline"
            size="icon-sm"
            onClick={() => table.setPageIndex(pageCount - 1)}
            disabled={!table.getCanNextPage()}
            aria-label={labels.lastPage}
          >
            <ChevronsRight className="rtl:rotate-180" />
          </Button>
        </div>
      </div>
    </div>
  )
}
