import {
  ArrowUpRight,
  CheckCircle2,
  Cloud,
  Languages,
  LayoutDashboard,
  Moon,
} from "lucide-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { StorviaMark } from "@/components/brand/storvia-mark";

export function FinalShowcaseLaunch() {
  return (
    <section className="py-10">
      <Card variant="selected" className="relative overflow-hidden p-6 sm:p-8">
        <div className="pointer-events-none absolute -end-20 -top-24 size-72 rounded-full bg-primary/10 blur-3xl" />
        <div className="pointer-events-none absolute -bottom-32 start-1/3 size-64 rounded-full bg-info/10 blur-3xl" />

        <div className="relative grid gap-8 xl:grid-cols-[minmax(0,1fr)_360px] xl:items-center">
          <div>
            <div className="mb-5 flex items-center gap-3">
              <div className="flex size-11 items-center justify-center rounded-xl bg-brand-navy text-brand-cyan shadow-sm dark:bg-brand-blue dark:text-brand-navy">
                <StorviaMark className="size-7" />
              </div>
              <div>
                <p className="text-sm font-semibold">STORVIA</p>
                <p className="text-xs text-muted-foreground">Navy · Cloud Blue · Cyan</p>
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <Badge>
                <CheckCircle2 data-icon="inline-start" />
                STAGE 02I
              </Badge>
              <Badge variant="outline">Final composition</Badge>
            </div>

            <h2 className="mt-5 max-w-3xl text-2xl font-semibold tracking-tight sm:text-3xl">
              See STORVIA as a product, not a component catalog.
            </h2>

            <p className="mt-3 max-w-2xl text-sm leading-7 text-muted-foreground sm:text-base">
              A dedicated final composition combines the visual language,
              responsive behavior, RTL/LTR, themes, file states, navigation,
              actions, and enterprise workspace density into one cohesive
              STORVIA experience.
            </p>

            <div className="mt-6">
              <Button
                size="lg"
                nativeButton={false}
                render={<a href="/design-system/showcase" />}
              >
                Open final showcase
                <ArrowUpRight data-icon="inline-end" />
              </Button>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            {[
              { icon: LayoutDashboard, label: "Workspace composition" },
              { icon: Languages, label: "Arabic + English" },
              { icon: Moon, label: "Light + Dark" },
              { icon: Cloud, label: "Cloud identity" },
            ].map(({ icon: FeatureIcon, label }) => (
              <div
                key={label}
                className="rounded-lg border border-border bg-background/70 p-4 shadow-xs"
              >
                <FeatureIcon className="size-4 text-primary" />
                <p className="mt-3 text-sm font-medium">{label}</p>
              </div>
            ))}
          </div>
        </div>
      </Card>
    </section>
  );
}
