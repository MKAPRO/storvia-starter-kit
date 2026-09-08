"use client"

import {
  Archive,
  FileText,
  Folder,
  MoreHorizontal,
} from "lucide-react"

import { DataTable } from "@/components/data-table/data-table"
import { DataTableColumnHeader } from "@/components/data-table/data-table-column-header"
import type { StorviaColumnDef } from "@/components/data-table/data-table-features"
import { Badge } from "@/components/ui/badge"
import { BidiText } from "@/components/ui/bidi-text"
import { Button } from "@/components/ui/button"

type FileStatus =
  | "Shared"
  | "Synced"
  | "Processing"
  | "Failed"
  | "Private"
  | "Archived"

type FileItem = {
  id: string
  name: string
  kind: "folder" | "file"
  owner: string
  status: FileStatus
  size: string
  sizeBytes: number
  modified: string
  modifiedAt: number
}

const files: FileItem[] = [
  {
    id: "f-001",
    name: "Finance",
    kind: "folder",
    owner: "Mohammed",
    status: "Shared",
    size: "2.4 GB",
    sizeBytes: 2_400_000_000,
    modified: "18 min ago",
    modifiedAt: 202608262132,
  },
  {
    id: "f-002",
    name: "Quarterly Report.pdf",
    kind: "file",
    owner: "Ahmed",
    status: "Synced",
    size: "8.6 MB",
    sizeBytes: 8_600_000,
    modified: "Yesterday",
    modifiedAt: 202608251830,
  },
  {
    id: "f-003",
    name: "backup.zip",
    kind: "file",
    owner: "System",
    status: "Processing",
    size: "4.2 GB",
    sizeBytes: 4_200_000_000,
    modified: "2 min ago",
    modifiedAt: 202608262148,
  },
  {
    id: "f-004",
    name: "corrupted-file.bin",
    kind: "file",
    owner: "Ali",
    status: "Failed",
    size: "—",
    sizeBytes: 0,
    modified: "5 min ago",
    modifiedAt: 202608262145,
  },
  {
    id: "f-005",
    name: "HR",
    kind: "folder",
    owner: "Sarah",
    status: "Private",
    size: "920 MB",
    sizeBytes: 920_000_000,
    modified: "Today",
    modifiedAt: 202608261640,
  },
  {
    id: "f-006",
    name: "Brand Assets",
    kind: "folder",
    owner: "Design Team",
    status: "Shared",
    size: "1.1 GB",
    sizeBytes: 1_100_000_000,
    modified: "Today",
    modifiedAt: 202608261510,
  },
  {
    id: "f-007",
    name: "Infrastructure Notes.md",
    kind: "file",
    owner: "Mohammed",
    status: "Synced",
    size: "84 KB",
    sizeBytes: 84_000,
    modified: "Monday",
    modifiedAt: 202608241410,
  },
  {
    id: "f-008",
    name: "Contracts",
    kind: "folder",
    owner: "Legal",
    status: "Private",
    size: "640 MB",
    sizeBytes: 640_000_000,
    modified: "Sunday",
    modifiedAt: 202608231125,
  },
  {
    id: "f-009",
    name: "Old Projects",
    kind: "folder",
    owner: "Mohammed",
    status: "Archived",
    size: "7.8 GB",
    sizeBytes: 7_800_000_000,
    modified: "Aug 20",
    modifiedAt: 202608201000,
  },
  {
    id: "f-010",
    name: "storage-policy.pdf",
    kind: "file",
    owner: "Administrator",
    status: "Synced",
    size: "2.1 MB",
    sizeBytes: 2_100_000,
    modified: "Aug 19",
    modifiedAt: 202608191300,
  },
  {
    id: "f-011",
    name: "Product Screenshots",
    kind: "folder",
    owner: "Design Team",
    status: "Shared",
    size: "3.3 GB",
    sizeBytes: 3_300_000_000,
    modified: "Aug 18",
    modifiedAt: 202608181830,
  },
  {
    id: "f-012",
    name: "migration-log.txt",
    kind: "file",
    owner: "System",
    status: "Synced",
    size: "412 KB",
    sizeBytes: 412_000,
    modified: "Aug 17",
    modifiedAt: 202608171240,
  },
]

function StatusBadge({ status }: { status: FileStatus }) {
  switch (status) {
    case "Synced":
      return <Badge variant="success">Synced</Badge>
    case "Shared":
      return <Badge variant="info">Shared</Badge>
    case "Processing":
      return <Badge variant="warning">Processing</Badge>
    case "Failed":
      return <Badge variant="destructive">Failed</Badge>
    case "Archived":
      return (
        <Badge variant="muted">
          <Archive data-icon="inline-start" />
          Archived
        </Badge>
      )
    case "Private":
    default:
      return <Badge variant="outline">Private</Badge>
  }
}

const columns: StorviaColumnDef<FileItem>[] = [
  {
    accessorKey: "name",
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title="Name" />
    ),
    cell: ({ row }) => {
      const item = row.original

      return (
        <div className="flex min-w-56 items-center gap-3">
          <div
            className={
              item.kind === "folder"
                ? "flex size-8 items-center justify-center rounded-md bg-primary/10 text-primary"
                : "flex size-8 items-center justify-center rounded-md bg-info/10 text-info"
            }
          >
            {item.kind === "folder" ? (
              <Folder className="size-4" />
            ) : (
              <FileText className="size-4" />
            )}
          </div>

          <div className="min-w-0">
            <BidiText className="block truncate font-medium">{item.name}</BidiText>
            <p className="text-xs text-muted-foreground">
              {item.kind === "folder" ? "Folder" : "File"}
            </p>
          </div>
        </div>
      )
    },
  },
  {
    accessorKey: "owner",
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title="Owner" />
    ),
  },
  {
    accessorKey: "status",
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title="Status" />
    ),
    cell: ({ row }) => <StatusBadge status={row.original.status} />,
  },
  {
    accessorKey: "sizeBytes",
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title="Size" />
    ),
    cell: ({ row }) => row.original.size,
  },
  {
    accessorKey: "modifiedAt",
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title="Modified" />
    ),
    cell: ({ row }) => row.original.modified,
  },
  {
    id: "actions",
    enableSorting: false,
    enableHiding: false,
    header: () => <span className="sr-only">Actions</span>,
    cell: ({ row }) => (
      <div className="flex justify-end">
        <Button
          variant="ghost"
          size="icon-sm"
          aria-label={`Actions for ${row.original.name}`}
        >
          <MoreHorizontal />
        </Button>
      </div>
    ),
  },
]

export function FileDataTableDemo() {
  return (
    <section className="border-t border-border py-10">
      <div className="mb-8">
        <h2 className="text-lg font-semibold">
          STORVIA DataTable
        </h2>

        <p className="mt-1 text-sm text-muted-foreground">
          Search, sorting, pagination, row selection, column visibility,
          density, and file-management states powered by TanStack Table.
        </p>
      </div>

      <DataTable
        columns={columns}
        data={files}
        getRowId={(row) => row.id}
        searchPlaceholder="Search files, folders, owners, or status..."
        defaultPageSize={5}
        pageSizeOptions={[5, 10, 20]}
        emptyMessage="No files or folders match your search."
      />
    </section>
  )
}
