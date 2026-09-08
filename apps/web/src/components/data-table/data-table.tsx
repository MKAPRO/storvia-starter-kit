"use client"

import * as React from "react"
import {
  flexRender,
  useTable,
  type RowData,
} from "@tanstack/react-table"

import {
  Table,
  TableBody,
  TableCell,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

import {
  storviaTableFeatures,
  type StorviaColumnDef,
} from "./data-table-features"
import {
  defaultDataTableLabels,
  type DataTableLabels,
} from "./data-table-labels"
import { DataTablePagination } from "./data-table-pagination"
import { DataTableToolbar } from "./data-table-toolbar"

type DataTableProps<TData extends RowData> = {
  columns: StorviaColumnDef<TData>[]
  data: TData[]
  searchPlaceholder?: string
  density?: "default" | "compact"
  enableRowSelection?: boolean
  defaultPageSize?: number
  pageSizeOptions?: number[]
  getRowId?: (originalRow: TData, index: number) => string
  emptyMessage?: string
  labels?: Partial<DataTableLabels>
}

function IndeterminateCheckbox({
  indeterminate,
  className,
  ...props
}: React.InputHTMLAttributes<HTMLInputElement> & {
  indeterminate?: boolean
}) {
  const ref = React.useRef<HTMLInputElement>(null)

  React.useEffect(() => {
    if (ref.current) {
      ref.current.indeterminate = Boolean(indeterminate)
    }
  }, [indeterminate])

  return (
    <input
      ref={ref}
      type="checkbox"
      className={[
        "size-4 rounded border border-input accent-primary",
        "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/30",
        className,
      ]
        .filter(Boolean)
        .join(" ")}
      {...props}
    />
  )
}

export function DataTable<TData extends RowData>({
  columns,
  data,
  searchPlaceholder = "Search...",
  density = "default",
  enableRowSelection = true,
  defaultPageSize = 10,
  pageSizeOptions = [10, 20, 30, 50],
  getRowId,
  emptyMessage = "No results found.",
  labels,
}: DataTableProps<TData>) {
  const tableLabels: DataTableLabels = {
    ...defaultDataTableLabels,
    ...labels,
  }

  const selectionColumn = React.useMemo<StorviaColumnDef<TData>>(
    () => ({
      id: "select",
      header: ({ table }) => (
        <IndeterminateCheckbox
          checked={table.getIsAllPageRowsSelected()}
          indeterminate={
            table.getIsSomePageRowsSelected() &&
            !table.getIsAllPageRowsSelected()
          }
          onChange={(event) =>
            table.toggleAllPageRowsSelected(event.target.checked)
          }
          aria-label={tableLabels.selectAllRows}
        />
      ),
      cell: ({ row }) => (
        <IndeterminateCheckbox
          checked={row.getIsSelected()}
          disabled={!row.getCanSelect()}
          indeterminate={row.getIsSomeSelected()}
          onChange={(event) =>
            row.toggleSelected(event.target.checked)
          }
          aria-label={tableLabels.selectRow}
        />
      ),
      enableSorting: false,
      enableHiding: false,
    }),
    [tableLabels.selectAllRows, tableLabels.selectRow]
  )

  const effectiveColumns = React.useMemo(
    () =>
      enableRowSelection
        ? [selectionColumn, ...columns]
        : columns,
    [columns, enableRowSelection, selectionColumn]
  )

  const table = useTable({
    features: storviaTableFeatures,
    data,
    columns: effectiveColumns,
    enableRowSelection,
    getRowId,
    globalFilterFn: "includesString",
    initialState: {
      pagination: {
        pageIndex: 0,
        pageSize: defaultPageSize,
      },
    },
  })

  return (
    <div className="space-y-4">
      <DataTableToolbar
        table={table}
        searchPlaceholder={searchPlaceholder}
        labels={tableLabels}
      />

      <Table density={density}>
        <TableHeader>
          {table.getHeaderGroups().map((headerGroup) => (
            <TableRow key={headerGroup.id}>
              {headerGroup.headers.map((header) => (
                <TableHead key={header.id}>
                  {header.isPlaceholder
                    ? null
                    : flexRender(
                        header.column.columnDef.header,
                        header.getContext()
                      )}
                </TableHead>
              ))}
            </TableRow>
          ))}
        </TableHeader>

        <TableBody>
          {table.getRowModel().rows.length > 0 ? (
            table.getRowModel().rows.map((row) => (
              <TableRow
                key={row.id}
                data-state={
                  row.getIsSelected()
                    ? "selected"
                    : undefined
                }
                data-interactive="true"
              >
                {row.getVisibleCells().map((cell) => (
                  <TableCell key={cell.id}>
                    {flexRender(
                      cell.column.columnDef.cell,
                      cell.getContext()
                    )}
                  </TableCell>
                ))}
              </TableRow>
            ))
          ) : (
            <TableRow>
              <TableEmpty
                colSpan={table.getVisibleLeafColumns().length}
              >
                {emptyMessage}
              </TableEmpty>
            </TableRow>
          )}
        </TableBody>
      </Table>

      <DataTablePagination
        table={table}
        pageSizeOptions={pageSizeOptions}
        labels={tableLabels}
      />
    </div>
  )
}
