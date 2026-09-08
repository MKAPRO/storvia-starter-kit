import * as React from "react";
import { Input as InputPrimitive } from "@base-ui/react/input";

import { cn } from "@/lib/utils";

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <InputPrimitive
      type={type}
      data-slot="input"
      className={cn(
        [
          "h-9 w-full min-w-0 rounded-md border border-input",
          "bg-background px-3 py-1.5 text-sm text-foreground shadow-xs",
          "transition-[color,background-color,border-color,box-shadow] duration-150",
          "outline-none",
          "placeholder:text-muted-foreground",
          "focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/25",
          "disabled:pointer-events-none disabled:cursor-not-allowed disabled:bg-muted disabled:text-muted-foreground disabled:opacity-70",
          "aria-invalid:border-destructive/70 aria-invalid:ring-2 aria-invalid:ring-destructive/20",
          "dark:bg-surface dark:disabled:bg-surface-muted dark:aria-invalid:ring-destructive/30",
          "file:me-3 file:inline-flex file:h-7 file:border-0 file:bg-transparent file:p-0",
          "file:text-sm file:font-medium file:text-foreground",
        ].join(" "),
        className,
      )}
      {...props}
    />
  );
}

export { Input };
