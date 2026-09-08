"use client";

import * as React from "react";
import {
  FileText,
  Folder,
  Languages,
  LayoutGrid,
  Menu,
  Monitor,
  Search,
  Share2,
  Smartphone,
  Tablet,
  Upload,
} from "lucide-react";

import { DataTable } from "@/components/data-table/data-table";
import { DataTableColumnHeader } from "@/components/data-table/data-table-column-header";
import type { StorviaColumnDef } from "@/components/data-table/data-table-features";
import type { DataTableLabels } from "@/components/data-table/data-table-labels";
import { Badge } from "@/components/ui/badge";
import { BidiText } from "@/components/ui/bidi-text";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

type Viewport = "mobile" | "tablet" | "desktop";
type Direction = "ltr" | "rtl";

type PreviewFile = {
  id: string;
  name: string;
  type: "folder" | "file";
  owner: string;
  status: "shared" | "synced";
  size: string;
};

const previewFiles: PreviewFile[] = [
  {
    id: "p1",
    name: "التقرير السنوي.pdf",
    type: "file",
    owner: "محمد خالد",
    status: "synced",
    size: "٨٫٦ MB",
  },
  {
    id: "p2",
    name: "نسخة_backup.zip",
    type: "file",
    owner: "فريق النظام",
    status: "shared",
    size: "٢٫٤ GB",
  },
  {
    id: "p3",
    name: "Q4-تقرير-final.xlsx",
    type: "file",
    owner: "أحمد علي",
    status: "synced",
    size: "١٢ MB",
  },
  {
    id: "p4",
    name: "المستندات الإدارية",
    type: "folder",
    owner: "الإدارة",
    status: "shared",
    size: "٣٢ ملفًا",
  },
];

const arabicLabels: Partial<DataTableLabels> = {
  columns: "الأعمدة",
  toggleColumns: "إظهار وإخفاء الأعمدة",
  clearSearch: "مسح البحث",
  rowsPerPage: "صفوف الصفحة",
  page: "صفحة",
  of: "من",
  rowsSelected: (selected, total) => `${selected} من ${total} محدد`,
  rowCount: (total) => `${total} صفوف`,
  firstPage: "الصفحة الأولى",
  previousPage: "الصفحة السابقة",
  nextPage: "الصفحة التالية",
  lastPage: "الصفحة الأخيرة",
  selectAllRows: "تحديد جميع صفوف الصفحة",
  selectRow: "تحديد الصف",
  columnLabel: (id) =>
    ({
      name: "الاسم",
      owner: "المالك",
      status: "الحالة",
      size: "الحجم",
    })[id] ?? id,
};

const rtlColumns: StorviaColumnDef<PreviewFile>[] = [
  {
    accessorKey: "name",
    header: ({ column }) => (
      <DataTableColumnHeader
        column={column}
        title="الاسم"
        sortAriaLabel="ترتيب حسب الاسم"
      />
    ),
    cell: ({ row }) => (
      <div className="flex min-w-52 items-center gap-3">
        <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
          {row.original.type === "folder" ? (
            <Folder className="size-4" />
          ) : (
            <FileText className="size-4" />
          )}
        </div>
        <BidiText className="block max-w-64 truncate font-medium">
          {row.original.name}
        </BidiText>
      </div>
    ),
  },
  {
    accessorKey: "owner",
    header: ({ column }) => (
      <DataTableColumnHeader
        column={column}
        title="المالك"
        sortAriaLabel="ترتيب حسب المالك"
      />
    ),
  },
  {
    accessorKey: "status",
    header: ({ column }) => (
      <DataTableColumnHeader
        column={column}
        title="الحالة"
        sortAriaLabel="ترتيب حسب الحالة"
      />
    ),
    cell: ({ row }) =>
      row.original.status === "shared" ? (
        <Badge variant="info">مشترك</Badge>
      ) : (
        <Badge variant="success">متزامن</Badge>
      ),
  },
  {
    accessorKey: "size",
    header: ({ column }) => (
      <DataTableColumnHeader
        column={column}
        title="الحجم"
        sortAriaLabel="ترتيب حسب الحجم"
      />
    ),
  },
];

const filenameStress = [
  "التقرير السنوي.pdf",
  "نسخة_backup.zip",
  "Q4-تقرير-final.xlsx",
  "مشروع_STORVIA-v2.docx",
  "2026-08-النسخة النهائية.tar.gz",
  "invoice_فاتورة_1042.pdf",
];

const viewportMeta: Record<
  Viewport,
  { label: string; width: string; icon: React.ReactNode }
> = {
  mobile: {
    label: "Mobile · 360",
    width: "360px",
    icon: <Smartphone className="size-4" />,
  },
  tablet: {
    label: "Tablet · 768",
    width: "768px",
    icon: <Tablet className="size-4" />,
  },
  desktop: {
    label: "Desktop · 1180",
    width: "1180px",
    icon: <Monitor className="size-4" />,
  },
};

