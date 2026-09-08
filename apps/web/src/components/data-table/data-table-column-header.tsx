"use client"

import {
  ArrowDown,
  ArrowUp,
  ChevronsUpDown,
} from "lucide-react"
import type {
  Column,
  RowData,
} from "@tanstack/react-table"

import { Button } from "@/components/ui/button"

import type { StorviaTableFeatures } from "./data-table-features"

type DataTableColumnHeaderProps<
  TData extends RowData,
  TValue,
> = {
  column: Column<StorviaTableFeatures, TData, TValue>
  title: string
  className?: string
  sortAriaLabel?: string
}

export function DataTableColumnHeader<
  TData extends RowData,
  TValue,
>({
  column,
  title,
  className,
  sortAriaLabel,
}: DataTableColumnHeaderProps<TData, TValue>) {
  if (!column.getCanSort()) {
    return <span className={className}>{title}</span>
  }

  const sorted = column.getIsSorted()

  return (
    <Button
      type="button"
      variant="ghost"
      size="sm"
      className={className}
      onClick={column.getToggleSortingHandler()}
      aria-label={sortAriaLabel ?? `Sort by ${title}`}
    >
      <span>{title}</span>

      {sorted === "desc" ? (
        <ArrowDown className="size-3.5" />
      ) : sorted === "asc" ? (
        <ArrowUp className="size-3.5" />
      ) : (
        <ChevronsUpDown className="size-3.5" />
      )}
    </Button>
  )
}
