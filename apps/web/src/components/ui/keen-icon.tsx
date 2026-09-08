import * as React from "react";

import { cn } from "@/lib/utils";

export type KeenIconStyle = "outline" | "solid";

type KeenIconProps = Omit<React.HTMLAttributes<HTMLElement>, "children"> & {
  name: string;
  variant?: KeenIconStyle;
};

export function KeenIcon({
  name,
  variant = "outline",
  className,
  ...props
}: KeenIconProps) {
  return (
    <i
      aria-hidden="true"
      className={cn(`ki-${name}`, variant === "solid" ? "ki-solid" : "ki-outline", className)}
      {...props}
    />
  );
}
