"use client";

import * as React from "react";

import { cn } from "@/lib/utils";

type TableDensity = "default" | "compact";

function Table({
  className,
  density = "default",
  ...props
}: React.ComponentProps<"table"> & {
  density?: TableDensity;
}) {
  return (
    <div
      data-slot="table-container"
      className="relative w-full overflow-x-auto rounded-lg border border-border bg-card shadow-xs"
    >
      <table
        data-slot="table"
        data-density={density}
        className={cn(
          "group/table w-full caption-bottom text-sm text-card-foreground",
          className,
        )}
        {...props}
      />
    </div>
  );
}

function TableHeader({ className, ...props }: React.ComponentProps<"thead">) {
  return (
    <thead
      data-slot="table-header"
      className={cn(
        "bg-surface-muted/70 text-muted-foreground [&_tr]:border-b [&_tr]:border-border",
        className,
      )}
      {...props}
    />
  );
}

function TableBody({ className, ...props }: React.ComponentProps<"tbody">) {
  return (
    <tbody
      data-slot="table-body"
      className={cn("[&_tr:last-child]:border-0", className)}
      {...props}
    />
  );
}

function TableFooter({ className, ...props }: React.ComponentProps<"tfoot">) {
  return (
    <tfoot
      data-slot="table-footer"
      className={cn(
        "border-t border-border bg-surface-muted/70 font-medium [&>tr]:last:border-b-0",
        className,
      )}
      {...props}
    />
  );
}

function TableRow({ className, ...props }: React.ComponentProps<"tr">) {
  return (
    <tr
      data-slot="table-row"
      className={cn(
        [
          "border-b border-border",
          "transition-[background-color,border-color] duration-150",
          "hover:bg-surface-interactive/45",
          "focus-within:bg-surface-interactive/45",
          "has-aria-expanded:bg-surface-interactive/55",
          "data-[interactive=true]:cursor-pointer",
          "data-[state=selected]:bg-surface-interactive",
          "data-[state=selected]:hover:bg-surface-interactive",
          "data-[state=selected]:border-primary/20",
        ].join(" "),
        className,
      )}
      {...props}
    />
  );
}

function TableHead({ className, ...props }: React.ComponentProps<"th">) {
  return (
    <th
      data-slot="table-head"
      className={cn(
        [
          "h-11 px-3 text-start align-middle",
          "text-xs font-semibold tracking-wide whitespace-nowrap",
          "text-muted-foreground",
          "group-data-[density=compact]/table:h-9",
          "group-data-[density=compact]/table:px-2.5",
          "[&:has([role=checkbox])]:pe-0",
        ].join(" "),
        className,
      )}
      {...props}
    />
  );
}

function TableCell({ className, ...props }: React.ComponentProps<"td">) {
  return (
    <td
      data-slot="table-cell"
      className={cn(
        [
          "h-12 px-3 py-2.5 align-middle whitespace-nowrap",
          "group-data-[density=compact]/table:h-10",
          "group-data-[density=compact]/table:px-2.5",
          "group-data-[density=compact]/table:py-2",
          "[&:has([role=checkbox])]:pe-0",
        ].join(" "),
        className,
      )}
      {...props}
    />
  );
}

function TableEmpty({
  className,
  colSpan = 1,
  children = "No data available.",
  ...props
}: React.ComponentProps<"td"> & {
  colSpan?: number;
}) {
  return (
    <td
      data-slot="table-empty"
      colSpan={colSpan}
      className={cn(
        "h-32 px-4 text-center align-middle text-sm text-muted-foreground",
        className,
      )}
      {...props}
    >
      {children}
    </td>
  );
}

function TableCaption({
  className,
  ...props
}: React.ComponentProps<"caption">) {
  return (
    <caption
      data-slot="table-caption"
      className={cn("mt-4 text-start text-sm text-muted-foreground", className)}
      {...props}
    />
  );
}

export {
  Table,
  TableHeader,
  TableBody,
  TableFooter,
  TableHead,
  TableRow,
  TableCell,
  TableEmpty,
  TableCaption,
};
