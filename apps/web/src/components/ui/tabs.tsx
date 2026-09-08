"use client"

import { Tabs as TabsPrimitive } from "@base-ui/react/tabs"
import { cva, type VariantProps } from "class-variance-authority"

import { cn } from "@/lib/utils"

function Tabs({
  className,
  orientation = "horizontal",
  ...props
}: TabsPrimitive.Root.Props) {
  return (
    <TabsPrimitive.Root
      data-slot="tabs"
      data-orientation={orientation}
      className={cn("group/tabs flex data-horizontal:flex-col gap-4", className)}
      {...props}
    />
  )
}

const tabsListVariants = cva(
  "group/tabs-list inline-flex max-w-full w-fit items-center justify-center overflow-x-auto text-muted-foreground group-data-vertical/tabs:h-fit group-data-vertical/tabs:flex-col group-data-vertical/tabs:overflow-visible",
  {
    variants: {
      variant: {
        default: "rounded-lg bg-muted p-1",
        line: "gap-1 border-b border-border bg-transparent p-0",
      },
    },
    defaultVariants: { variant: "default" },
  }
)

function TabsList({
  className,
  variant = "default",
  ...props
}: TabsPrimitive.List.Props & VariantProps<typeof tabsListVariants>) {
  return (
    <TabsPrimitive.List
      data-slot="tabs-list"
      data-variant={variant}
      className={cn(tabsListVariants({ variant }), className)}
      {...props}
    />
  )
}

function TabsTrigger({
  className,
  ...props
}: TabsPrimitive.Tab.Props) {
  return (
    <TabsPrimitive.Tab
      data-slot="tabs-trigger"
      className={cn(
        [
          "relative inline-flex h-8 items-center justify-center gap-2 whitespace-nowrap",
          "rounded-md px-3 text-sm font-medium text-muted-foreground outline-none",
          "transition-[color,background-color,box-shadow]",
          "hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/30",
          "disabled:pointer-events-none disabled:opacity-50",
          "data-active:bg-background data-active:text-foreground data-active:shadow-xs",
          "group-data-[variant=line]/tabs-list:rounded-none",
          "group-data-[variant=line]/tabs-list:bg-transparent",
          "group-data-[variant=line]/tabs-list:data-active:bg-transparent",
          "group-data-[variant=line]/tabs-list:data-active:shadow-none",
          "group-data-[variant=line]/tabs-list:data-active:text-primary",
          "group-data-[variant=line]/tabs-list:after:absolute",
          "group-data-[variant=line]/tabs-list:after:inset-x-0",
          "group-data-[variant=line]/tabs-list:after:-bottom-px",
          "group-data-[variant=line]/tabs-list:after:h-0.5",
          "group-data-[variant=line]/tabs-list:after:bg-primary",
          "group-data-[variant=line]/tabs-list:after:opacity-0",
          "group-data-[variant=line]/tabs-list:data-active:after:opacity-100",
          "[&_svg]:size-4 [&_svg]:shrink-0",
        ].join(" "),
        className
      )}
      {...props}
    />
  )
}

function TabsContent({
  className,
  ...props
}: TabsPrimitive.Panel.Props) {
  return (
    <TabsPrimitive.Panel
      data-slot="tabs-content"
      className={cn("flex-1 outline-none", className)}
      {...props}
    />
  )
}

export { Tabs, TabsList, TabsTrigger, TabsContent, tabsListVariants }