export function ResponsiveDirectionDemo() {
  const [viewport, setViewport] = React.useState<Viewport>("desktop");
  const [direction, setDirection] = React.useState<Direction>("ltr");

  const isRtl = direction === "rtl";
  const isMobile = viewport === "mobile";
  const isDesktop = viewport === "desktop";

  return (
    <section className="border-t border-border py-10">
      <div className="mb-8">
        <div className="inline-flex rounded-full border border-border bg-surface px-3 py-1 text-xs font-semibold text-surface-foreground">
          STAGE 02H
        </div>

        <h2 className="mt-3 text-xl font-semibold">
          Responsive & Directionality Lab
        </h2>

        <p className="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
          Stress-test STORVIA across mobile, tablet, desktop, LTR, RTL,
          mixed-script filenames, dense tables, and narrow overlay-safe layouts.
        </p>
      </div>

      <div className="space-y-12">
        {/* Interactive preview controls */}
        <div>
          <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h3 className="text-sm font-semibold">
                Interactive viewport preview
              </h3>
              <p className="mt-1 text-sm text-muted-foreground">
                Switch direction and viewport without leaving the design system.
              </p>
            </div>

            <div className="flex flex-wrap gap-2">
              {(Object.keys(viewportMeta) as Viewport[]).map((item) => (
                <Button
                  key={item}
                  size="sm"
                  variant={viewport === item ? "default" : "outline"}
                  onClick={() => setViewport(item)}
                >
                  {viewportMeta[item].icon}
                  {viewportMeta[item].label}
                </Button>
              ))}

              <Button
                size="sm"
                variant="secondary"
                onClick={() =>
                  setDirection((current) => (current === "ltr" ? "rtl" : "ltr"))
                }
              >
                <Languages />
                {isRtl ? "RTL · العربية" : "LTR · English"}
              </Button>
            </div>
          </div>

          <div className="overflow-x-auto rounded-xl border border-border bg-surface-muted/40 p-4 sm:p-6">
            <div
              lang={isRtl ? "ar" : "en"}
              dir={direction}
              className={[
                "mx-auto overflow-hidden rounded-xl border border-border bg-background shadow-md",
                isRtl ? "font-arabic" : "",
              ].join(" ")}
              style={{
                width: viewportMeta[viewport].width,
                maxWidth: "100%",
              }}
            >
              <div className="flex min-h-14 items-center justify-between border-b border-border bg-card px-3 sm:px-4">
                <div className="flex min-w-0 items-center gap-2">
                  {isMobile ? (
                    <Button
                      variant="ghost"
                      size="icon-sm"
                      aria-label={isRtl ? "فتح القائمة" : "Open menu"}
                    >
                      <Menu />
                    </Button>
                  ) : null}

                  <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-xs font-bold text-primary-foreground">
                    S
                  </div>

                  <div className="min-w-0">
                    <p className="truncate text-sm font-semibold">STORVIA</p>
                    {!isMobile ? (
                      <p className="text-[0.6875rem] text-muted-foreground">
                        {isRtl ? "مساحة العمل" : "Workspace"}
                      </p>
                    ) : null}
                  </div>
                </div>

                <Button size="sm">
                  <Upload />
                  {!isMobile ? (isRtl ? "رفع الملفات" : "Upload") : null}
                </Button>
              </div>

              <div
                className={
                  isDesktop
                    ? "grid min-h-[430px] grid-cols-[190px_minmax(0,1fr)]"
                    : "min-h-[430px]"
                }
              >
                {isDesktop ? (
                  <aside className="border-e border-border bg-sidebar p-3">
                    <div className="space-y-1">
                      {[
                        {
                          icon: LayoutGrid,
                          label: isRtl ? "ملفاتي" : "My files",
                        },
                        {
                          icon: Share2,
                          label: isRtl ? "المشاركات" : "Shared",
                        },
                        {
                          icon: Folder,
                          label: isRtl ? "المجلدات" : "Folders",
                        },
                      ].map(({ icon: NavIcon, label }, index) => (
                        <div
                          key={label}
                          className={[
                            "flex h-9 items-center gap-2 rounded-md px-2.5 text-sm",
                            index === 0
                              ? "bg-sidebar-accent font-medium text-sidebar-accent-foreground"
                              : "text-muted-foreground",
                          ].join(" ")}
                        >
                          <NavIcon className="size-4" />
                          <span>{label}</span>
                        </div>
                      ))}
                    </div>
                  </aside>
                ) : null}

                <div className="min-w-0 p-3 sm:p-5">
                  {!isDesktop ? (
                    <div className="mb-4 flex gap-2 overflow-x-auto pb-1">
                      <Badge>{isRtl ? "ملفاتي" : "My files"}</Badge>
                      <Badge variant="outline">
                        {isRtl ? "مشترك" : "Shared"}
                      </Badge>
                      <Badge variant="outline">
                        {isRtl ? "الأخيرة" : "Recent"}
                      </Badge>
                    </div>
                  ) : null}

                  <div
                    className={
                      isMobile
                        ? "space-y-3"
                        : "flex items-end justify-between gap-4"
                    }
                  >
                    <div>
                      <h4 className="text-lg font-semibold">
                        {isRtl ? "ملفاتي" : "My files"}
                      </h4>
                      <p className="mt-1 text-xs text-muted-foreground">
                        {isRtl
                          ? "إدارة الملفات والمجلدات بأمان."
                          : "Manage files and folders securely."}
                      </p>
                    </div>

                    <div className={isMobile ? "w-full" : "w-64"}>
                      <div className="relative">
                        <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                          className="ps-9"
                          placeholder={
                            isRtl ? "البحث في الملفات..." : "Search files..."
                          }
                        />
                      </div>
                    </div>
                  </div>

                  <div
                    className={[
                      "mt-5 grid gap-3",
                      isMobile
                        ? "grid-cols-1"
                        : isDesktop
                          ? "grid-cols-3"
                          : "grid-cols-2",
                    ].join(" ")}
                  >
                    {previewFiles.slice(0, isMobile ? 3 : 4).map((file) => (
                      <Card
                        key={file.id}
                        variant="interactive"
                        className="min-w-0 p-4"
                      >
                        <div className="flex items-start gap-3">
                          <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            {file.type === "folder" ? (
                              <Folder className="size-4" />
                            ) : (
                              <FileText className="size-4" />
                            )}
                          </div>

                          <div className="min-w-0 flex-1">
                            <BidiText className="block truncate text-sm font-semibold">
                              {file.name}
                            </BidiText>
                            <p className="mt-1 truncate text-xs text-muted-foreground">
                              {file.owner}
                            </p>
                          </div>
                        </div>

                        <div className="mt-4 flex items-center justify-between gap-2">
                          <Badge
                            variant={
                              file.status === "shared" ? "info" : "success"
                            }
                          >
                            {isRtl
                              ? file.status === "shared"
                                ? "مشترك"
                                : "متزامن"
                              : file.status === "shared"
                                ? "Shared"
                                : "Synced"}
                          </Badge>
                          <span className="text-xs text-muted-foreground">
                            {file.size}
                          </span>
                        </div>
                      </Card>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Mixed-direction filenames */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">
            Mixed-script filename safety
          </h3>

          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {filenameStress.map((filename) => (
              <div
                key={filename}
                className="min-w-0 rounded-lg border border-border bg-card p-4 shadow-xs"
              >
                <p className="mb-2 text-xs font-medium text-muted-foreground">
                  dir=&quot;auto&quot; + Bidi isolation
                </p>
                <BidiText className="block truncate font-medium">
                  {filename}
                </BidiText>
              </div>
            ))}
          </div>
        </div>

        {/* Actual RTL DataTable */}
        <div lang="ar" dir="rtl" className="font-arabic">
          <div className="mb-4">
            <h3 className="text-sm font-semibold">DataTable عربية فعلية</h3>
            <p className="mt-1 text-sm text-muted-foreground">
              البحث، اختيار الأعمدة، التحديد، الترقيم، الأسهم، وأسماء الملفات
              المختلطة تعمل من اليمين إلى اليسار.
            </p>
          </div>

          <DataTable
            columns={rtlColumns}
            data={previewFiles}
            getRowId={(row) => row.id}
            searchPlaceholder="ابحث في الملفات أو المالك..."
            defaultPageSize={3}
            pageSizeOptions={[3, 5, 10]}
            emptyMessage="لا توجد نتائج مطابقة."
            labels={arabicLabels}
          />
        </div>

        {/* Breakpoint intent */}
        <div>
          <h3 className="mb-4 text-sm font-semibold">
            Responsive behavior contract
          </h3>

          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {[
              [
                "Mobile",
                "320–639px",
                "Stack actions, compact navigation, horizontal data overflow.",
              ],
              [
                "Tablet",
                "640–1023px",
                "Two-column content, condensed navigation, full-width dialogs.",
              ],
              [
                "Laptop",
                "1024–1279px",
                "Productivity layout with controlled density.",
              ],
              [
                "Desktop",
                "1280px+",
                "Full navigation and dense file-management workspace.",
              ],
            ].map(([title, range, description]) => (
              <div
                key={title}
                className="rounded-lg border border-border bg-card p-4 shadow-xs"
              >
                <div className="flex items-center justify-between gap-3">
                  <p className="font-semibold">{title}</p>
                  <Badge variant="outline">{range}</Badge>
                </div>
                <p className="mt-3 text-sm leading-6 text-muted-foreground">
                  {description}
                </p>
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
