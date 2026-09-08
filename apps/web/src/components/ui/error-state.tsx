import * as React from "react"
import { TriangleAlert } from "lucide-react"

import { cn } from "@/lib/utils"

type ErrorStateVariant = "error" | "warning"
type ErrorStateSize = "compact" | "default"

type ErrorStateProps = Omit<React.ComponentProps<"div">, "title"> & {
  icon?: React.ReactNode
  title: React.ReactNode
  description?: React.ReactNode
  action?: React.ReactNode
  secondaryAction?: React.ReactNode
  code?: React.ReactNode
  variant?: ErrorStateVariant
  size?: ErrorStateSize
}

function ErrorState({
  className,
  icon,
  title,
  description,
  action,
  secondaryAction,
  code,
  variant = "error",
  size = "default",
  ...props
}: ErrorStateProps) {
  const isWarning = variant === "warning"

  return (
    <div
      data-slot="error-state"
      data-variant={variant}
      data-size={size}
      role="alert"
      className={cn(
        "flex w-full flex-col items-center justify-center text-center",
        size === "compact" ? "min-h-32 px-5 py-6" : "min-h-56 px-6 py-10",
        className
      )}
      {...props}
    >
      <div
        data-slot="error-state-icon"
        className={cn(
          "flex items-center justify-center rounded-xl border",
          size === "compact" ? "size-10" : "size-12",
          isWarning
            ? "border-warning/25 bg-warning/12 text-warning-foreground dark:text-warning"
            : "border-destructive/20 bg-destructive/10 text-destructive"
        )}
      >
        {icon ?? <TriangleAlert className="size-5" aria-hidden="true" />}
      </div>

      <div className={cn(size === "compact" ? "mt-3" : "mt-4")}>
        <h3 className="text-sm font-semibold text-foreground">{title}</h3>

        {description ? (
          <p className="mx-auto mt-1.5 max-w-md text-sm leading-6 text-muted-foreground">
            {description}
          </p>
        ) : null}

        {code ? (
          <code className="mt-3 inline-flex rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground">
            {code}
          </code>
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

export { ErrorState }
export type { ErrorStateProps }
