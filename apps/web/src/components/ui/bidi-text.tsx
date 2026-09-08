import * as React from "react"

import { cn } from "@/lib/utils"

function BidiText({
  className,
  dir = "auto",
  ...props
}: React.ComponentProps<"bdi">) {
  return (
    <bdi
      data-slot="bidi-text"
      dir={dir}
      className={cn("unicode-bidi-isolate", className)}
      {...props}
    />
  )
}

export { BidiText }
