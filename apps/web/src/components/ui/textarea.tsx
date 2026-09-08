import * as React from "react";

import { cn } from "@/lib/utils";

function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return (
    <textarea
      data-slot="textarea"
      className={cn(
        [
          "field-sizing-content min-h-24 w-full rounded-md border border-input",
          "bg-background px-3 py-2.5 text-sm text-foreground shadow-xs",
          "transition-[color,background-color,border-color,box-shadow] duration-150",
          "outline-none resize-y",
          "placeholder:text-muted-foreground",
          "focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/25",
          "disabled:cursor-not-allowed disabled:bg-muted disabled:text-muted-foreground disabled:opacity-70",
          "aria-invalid:border-destructive/70 aria-invalid:ring-2 aria-invalid:ring-destructive/20",
          "dark:bg-surface dark:disabled:bg-surface-muted dark:aria-invalid:ring-destructive/30",
        ].join(" "),
        className,
      )}
      {...props}
    />
  );
}

export { Textarea };
