import { CheckCircle2, Cloud, Folder, ShieldCheck } from "lucide-react"

import { StorviaFlowVisual } from "@/components/brand/storvia-flow-visual"
import { StorviaMark } from "@/components/brand/storvia-mark"
import { Badge } from "@/components/ui/badge"
import { Card } from "@/components/ui/card"

const palette = [
  {
    name: "Deep Navy",
    role: "Foundation / trust",
    className: "bg-brand-navy text-white",
  },
  {
    name: "Cloud Blue",
    role: "Primary interaction",
    className: "bg-brand-blue text-white",
  },
  {
    name: "Signal Cyan",
    role: "Cloud / data accent",
    className: "bg-brand-cyan text-brand-navy",
  },
  {
    name: "Ice Surface",
    role: "Soft technical surface",
    className: "bg-brand-ice text-brand-navy",
  },
]

export function BrandRefreshDemo() {
  return (
    <section className="border-t border-border py-10">
      <div className="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <Badge>R02</Badge>
            <Badge variant="outline">Visual Identity Refinement</Badge>
          </div>

          <h2 className="mt-4 text-xl font-semibold tracking-tight sm:text-2xl">
            A cloud identity with its own signature.
          </h2>

          <p className="mt-2 max-w-3xl text-sm leading-7 text-muted-foreground">
            STORVIA now centers on deep navy, cloud blue, restrained cyan, and
            cool technical surfaces. Purple-spectrum brand hues are excluded
            so the product reads as secure cloud infrastructure rather than a
            trend-driven template.
          </p>
        </div>

        <div className="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-xs">
          <div className="flex size-10 items-center justify-center rounded-lg bg-brand-navy text-brand-cyan dark:bg-brand-blue dark:text-brand-navy">
            <StorviaMark className="size-6" />
          </div>
          <div>
            <p className="text-sm font-semibold">STORVIA visual signature</p>
            <p className="text-xs text-muted-foreground">
              Storage flow · control · connection
            </p>
          </div>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {palette.map((item) => (
          <article
            key={item.name}
            className="overflow-hidden rounded-xl border border-border bg-card shadow-xs"
          >
            <div className={`min-h-28 p-5 ${item.className}`}>
              <p className="font-semibold">{item.name}</p>
              <p className="mt-1 text-sm opacity-75">{item.role}</p>
            </div>
            <div className="p-4 text-xs text-muted-foreground">
              Semantic brand token
            </div>
          </article>
        ))}
      </div>

      <div className="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.65fr)]">
        <Card className="relative overflow-hidden p-5 sm:p-7">
          <div className="absolute inset-x-0 top-0 h-px bg-brand-cyan/60" />

          <div className="grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(360px,1.1fr)] lg:items-center">
            <div>
              <Badge variant="info">
                <Cloud data-icon="inline-start" />
                Cloud visual language
              </Badge>

              <h3 className="mt-4 text-2xl font-semibold tracking-tight">
                Strokes that explain the product.
              </h3>

              <p className="mt-3 text-sm leading-7 text-muted-foreground">
                Decorative vectors are tied to storage, transfer, connection,
                and control. They stay quiet and appear as one focal visual,
                not repeated decoration across every card.
              </p>

              <div className="mt-5 space-y-2.5 text-sm">
                {[
                  "One focal illustration per major surface",
                  "Thin cloud/data strokes instead of decorative blobs",
                  "Cyan is an accent, not the dominant brand color",
                  "No violet, purple, or magenta in the brand spectrum",
                ].map((rule) => (
                  <div key={rule} className="flex items-start gap-2">
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-success" />
                    <span>{rule}</span>
                  </div>
                ))}
              </div>
            </div>

            <StorviaFlowVisual className="mx-auto max-w-xl" />
          </div>
        </Card>

        <div className="grid gap-4 sm:grid-cols-3 xl:grid-cols-1">
          {[
            {
              icon: Folder,
              title: "Storage-first",
              text: "Folder and file metaphors stay central.",
            },
            {
              icon: ShieldCheck,
              title: "Enterprise confidence",
              text: "Navy carries trust, control, and security.",
            },
            {
              icon: Cloud,
              title: "Cloud signal",
              text: "Blue/cyan strokes communicate movement and connection.",
            },
          ].map(({ icon: Icon, title, text }) => (
            <Card key={title} variant="interactive" className="p-4">
              <div className="flex size-9 items-center justify-center rounded-lg bg-brand-ice text-brand-blue dark:text-brand-cyan">
                <Icon className="size-4" />
              </div>
              <p className="mt-4 font-semibold">{title}</p>
              <p className="mt-1 text-sm leading-6 text-muted-foreground">
                {text}
              </p>
            </Card>
          ))}
        </div>
      </div>
    </section>
  )
}
