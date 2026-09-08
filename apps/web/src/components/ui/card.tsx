import * as React from "react";

import { cn } from "@/lib/utils";

type CardVariant = "default" | "elevated" | "interactive" | "selected";

function Card({
  className,
  size = "default",
  variant = "default",
  ...props
}: React.ComponentProps<"div"> & {
  size?: "default" | "sm";
  variant?: CardVariant;
}) {
  return (
    <div
      data-slot="card"
      data-size={size}
      data-variant={variant}
      className={cn(
        [
          "group/card flex flex-col gap-(--card-spacing) overflow-hidden",
          "rounded-lg border text-sm text-card-foreground",
          "py-(--card-spacing)",
          "transition-[background-color,border-color,box-shadow,transform] duration-150",
          "[--card-spacing:--spacing(4)]",
          "has-data-[slot=card-footer]:pb-0",
          "has-[>img:first-child]:pt-0",
          "data-[size=sm]:[--card-spacing:--spacing(3)]",
          "data-[size=sm]:has-data-[slot=card-footer]:pb-0",
          "*:[img:first-child]:rounded-t-lg",
          "*:[img:last-child]:rounded-b-lg",

          /* Variants */
          "data-[variant=default]:border-border",
          "data-[variant=default]:bg-card",
          "data-[variant=default]:shadow-xs",

          "data-[variant=elevated]:border-border",
          "data-[variant=elevated]:bg-surface-elevated",
          "data-[variant=elevated]:text-surface-elevated-foreground",
          "data-[variant=elevated]:shadow-sm",

          "data-[variant=interactive]:border-border",
          "data-[variant=interactive]:bg-card",
          "data-[variant=interactive]:shadow-xs",
          "data-[variant=interactive]:hover:border-ring/35",
          "data-[variant=interactive]:hover:bg-surface-interactive/45",
          "data-[variant=interactive]:hover:shadow-sm",

          "data-[variant=selected]:border-primary/45",
          "data-[variant=selected]:bg-surface-interactive",
          "data-[variant=selected]:text-surface-interactive-foreground",
          "data-[variant=selected]:shadow-xs",
          "data-[variant=selected]:ring-1",
          "data-[variant=selected]:ring-primary/15",
        ].join(" "),
        className,
      )}
      {...props}
    />
  );
}

function CardHeader({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card-header"
      className={cn(
        [
          "group/card-header @container/card-header grid auto-rows-min items-start gap-1.5",
          "rounded-t-lg px-(--card-spacing)",
          "has-data-[slot=card-action]:grid-cols-[1fr_auto]",
          "has-data-[slot=card-description]:grid-rows-[auto_auto]",
          "[.border-b]:pb-(--card-spacing)",
        ].join(" "),
        className,
      )}
      {...props}
    />
  );
}

function CardTitle({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card-title"
      className={cn(
        "font-heading text-base leading-snug font-semibold group-data-[size=sm]/card:text-sm",
        className,
      )}
      {...props}
    />
  );
}

function CardDescription({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card-description"
      className={cn("text-sm leading-6 text-muted-foreground", className)}
      {...props}
    />
  );
}

function CardAction({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card-action"
      className={cn(
        "col-start-2 row-span-2 row-start-1 self-start justify-self-end",
        className,
      )}
      {...props}
    />
  );
}

function CardContent({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card-content"
      className={cn("px-(--card-spacing)", className)}
      {...props}
    />
  );
}

function CardFooter({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card-footer"
      className={cn(
        [
          "flex items-center rounded-b-lg border-t border-border",
          "bg-surface-muted/70 p-(--card-spacing)",
          "text-surface-muted-foreground",
        ].join(" "),
        className,
      )}
      {...props}
    />
  );
}

export {
  Card,
  CardHeader,
  CardFooter,
  CardTitle,
  CardAction,
  CardDescription,
  CardContent,
};
