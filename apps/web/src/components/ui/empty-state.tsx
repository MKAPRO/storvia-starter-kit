import * as React from "react"
import { FolderOpen } from "lucide-react"

import { cn } from "@/lib/utils"

type EmptyStateSize = "compact" | "default"

type EmptyStateProps = Omit<React.ComponentProps<"div">, "title"> & {
  icon?: React.ReactNode
  title: React.ReactNode
  description?: React.ReactNode
  action?: React.ReactNode
  secondaryAction?: React.ReactNode
  size?: EmptyStateSize
}

function EmptyState({
  className,
  icon,
  title,
  description,
  action,
  secondaryAction,
  size = "default",
  ...props
}: EmptyStateProps) {
  return (
    <div
      data-slot="empty-state"
      data-size={size}
      role="status"
      className={cn(
        "flex w-full flex-col items-center justify-center text-center",
        size === "compact" ? "min-h-32 px-5 py-6" : "min-h-56 px-6 py-10",
        className
      )}
      {...props}
    >
      <div
        data-slot="empty-state-icon"
        className={cn(
          "flex items-center justify-center rounded-xl border border-border bg-surface-muted text-muted-foreground",
          size === "compact" ? "size-10" : "size-12"
        )}
      >
        {icon ?? <FolderOpen className="size-5" aria-hidden="true" />}
      </div>

      <div className={cn(size === "compact" ? "mt-3" : "mt-4")}>
        <h3 className="text-sm font-semibold text-foreground">{title}</h3>

        {description ? (
          <p className="mx-auto mt-1.5 max-w-md text-sm leading-6 text-muted-foreground">
            {description}
          </p>
        ) : null}
      </div>

      {action || secondaryAction ? (
        <div className="mt-5 flex flex-wrap items-center justify-center gap-2">
          {action}
          {secondaryAction}
        </div>
      ) : null}
    </div>
  )
}

export { EmptyState }
export type { EmptyStateProps }
