import * as React from "react"
import { LoaderCircle } from "lucide-react"

import { cn } from "@/lib/utils"

type LoadingStateSize = "sm" | "default" | "lg"

type LoadingStateProps = React.ComponentProps<"div"> & {
  label?: string
  description?: string
  size?: LoadingStateSize
}

const sizeClasses: Record<LoadingStateSize, string> = {
  sm: "min-h-20 gap-2 px-4 py-4",
  default: "min-h-32 gap-3 px-6 py-6",
  lg: "min-h-48 gap-4 px-8 py-8",
}

const iconSizeClasses: Record<LoadingStateSize, string> = {
  sm: "size-4",
  default: "size-5",
  lg: "size-6",
}

function LoadingState({
  className,
  label = "Loading…",
  description,
  size = "default",
  ...props
}: LoadingStateProps) {
  return (
    <div
      data-slot="loading-state"
      data-size={size}
      role="status"
      aria-live="polite"
      aria-busy="true"
      className={cn(
        "flex w-full flex-col items-center justify-center text-center",
        sizeClasses[size],
        className
      )}
      {...props}
    >
      <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
        <LoaderCircle
          className={cn("animate-spin", iconSizeClasses[size])}
          aria-hidden="true"
        />
      </div>

      <div>
        <p className="text-sm font-medium text-foreground">{label}</p>
        {description ? (
          <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
            {description}
          </p>
        ) : null}
      </div>
    </div>
  )
}

export { LoadingState }
export type { LoadingStateProps }
