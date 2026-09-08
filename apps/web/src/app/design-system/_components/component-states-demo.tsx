"use client"

import {
  CheckCircle2,
  LoaderCircle,
  LockKeyhole,
  XCircle,
} from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import {
  Table,
  TableBody,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Textarea } from "@/components/ui/textarea"

export function ComponentStatesDemo() {
  return (
    <section className="border-t border-border py-10">
      <div className="mb-8">
        <h2 className="text-lg font-semibold">
          Component States
        </h2>

        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
          Unified STORVIA interaction states across actions, form controls,
          content surfaces, status indicators, and data views.
        </p>
      </div>

      <div className="space-y-10">
        {/* Action states */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">
            Actions
          </h3>

          <div className="flex flex-wrap items-center gap-3">
            <Button>Default</Button>

            <Button variant="outline">
              Secondary action
            </Button>

            <Button aria-busy="true">
              <LoaderCircle
                data-icon="inline-start"
                className="animate-spin"
              />
              Loading
            </Button>

            <Button disabled>
              Disabled
            </Button>

            <Button variant="destructive">
              Destructive
            </Button>
          </div>
        </div>

        {/* Form states */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">
            Form controls
          </h3>

          <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            <div>
              <label
                htmlFor="state-default"
                className="mb-2 block text-sm font-medium"
              >
                Default
              </label>

              <Input
                id="state-default"
                placeholder="File name..."
              />
            </div>

            <div>
              <label
                htmlFor="state-readonly"
                className="mb-2 block text-sm font-medium"
              >
                Read-only
              </label>

              <Input
                id="state-readonly"
                value="policy.pdf"
                readOnly
                className="bg-surface-muted/60"
              />
            </div>

            <div>
              <label
                htmlFor="state-disabled"
                className="mb-2 block text-sm font-medium"
              >
                Disabled
              </label>

              <Input
                id="state-disabled"
                value="Managed by administrator"
                disabled
                readOnly
              />
            </div>

            <div>
              <label
                htmlFor="state-invalid"
                className="mb-2 block text-sm font-medium"
              >
                Invalid
              </label>

              <Input
                id="state-invalid"
                defaultValue="blocked.exe"
                aria-invalid="true"
                aria-describedby="state-invalid-message"
              />

              <p
                id="state-invalid-message"
                className="mt-2 flex items-center gap-1.5 text-xs text-destructive"
              >
                <XCircle className="size-3.5" />
                File type is not allowed.
              </p>
            </div>
          </div>

          <div className="mt-5 max-w-2xl">
            <label
              htmlFor="state-textarea"
              className="mb-2 block text-sm font-medium"
            >
              Read-only description
            </label>

            <Textarea
              id="state-textarea"
              value="This description is controlled by the workspace policy."
              readOnly
              className="bg-surface-muted/60"
            />
          </div>
        </div>

        {/* Surface states */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">
            Content surfaces
          </h3>

          <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            <Card>
              <CardHeader>
                <CardTitle>Default</CardTitle>
                <CardDescription>
                  Standard content surface.
                </CardDescription>
              </CardHeader>

              <CardContent>
                <Badge variant="secondary">
                  Normal
                </Badge>
              </CardContent>
            </Card>

            <Card variant="interactive">
              <CardHeader>
                <CardTitle>Interactive</CardTitle>
                <CardDescription>
                  Hover to verify interaction feedback.
                </CardDescription>
              </CardHeader>

              <CardContent>
                <Badge variant="info">
                  Interactive
                </Badge>
              </CardContent>
            </Card>

            <Card variant="selected">
              <CardHeader>
                <CardTitle>Selected</CardTitle>
                <CardDescription>
                  Persistent selection state.
                </CardDescription>
              </CardHeader>

              <CardContent>
                <Badge>
                  Selected
                </Badge>
              </CardContent>
            </Card>

            <Card className="opacity-60">
              <CardHeader>
                <CardTitle>Disabled context</CardTitle>
                <CardDescription>
                  Visible but unavailable.
                </CardDescription>
              </CardHeader>

              <CardContent>
                <Badge variant="muted">
                  Unavailable
                </Badge>
              </CardContent>
            </Card>
          </div>
        </div>

        {/* Semantic states */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">
            Semantic feedback
          </h3>

          <div className="flex flex-wrap items-center gap-3">
            <Badge variant="success">
              <CheckCircle2 data-icon="inline-start" />
              Success
            </Badge>

            <Badge variant="warning">
              <LoaderCircle
                data-icon="inline-start"
                className="animate-spin"
              />
              Busy
            </Badge>

            <Badge variant="info">
              Processing
            </Badge>

            <Badge variant="destructive">
              <XCircle data-icon="inline-start" />
              Error
            </Badge>

            <Badge variant="secondary">
              <LockKeyhole data-icon="inline-start" />
              Locked
            </Badge>

            <Badge variant="muted">
              Inactive
            </Badge>
          </div>
        </div>

        {/* Empty state */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">
            Empty data
          </h3>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Modified</TableHead>
              </TableRow>
            </TableHeader>

            <TableBody>
              <TableRow>
                <TableEmpty colSpan={3}>
                  <div className="mx-auto flex max-w-sm flex-col items-center py-3 text-center">
                    <div className="mb-3 flex size-10 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                      <span className="text-lg">—</span>
                    </div>

                    <p className="font-medium text-foreground">
                      No files yet
                    </p>

                    <p className="mt-1 text-sm text-muted-foreground">
                      Upload a file or create a folder to get started.
                    </p>
                  </div>
                </TableEmpty>
              </TableRow>
            </TableBody>
          </Table>
        </div>

        {/* Arabic / RTL */}
        <div
          lang="ar"
          dir="rtl"
          className="font-arabic rounded-xl border border-border bg-card p-6"
        >
          <div className="mb-6">
            <h3 className="text-sm font-semibold">
              الحالات بالعربية / RTL
            </h3>

            <p className="mt-1 text-sm text-muted-foreground">
              نفس الحالات يجب أن تبقى واضحة ومتوازنة بصريًا في الاتجاه من
              اليمين إلى اليسار.
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <Button>افتراضي</Button>

            <Button aria-busy="true">
              <LoaderCircle
                data-icon="inline-start"
                className="animate-spin"
              />
              جارٍ التنفيذ
            </Button>

            <Button disabled>
              غير متاح
            </Button>

            <Badge variant="success">
              ناجح
            </Badge>

            <Badge variant="warning">
              قيد المعالجة
            </Badge>

            <Badge variant="destructive">
              خطأ
            </Badge>

            <Badge variant="secondary">
              مقفل
            </Badge>
          </div>

          <div className="mt-6 grid gap-5 md:grid-cols-2">
            <div>
              <label
                htmlFor="state-arabic-default"
                className="mb-2 block text-sm font-medium"
              >
                اسم الملف
              </label>

              <Input
                id="state-arabic-default"
                placeholder="اسم الملف..."
              />
            </div>

            <div>
              <label
                htmlFor="state-arabic-invalid"
                className="mb-2 block text-sm font-medium"
              >
                حالة الخطأ
              </label>

              <Input
                id="state-arabic-invalid"
                defaultValue="ملف.exe"
                aria-invalid="true"
              />
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}
