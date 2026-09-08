"use client"

import {
  FileQuestion,
  FolderOpen,
  RefreshCw,
  SearchX,
  ShieldAlert,
  Upload,
} from "lucide-react"

import { Button } from "@/components/ui/button"
import { EmptyState } from "@/components/ui/empty-state"
import { ErrorState } from "@/components/ui/error-state"
import { LoadingState } from "@/components/ui/loading-state"
import { Skeleton } from "@/components/ui/skeleton"

function FileCardSkeleton() {
  return (
    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
      <div className="flex items-start gap-3">
        <Skeleton className="size-10 shrink-0 rounded-lg" />

        <div className="min-w-0 flex-1 space-y-2">
          <Skeleton className="h-4 w-3/5" />
          <Skeleton className="h-3 w-2/5" />
        </div>

        <Skeleton className="size-7 shrink-0" />
      </div>

      <div className="mt-5 flex items-center justify-between gap-3">
        <Skeleton className="h-3 w-24" />
        <Skeleton className="h-5 w-16 rounded-full" />
      </div>
    </div>
  )
}

function TableSkeleton() {
  return (
    <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
      <div className="grid grid-cols-[minmax(220px,1.4fr)_1fr_0.7fr_0.7fr] gap-4 border-b border-border bg-surface-muted/70 px-4 py-3">
        {Array.from({ length: 4 }).map((_, index) => (
          <Skeleton key={index} className="h-3 w-20" />
        ))}
      </div>

      <div className="divide-y divide-border">
        {Array.from({ length: 4 }).map((_, row) => (
          <div
            key={row}
            className="grid grid-cols-[minmax(220px,1.4fr)_1fr_0.7fr_0.7fr] items-center gap-4 px-4 py-3"
          >
            <div className="flex items-center gap-3">
              <Skeleton className="size-8 shrink-0" />
              <div className="space-y-2">
                <Skeleton className="h-3.5 w-40" />
                <Skeleton className="h-3 w-20" />
              </div>
            </div>

            <Skeleton className="h-3.5 w-24" />
            <Skeleton className="h-5 w-16 rounded-full" />
            <Skeleton className="h-3.5 w-20" />
          </div>
        ))}
      </div>
    </div>
  )
}

export function SystemStatesDemo() {
  return (
    <section className="border-t border-border py-10">
      <div className="mb-8">
        <div className="inline-flex rounded-full border border-border bg-surface px-3 py-1 text-xs font-semibold text-surface-foreground">
          STAGE 02G
        </div>

        <h2 className="mt-3 text-xl font-semibold">
          Loading, Empty & Error States
        </h2>

        <p className="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
          STORVIA system feedback for asynchronous operations, first-use
          screens, filtered results, recoverable failures, and permission
          problems.
        </p>
      </div>

      <div className="space-y-12">
        {/* Loading */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">Loading states</h3>

          <div className="grid gap-5 lg:grid-cols-2">
            <div className="rounded-lg border border-border bg-card shadow-xs">
              <LoadingState
                label="Loading your files"
                description="STORVIA is preparing the current workspace."
              />
            </div>

            <div className="rounded-lg border border-border bg-card shadow-xs">
              <LoadingState
                size="sm"
                label="Processing upload"
                description="virus-scan-report.pdf is being validated."
              />
            </div>
          </div>
        </div>

        {/* Skeletons */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">Skeleton patterns</h3>

          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <FileCardSkeleton />
            <FileCardSkeleton />
            <FileCardSkeleton />
          </div>

          <div className="mt-5">
            <TableSkeleton />
          </div>
        </div>

        {/* Empty */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">Empty states</h3>

          <div className="grid gap-5 lg:grid-cols-3">
            <div className="rounded-lg border border-border bg-card shadow-xs">
              <EmptyState
                icon={<FolderOpen className="size-5" />}
                title="No files yet"
                description="Upload your first file or create a folder to start organizing this workspace."
                action={
                  <Button>
                    <Upload />
                    Upload files
                  </Button>
                }
                secondaryAction={
                  <Button variant="outline">New folder</Button>
                }
              />
            </div>

            <div className="rounded-lg border border-border bg-card shadow-xs">
              <EmptyState
                icon={<SearchX className="size-5" />}
                title="No matching files"
                description="Try a different search term or clear the active filters."
                action={<Button variant="outline">Clear filters</Button>}
              />
            </div>

            <div className="rounded-lg border border-border bg-card shadow-xs">
              <EmptyState
                icon={<FileQuestion className="size-5" />}
                title="Nothing shared with you"
                description="Files and folders shared by your organization will appear here."
              />
            </div>
          </div>
        </div>

        {/* Errors */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">Error states</h3>

          <div className="grid gap-5 lg:grid-cols-2">
            <div className="rounded-lg border border-border bg-card shadow-xs">
              <ErrorState
                title="Couldn’t load this folder"
                description="The request failed before STORVIA could retrieve the folder contents."
                code="STORAGE_FETCH_FAILED"
                action={
                  <Button>
                    <RefreshCw />
                    Retry
                  </Button>
                }
                secondaryAction={
                  <Button variant="outline">Go back</Button>
                }
              />
            </div>

            <div className="rounded-lg border border-border bg-card shadow-xs">
              <ErrorState
                variant="warning"
                icon={<ShieldAlert className="size-5" />}
                title="Access restricted"
                description="Your account does not have permission to open this item. Backend authorization remains the source of truth."
                code="ACCESS_DENIED"
                action={<Button variant="outline">Return to files</Button>}
              />
            </div>
          </div>
        </div>

        {/* Compact embedding */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">
            Compact embedded states
          </h3>

          <div className="grid gap-5 md:grid-cols-3">
            <div className="rounded-lg border border-border bg-card shadow-xs">
              <LoadingState size="sm" label="Refreshing activity" />
            </div>

            <div className="rounded-lg border border-border bg-card shadow-xs">
              <EmptyState
                size="compact"
                title="No recent activity"
                description="New file activity will appear here."
              />
            </div>

            <div className="rounded-lg border border-border bg-card shadow-xs">
              <ErrorState
                size="compact"
                title="Preview unavailable"
                description="The preview could not be generated."
                action={<Button variant="outline" size="sm">Retry</Button>}
              />
            </div>
          </div>
        </div>

        {/* Arabic */}
        <div
          lang="ar"
          dir="rtl"
          className="font-arabic rounded-xl border border-border bg-card p-6"
        >
          <div className="mb-6">
            <h3 className="text-sm font-semibold">العربية / RTL</h3>
            <p className="mt-1 text-sm text-muted-foreground">
              حالات النظام تحافظ على الوضوح والتسلسل البصري في الواجهة العربية.
            </p>
          </div>

          <div className="grid gap-5 lg:grid-cols-3">
            <div className="rounded-lg border border-border bg-background">
              <LoadingState
                size="sm"
                label="جارٍ تحميل الملفات"
                description="يتم تجهيز مساحة العمل الحالية."
              />
            </div>

            <div className="rounded-lg border border-border bg-background">
              <EmptyState
                size="compact"
                title="لا توجد ملفات"
                description="ارفع ملفًا أو أنشئ مجلدًا للبدء."
                action={<Button size="sm">رفع الملفات</Button>}
              />
            </div>

            <div className="rounded-lg border border-border bg-background">
              <ErrorState
                size="compact"
                title="تعذر تحميل المجلد"
                description="حدث خطأ أثناء جلب محتويات المجلد."
                action={<Button variant="outline" size="sm">إعادة المحاولة</Button>}
              />
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}
