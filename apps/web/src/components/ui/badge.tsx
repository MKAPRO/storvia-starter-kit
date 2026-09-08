import { mergeProps } from "@base-ui/react/merge-props";
import { useRender } from "@base-ui/react/use-render";
import { cva, type VariantProps } from "class-variance-authority";

import { cn } from "@/lib/utils";

const badgeVariants = cva(
  [
    "group/badge inline-flex h-6 w-fit shrink-0 items-center justify-center",
    "gap-1 overflow-hidden rounded-full border border-transparent",
    "px-2.5 py-0.5 text-xs font-medium whitespace-nowrap",
    "transition-[color,background-color,border-color,box-shadow] duration-150",
    "outline-none",
    "focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30",
    "has-data-[icon=inline-end]:pe-2",
    "has-data-[icon=inline-start]:ps-2",
    "aria-invalid:border-destructive/60 aria-invalid:ring-2 aria-invalid:ring-destructive/20",
    "dark:aria-invalid:ring-destructive/30",
    "[&>svg]:pointer-events-none [&>svg]:size-3",
  ].join(" "),
  {
    variants: {
      variant: {
        default:
          "bg-primary text-primary-foreground shadow-xs [a]:hover:bg-primary/90",

        secondary:
          "border-border/70 bg-secondary text-secondary-foreground [a]:hover:bg-accent [a]:hover:text-accent-foreground",

        outline:
          "border-border bg-background text-foreground [a]:hover:bg-muted",

        muted: "bg-muted text-muted-foreground [a]:hover:bg-muted/80",

        success:
          "border-success/20 bg-success/12 text-success dark:bg-success/15 [a]:hover:bg-success/18",

        warning:
          "border-warning/25 bg-warning/15 text-warning-foreground dark:bg-warning/18 dark:text-warning [a]:hover:bg-warning/22",

        info: "border-info/20 bg-info/12 text-info dark:bg-info/15 [a]:hover:bg-info/18",

        destructive:
          "border-destructive/20 bg-destructive/10 text-destructive focus-visible:ring-destructive/20 dark:bg-destructive/15 dark:focus-visible:ring-destructive/30 [a]:hover:bg-destructive/18",

        ghost:
          "text-foreground hover:bg-muted hover:text-foreground dark:hover:bg-muted/60",

        link: "rounded-none px-0 text-primary underline-offset-4 hover:underline focus-visible:border-transparent focus-visible:ring-0",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
);

function Badge({
  className,
  variant = "default",
  render,
  ...props
}: useRender.ComponentProps<"span"> & VariantProps<typeof badgeVariants>) {
  return useRender({
    defaultTagName: "span",
    props: mergeProps<"span">(
      {
        className: cn(badgeVariants({ variant }), className),
      },
      props,
    ),
    render,
    state: {
      slot: "badge",
      variant,
    },
  });
}

export { Badge, badgeVariants };
