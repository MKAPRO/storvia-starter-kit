import * as React from "react";
import { mergeProps } from "@base-ui/react/merge-props";
import { useRender } from "@base-ui/react/use-render";

import { cn } from "@/lib/utils";

function Navigation({ className, ...props }: React.ComponentProps<"nav">) {
  return (
    <nav
      data-slot="navigation"
      className={cn("w-full", className)}
      {...props}
    />
  );
}

function NavigationSection({
  className,
  ...props
}: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="navigation-section"
      className={cn("space-y-1.5", className)}
      {...props}
    />
  );
}

function NavigationLabel({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="navigation-label"
      className={cn(
        "px-2 text-[0.6875rem] font-semibold uppercase tracking-[0.08em] text-muted-foreground",
        className,
      )}
      {...props}
    />
  );
}

function NavigationList({ className, ...props }: React.ComponentProps<"ul">) {
  return (
    <ul
      data-slot="navigation-list"
      className={cn("space-y-1", className)}
      {...props}
    />
  );
}

function NavigationItem({ className, ...props }: React.ComponentProps<"li">) {
  return (
    <li data-slot="navigation-item" className={cn(className)} {...props} />
  );
}

function NavigationLink({
  className,
  active = false,
  render,
  ...props
}: useRender.ComponentProps<"a"> & { active?: boolean }) {
  return useRender({
    defaultTagName: "a",
    props: mergeProps<"a">(
      {
        "aria-current": active ? "page" : undefined,
        className: cn(
          [
            "group/nav-link flex min-h-9 items-center gap-2.5 rounded-md px-2.5",
            "text-sm font-medium text-muted-foreground outline-none",
            "transition-[color,background-color,box-shadow] duration-150",
            "hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
            "focus-visible:ring-2 focus-visible:ring-sidebar-ring/30",
            active &&
              "bg-sidebar-accent text-sidebar-accent-foreground shadow-xs",
            "[&_svg]:size-4 [&_svg]:shrink-0",
          ],
          className,
        ),
      },
      props,
    ),
    render,
    state: {
      slot: "navigation-link",
      active,
    },
  });
}

function NavigationBadge({
  className,
  ...props
}: React.ComponentProps<"span">) {
  return (
    <span
      data-slot="navigation-badge"
      className={cn(
        [
          "ms-auto rounded-full bg-muted px-2 py-0.5",
          "text-[0.6875rem] font-medium text-muted-foreground",
          "group-aria-[current=page]/nav-link:bg-primary/10",
          "group-aria-[current=page]/nav-link:text-primary",
        ].join(" "),
        className,
      )}
      {...props}
    />
  );
}

export {
  Navigation,
  NavigationSection,
  NavigationLabel,
  NavigationList,
  NavigationItem,
  NavigationLink,
  NavigationBadge,
};
