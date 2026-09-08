import * as React from "react"

import { cn } from "@/lib/utils"

function StorviaMark({
  className,
  ...props
}: React.ComponentProps<"svg">) {
  return (
    <svg
      viewBox="0 0 48 48"
      fill="none"
      aria-hidden="true"
      className={cn("shrink-0", className)}
      {...props}
    >
      <path
        d="M35.5 10.5H18.2C13.7 10.5 10 13.8 10 17.9C10 22 13.7 25.3 18.2 25.3H29.8C34.3 25.3 38 28.6 38 32.7C38 36.9 34.3 40.2 29.8 40.2H12.5"
        stroke="currentColor"
        strokeWidth="4.2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <path
        d="M12.5 7.8H31.2"
        stroke="currentColor"
        strokeWidth="1.6"
        strokeLinecap="round"
        opacity="0.34"
      />
      <path
        d="M16.8 43H35.5"
        stroke="currentColor"
        strokeWidth="1.6"
        strokeLinecap="round"
        opacity="0.34"
      />
      <circle cx="38" cy="10.5" r="2.8" fill="currentColor" opacity="0.72" />
      <circle cx="10" cy="40.2" r="2.8" fill="currentColor" opacity="0.72" />
    </svg>
  )
}

export { StorviaMark }
