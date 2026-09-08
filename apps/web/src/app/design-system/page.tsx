"use client";

import { useSyncExternalStore } from "react";
import { useTheme } from "@teispace/next-themes";
import { Moon, Sun } from "lucide-react";
import { LoaderCircle, Plus, Trash2 } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";

import { Button } from "@/components/ui/button";

import {
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { FileText, Folder, HardDrive, MoreHorizontal } from "lucide-react";
import {
  Archive,
  CheckCircle2,
  Clock3,
  Globe2,
  LockKeyhole,
  ShieldCheck,
  UploadCloud,
  XCircle,
} from "lucide-react";
import {
  Table,
  TableBody,
  TableCell,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { ComponentStatesDemo } from "./_components/component-states-demo";
import { FileDataTableDemo } from "./_components/file-data-table-demo";
import { NavigationOverlaysDemo } from "./_components/navigation-overlays-demo";
import { SystemStatesDemo } from "./_components/system-states-demo";
import { ResponsiveDirectionDemo } from "./_components/responsive-direction-demo";
import { FinalShowcaseLaunch } from "./_components/final-showcase-launch";
import { BrandRefreshDemo } from "./_components/brand-refresh-demo";

import { Badge } from "@/components/ui/badge";

const emptySubscribe = () => () => {};

function useMounted() {
  return useSyncExternalStore(
    emptySubscribe,
    () => true,
    () => false,
  );
}
const brandTokens = [
  {
    name: "Primary",
    token: "bg-primary",
    foreground: "text-primary-foreground",
    label: "Deep Cloud Blue",
  },
  {
    name: "Accent",
    token: "bg-accent",
    foreground: "text-accent-foreground",
    label: "Cyan Mist",
  },
  {
    name: "Info",
    token: "bg-info",
    foreground: "text-info-foreground",
    label: "Information",
  },
  {
    name: "Success",
    token: "bg-success",
    foreground: "text-success-foreground",
    label: "Healthy / Complete",
  },
  {
    name: "Warning",
    token: "bg-warning",
    foreground: "text-warning-foreground",
    label: "Attention",
  },
  {
    name: "Destructive",
    token: "bg-destructive",
    foreground: "text-destructive-foreground",
    label: "Danger / Failure",
  },
];

const surfaceTokens = [
  {
    name: "Background",
    className: "bg-background text-foreground",
  },
  {
    name: "Card",
    className: "bg-card text-card-foreground",
  },
  {
    name: "Surface",
    className: "bg-surface text-surface-foreground",
  },
  {
    name: "Muted Surface",
    className: "bg-surface-muted text-surface-muted-foreground",
  },
  {
    name: "Secondary",
    className: "bg-secondary text-secondary-foreground",
  },
  {
    name: "Muted",
    className: "bg-muted text-muted-foreground",
  },
];

function ThemeToggle() {
  const { resolvedTheme, setTheme } = useTheme();
  const mounted = useMounted();

  if (!mounted) {
    return (
      <button
        type="button"
        disabled
        className="inline-flex h-10 items-center gap-2 rounded-lg border border-border bg-card px-3 text-sm font-medium text-card-foreground shadow-sm"
        aria-label="Loading color theme"
      >
        <Moon className="size-4" />
        Theme
      </button>
    );
  }

  const isDark = resolvedTheme === "dark";

  return (
    <button
      type="button"
      onClick={() => setTheme(isDark ? "light" : "dark")}
      className="inline-flex h-10 items-center gap-2 rounded-lg border border-border bg-card px-3 text-sm font-medium text-card-foreground shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
      aria-label="Toggle color theme"
    >
      {isDark ? <Sun className="size-4" /> : <Moon className="size-4" />}
      {isDark ? "Light" : "Dark"}
    </button>
  );
}
export default function DesignSystemPage() {
  return (
    <main className="min-h-screen bg-background text-foreground">
      <div className="mx-auto w-full max-w-7xl px-5 py-10 sm:px-8 lg:px-10">
        <header className="flex flex-col gap-6 border-b border-border pb-8 sm:flex-row sm:items-end sm:justify-between">
          <div className="space-y-2">
            <div className="inline-flex items-center rounded-full border border-border bg-surface px-3 py-1 text-xs font-medium text-surface-foreground">
              STAGE 02 · Design System
            </div>

            <div>
              <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                STORVIA Design System
              </h1>
              <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                R02-refined visual language for a polished self-hosted cloud storage
                and file-sharing platform.
              </p>
            </div>
          </div>

          <ThemeToggle />
        </header>

        <FinalShowcaseLaunch />
        <BrandRefreshDemo />

        <section className="py-10">
          <div className="mb-5">
            <h2 className="text-lg font-semibold">Brand & semantic states</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Primary actions stay focused while status colors communicate
              meaning without dominating the interface.
            </p>
          </div>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {brandTokens.map((item) => (
              <article
                key={item.name}
                className="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
              >
                <div
                  className={`flex min-h-32 items-end p-5 ${item.token} ${item.foreground}`}
                >
                  <div>
                    <div className="text-lg font-semibold">{item.name}</div>
                    <div className="mt-1 text-sm opacity-80">{item.label}</div>
                  </div>
                </div>

                <div className="p-4">
                  <code className="text-xs text-muted-foreground">
                    {item.token.replace("bg-", "--")}
                  </code>
                </div>
              </article>
            ))}
          </div>
        </section>

        <section className="border-t border-border py-10">
          <div className="mb-5">
            <h2 className="text-lg font-semibold">Surface hierarchy</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              STORVIA uses subtle surface separation instead of heavy shadows.
            </p>
          </div>

          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {surfaceTokens.map((item) => (
              <article
                key={item.name}
                className={`min-h-36 rounded-xl border border-border p-5 ${item.className}`}
              >
                <div className="font-semibold">{item.name}</div>
                <p className="mt-2 max-w-xs text-sm opacity-70">
                  Cloud-oriented neutral surface designed for clear content
                  hierarchy.
                </p>
              </article>
            ))}
          </div>
        </section>

        <section className="border-t border-border py-10">
          <div className="mb-5">
            <h2 className="text-lg font-semibold">Controls preview</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              A small interaction sample to verify contrast, borders, focus, and
              surface relationships.
            </p>
          </div>

          <div className="rounded-2xl border border-border bg-card p-6 shadow-sm">
            <div className="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-end">
              <div>
                <label
                  htmlFor="storvia-demo-input"
                  className="mb-2 block text-sm font-medium"
                >
                  Search files
                </label>

                <input
                  id="storvia-demo-input"
                  type="text"
                  placeholder="Search files and folders..."
                  className="h-11 w-full rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/25"
                />
              </div>

              <div className="flex flex-wrap gap-3">
                <button
                  type="button"
                  className="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-5 text-sm font-semibold text-primary-foreground transition-opacity hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                  Upload files
                </button>

                <button
                  type="button"
                  className="inline-flex h-11 items-center justify-center rounded-lg border border-border bg-secondary px-5 text-sm font-semibold text-secondary-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                  New folder
                </button>
              </div>
            </div>

            <div className="mt-6 grid gap-3 sm:grid-cols-3">
              <div className="rounded-lg border border-border bg-surface p-4">
                <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                  Storage
                </div>
                <div className="mt-2 text-2xl font-semibold">428 GB</div>
                <div className="mt-1 text-sm text-muted-foreground">
                  of 1 TB used
                </div>
              </div>

              <div className="rounded-lg border border-border bg-surface p-4">
                <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                  Files
                </div>
                <div className="mt-2 text-2xl font-semibold">18,420</div>
                <div className="mt-1 text-sm text-muted-foreground">
                  across your workspace
                </div>
              </div>

              <div className="rounded-lg border border-border bg-surface p-4">
                <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                  Shared
                </div>
                <div className="mt-2 text-2xl font-semibold">126</div>
                <div className="mt-1 text-sm text-muted-foreground">
                  active shared items
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="border-t border-border py-10">
          <div className="rounded-2xl border border-sidebar-border bg-sidebar p-6 text-sidebar-foreground">
            <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <div className="text-sm font-semibold">Sidebar token check</div>
                <p className="mt-1 text-sm opacity-70">
                  Navigation surfaces remain visually distinct from the main
                  workspace.
                </p>
              </div>

              <div className="inline-flex rounded-lg bg-sidebar-primary px-4 py-2 text-sm font-semibold text-sidebar-primary-foreground">
                Active navigation
              </div>
            </div>
          </div>
        </section>
        <section className="border-t border-border py-10">
          <div className="mb-8">
            <h2 className="text-lg font-semibold">Typography</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              English and Arabic typography validation for STORVIA.
            </p>
          </div>

          <div className="grid gap-6 lg:grid-cols-2">
            {/* English */}
            <article
              lang="en"
              dir="ltr"
              className="rounded-2xl border border-border bg-card p-6 text-card-foreground"
            >
              <div className="mb-6 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                English · Geist
              </div>

              <div className="space-y-5">
                <div>
                  <div className="text-4xl font-semibold tracking-tight">
                    Your files. Your cloud.
                  </div>
                  <p className="mt-2 text-muted-foreground">
                    Secure self-hosted storage designed for professional teams.
                  </p>
                </div>

                <div className="space-y-2">
                  <p className="text-2xl font-semibold">Shared workspace</p>
                  <p className="text-lg font-medium">
                    Recent files and folders
                  </p>
                  <p className="text-base">
                    Manage documents, permissions, storage, and sharing from one
                    reliable workspace.
                  </p>
                  <p className="text-sm text-muted-foreground">
                    Last modified 12 minutes ago
                  </p>
                  <p className="text-xs text-muted-foreground">
                    STORVIA TYPOGRAPHY SYSTEM
                  </p>
                </div>

                <code className="block rounded-lg bg-muted p-3 font-mono text-sm">
                  node_01J8D9F3A7 · 428.25 GB
                </code>
              </div>
            </article>

            {/* Arabic */}
            <article
              lang="ar"
              dir="rtl"
              className="font-arabic rounded-2xl border border-border bg-card p-6 text-card-foreground"
            >
              <div className="mb-6 text-xs font-medium text-muted-foreground">
                العربية · IBM Plex Sans Arabic
              </div>

              <div className="space-y-5">
                <div>
                  <div className="text-4xl font-semibold tracking-tight">
                    ملفاتك. سحابتك.
                  </div>
                  <p className="mt-2 text-muted-foreground">
                    تخزين سحابي ذاتي الاستضافة مصمم للفرق والمؤسسات الاحترافية.
                  </p>
                </div>

                <div className="space-y-2">
                  <p className="text-2xl font-semibold">مساحة العمل المشتركة</p>
                  <p className="text-lg font-medium">
                    الملفات والمجلدات الأخيرة
                  </p>
                  <p className="text-base leading-7">
                    أدر الملفات والصلاحيات ومساحات التخزين والمشاركة من خلال
                    مساحة عمل واحدة آمنة وموثوقة.
                  </p>
                  <p className="text-sm text-muted-foreground">
                    آخر تعديل منذ 12 دقيقة
                  </p>
                  <p className="text-xs text-muted-foreground">
                    نظام الطباعة الخاص بـ STORVIA
                  </p>
                </div>

                <code
                  dir="ltr"
                  className="block rounded-lg bg-muted p-3 font-mono text-sm"
                >
                  node_01J8D9F3A7 · 428.25 GB
                </code>
              </div>
            </article>
          </div>
        </section>
        <section className="border-t border-border py-10">
          <div className="mb-8">
            <h2 className="text-lg font-semibold">
              Spacing, Radius, Shadows & Surfaces
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
              STORVIA structural design tokens for consistent enterprise
              interfaces.
            </p>
          </div>

          {/* Radius */}
          <div className="mb-10">
            <h3 className="mb-4 text-sm font-semibold">Radius system</h3>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
              {[
                ["6px", "rounded-sm", "Small"],
                ["8px", "rounded-md", "Medium"],
                ["12px", "rounded-lg", "Large"],
                ["16px", "rounded-xl", "XL"],
                ["20px", "rounded-2xl", "2XL"],
              ].map(([size, radius, label]) => (
                <div
                  key={label}
                  className={`${radius} border border-border bg-card p-5`}
                >
                  <p className="font-medium">{label}</p>
                  <p className="mt-1 text-sm text-muted-foreground">{size}</p>
                </div>
              ))}
            </div>
          </div>

          {/* Spacing */}
          <div className="mb-10">
            <h3 className="mb-4 text-sm font-semibold">4px spacing rhythm</h3>

            <div className="rounded-xl border border-border bg-card p-6">
              <div className="flex flex-wrap items-end gap-6">
                {[
                  ["4", "size-1"],
                  ["8", "size-2"],
                  ["12", "size-3"],
                  ["16", "size-4"],
                  ["20", "size-5"],
                  ["24", "size-6"],
                  ["32", "size-8"],
                  ["40", "size-10"],
                  ["48", "size-12"],
                  ["64", "size-16"],
                ].map(([label, size]) => (
                  <div key={label} className="text-center">
                    <div className={`${size} mx-auto rounded-sm bg-primary`} />
                    <div className="mt-2 text-xs text-muted-foreground">
                      {label}px
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Surface hierarchy */}
          <div className="mb-10">
            <h3 className="mb-4 text-sm font-semibold">Surface hierarchy</h3>

            <div className="grid gap-4 lg:grid-cols-4">
              <div className="rounded-lg border border-border bg-background p-5">
                <p className="font-semibold">Background</p>
                <p className="mt-2 text-sm text-muted-foreground">
                  Application foundation
                </p>
              </div>

              <div className="rounded-lg border border-border bg-surface p-5 text-surface-foreground">
                <p className="font-semibold">Surface</p>
                <p className="mt-2 text-sm opacity-70">
                  Standard workspace surface
                </p>
              </div>

              <div className="rounded-lg border border-border bg-surface-elevated p-5 text-surface-elevated-foreground shadow-sm">
                <p className="font-semibold">Elevated</p>
                <p className="mt-2 text-sm opacity-70">
                  Menus and raised content
                </p>
              </div>

              <div className="rounded-lg border border-border bg-surface-interactive p-5 text-surface-interactive-foreground">
                <p className="font-semibold">Interactive</p>
                <p className="mt-2 text-sm opacity-70">
                  Selection and interaction
                </p>
              </div>
            </div>
          </div>

          {/* Shadows */}
          <div>
            <h3 className="mb-4 text-sm font-semibold">Elevation system</h3>

            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
              {[
                ["XS", "shadow-xs", "Controls"],
                ["SM", "shadow-sm", "Cards"],
                ["MD", "shadow-md", "Dropdowns"],
                ["LG", "shadow-lg", "Dialogs"],
              ].map(([label, shadow, usage]) => (
                <div
                  key={label}
                  className={`${shadow} rounded-xl border border-border bg-surface-elevated p-6 text-surface-elevated-foreground`}
                >
                  <p className="text-lg font-semibold">{label}</p>
                  <p className="mt-1 text-sm text-muted-foreground">{usage}</p>
                  <code className="mt-5 block text-xs">{shadow}</code>
                </div>
              ))}
            </div>
          </div>
        </section>
        <section className="border-t border-border py-10">
          <div className="mb-8">
            <h2 className="text-lg font-semibold">Buttons</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              STORVIA action hierarchy, sizes, states, and icon behavior.
            </p>
          </div>

          <div className="space-y-8">
            {/* Variants */}
            <div>
              <h3 className="mb-4 text-sm font-semibold">Variants</h3>

              <div className="flex flex-wrap items-center gap-3">
                <Button>Upload files</Button>

                <Button variant="secondary">New folder</Button>

                <Button variant="outline">Properties</Button>

                <Button variant="ghost">Cancel</Button>

                <Button variant="destructive">Delete</Button>

                <Button variant="link">View details</Button>
              </div>
            </div>

            {/* Sizes */}
            <div>
              <h3 className="mb-4 text-sm font-semibold">Sizes</h3>

              <div className="flex flex-wrap items-center gap-3">
                <Button size="xs">Extra small</Button>
                <Button size="sm">Small</Button>
                <Button>Default</Button>
                <Button size="lg">Large</Button>
              </div>
            </div>

            {/* Icons */}
            <div>
              <h3 className="mb-4 text-sm font-semibold">Icons</h3>

              <div className="flex flex-wrap items-center gap-3">
                <Button>
                  <Plus data-icon="inline-start" />
                  New folder
                </Button>

                <Button variant="destructive">
                  <Trash2 data-icon="inline-start" />
                  Delete
                </Button>

                <Button size="icon" aria-label="Create folder">
                  <Plus />
                </Button>

                <Button
                  variant="outline"
                  size="icon-sm"
                  aria-label="Delete item"
                >
                  <Trash2 />
                </Button>
              </div>
            </div>

            {/* States */}
            <div>
              <h3 className="mb-4 text-sm font-semibold">States</h3>

              <div className="flex flex-wrap items-center gap-3">
                <Button disabled>Disabled</Button>

                <Button aria-busy="true">
                  <LoaderCircle
                    data-icon="inline-start"
                    className="animate-spin"
                  />
                  Uploading
                </Button>

                <Button variant="outline" aria-invalid="true">
                  Invalid action
                </Button>
              </div>
            </div>

            {/* RTL validation */}
            <div>
              <h3 className="mb-4 text-sm font-semibold">Arabic / RTL</h3>

              <div
                lang="ar"
                dir="rtl"
                className="font-arabic flex flex-wrap items-center gap-3"
              >
                <Button>
                  <Plus data-icon="inline-start" />
                  مجلد جديد
                </Button>

                <Button variant="secondary">رفع الملفات</Button>

                <Button variant="outline">الخصائص</Button>

                <Button variant="ghost">إلغاء</Button>

                <Button variant="destructive">
                  <Trash2 data-icon="inline-start" />
                  حذف
                </Button>
              </div>
            </div>
          </div>
        </section>
        <section className="border-t border-border py-10">
          <div className="mb-8">
            <h2 className="text-lg font-semibold">Inputs & Textarea</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              STORVIA form controls across default, focus, invalid, disabled,
              file, and RTL states.
            </p>
          </div>

          <div className="grid gap-8 lg:grid-cols-2">
            {/* Standard controls */}
            <div className="space-y-6">
              <div>
                <label
                  htmlFor="file-name"
                  className="mb-2 block text-sm font-medium"
                >
                  File name
                </label>

                <Input id="file-name" placeholder="Quarterly report.pdf" />

                <p className="mt-2 text-xs text-muted-foreground">
                  Enter a clear and recognizable file name.
                </p>
              </div>

              <div>
                <label
                  htmlFor="search-files"
                  className="mb-2 block text-sm font-medium"
                >
                  Search
                </label>

                <Input
                  id="search-files"
                  type="search"
                  placeholder="Search files and folders..."
                />
              </div>

              <div>
                <label
                  htmlFor="disabled-input"
                  className="mb-2 block text-sm font-medium"
                >
                  Disabled
                </label>

                <Input
                  id="disabled-input"
                  value="Storage policy controlled by administrator"
                  disabled
                  readOnly
                />
              </div>

              <div>
                <label
                  htmlFor="invalid-input"
                  className="mb-2 block text-sm font-medium"
                >
                  Invalid
                </label>

                <Input
                  id="invalid-input"
                  defaultValue="report.exe"
                  aria-invalid="true"
                  aria-describedby="invalid-input-error"
                />

                <p
                  id="invalid-input-error"
                  className="mt-2 text-xs text-destructive"
                >
                  This file type is not allowed.
                </p>
              </div>
            </div>

            {/* Textareas / File */}
            <div className="space-y-6">
              <div>
                <label
                  htmlFor="description"
                  className="mb-2 block text-sm font-medium"
                >
                  Description
                </label>

                <Textarea
                  id="description"
                  placeholder="Add a description for this shared folder..."
                />

                <p className="mt-2 text-xs text-muted-foreground">
                  Optional workspace information visible to collaborators.
                </p>
              </div>

              <div>
                <label
                  htmlFor="invalid-description"
                  className="mb-2 block text-sm font-medium"
                >
                  Invalid textarea
                </label>

                <Textarea
                  id="invalid-description"
                  defaultValue="Invalid sharing description"
                  aria-invalid="true"
                />
              </div>

              <div>
                <label
                  htmlFor="file-upload"
                  className="mb-2 block text-sm font-medium"
                >
                  File upload
                </label>

                <Input id="file-upload" type="file" />
              </div>
            </div>
          </div>

          {/* RTL */}
          <div
            lang="ar"
            dir="rtl"
            className="font-arabic mt-10 rounded-xl border border-border bg-card p-6"
          >
            <div className="mb-6">
              <h3 className="font-semibold">العربية / RTL</h3>
              <p className="mt-1 text-sm text-muted-foreground">
                اختبار اتجاه ومحاذاة حقول STORVIA باللغة العربية.
              </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
              <div>
                <label
                  htmlFor="arabic-file-name"
                  className="mb-2 block text-sm font-medium"
                >
                  اسم الملف
                </label>

                <Input id="arabic-file-name" placeholder="التقرير المالي.pdf" />
              </div>

              <div>
                <label
                  htmlFor="arabic-search"
                  className="mb-2 block text-sm font-medium"
                >
                  البحث
                </label>

                <Input
                  id="arabic-search"
                  type="search"
                  placeholder="ابحث في الملفات والمجلدات..."
                />
              </div>

              <div className="lg:col-span-2">
                <label
                  htmlFor="arabic-description"
                  className="mb-2 block text-sm font-medium"
                >
                  الوصف
                </label>

                <Textarea
                  id="arabic-description"
                  placeholder="أضف وصفًا للمجلد أو الملف..."
                />
              </div>
            </div>
          </div>
        </section>
        <section className="border-t border-border py-10">
          <div className="mb-8">
            <h2 className="text-lg font-semibold">Cards</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              STORVIA card hierarchy for content, metrics, files, folders, and
              interactive states.
            </p>
          </div>

          <div className="space-y-10">
            {/* Core variants */}
            <div>
              <h3 className="mb-4 text-sm font-semibold">Core variants</h3>

              <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                <Card>
                  <CardHeader>
                    <CardTitle>Default</CardTitle>
                    <CardDescription>
                      Standard workspace content.
                    </CardDescription>
                  </CardHeader>

                  <CardContent>
                    <p className="text-sm">
                      Used for regular content where subtle separation is
                      enough.
                    </p>
                  </CardContent>
                </Card>

                <Card variant="elevated">
                  <CardHeader>
                    <CardTitle>Elevated</CardTitle>
                    <CardDescription>Raised visual hierarchy.</CardDescription>
                  </CardHeader>

                  <CardContent>
                    <p className="text-sm">
                      Suitable for floating or emphasized information.
                    </p>
                  </CardContent>
                </Card>

                <Card variant="interactive">
                  <CardHeader>
                    <CardTitle>Interactive</CardTitle>
                    <CardDescription>Hover this card.</CardDescription>
                  </CardHeader>

                  <CardContent>
                    <p className="text-sm">
                      Intended for clickable folders, files, and workspace
                      items.
                    </p>
                  </CardContent>
                </Card>

                <Card variant="selected">
                  <CardHeader>
                    <CardTitle>Selected</CardTitle>
                    <CardDescription>Currently selected item.</CardDescription>
                  </CardHeader>

                  <CardContent>
                    <p className="text-sm">
                      Selection remains visible without relying on color alone.
                    </p>
                  </CardContent>
                </Card>
              </div>
            </div>

            {/* Metric cards */}
            <div>
              <h3 className="mb-4 text-sm font-semibold">Metric cards</h3>

              <div className="grid gap-5 md:grid-cols-3">
                <Card>
                  <CardHeader>
                    <CardDescription>Storage used</CardDescription>

                    <CardAction>
                      <div className="rounded-md bg-primary/10 p-2 text-primary">
                        <HardDrive className="size-4" />
                      </div>
                    </CardAction>

                    <CardTitle className="text-2xl">428 GB</CardTitle>
                  </CardHeader>

                  <CardContent>
                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                      <div className="h-full w-[42%] rounded-full bg-primary" />
                    </div>

                    <p className="mt-3 text-xs text-muted-foreground">
                      42% of 1 TB workspace storage
                    </p>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardDescription>Total files</CardDescription>
                    <CardTitle className="text-2xl">18,420</CardTitle>
                  </CardHeader>

                  <CardContent>
                    <p className="text-sm text-muted-foreground">
                      Files available across your organization.
                    </p>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardDescription>Shared items</CardDescription>
                    <CardTitle className="text-2xl">126</CardTitle>
                  </CardHeader>

                  <CardContent>
                    <p className="text-sm text-muted-foreground">
                      Active internal and external shares.
                    </p>
                  </CardContent>
                </Card>
              </div>
            </div>

            {/* File / Folder cards */}
            <div>
              <h3 className="mb-4 text-sm font-semibold">
                File & folder ready
              </h3>

              <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <Card variant="interactive">
                  <CardHeader>
                    <div className="mb-3 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                      <Folder className="size-5" />
                    </div>

                    <CardAction>
                      <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Folder actions"
                      >
                        <MoreHorizontal />
                      </Button>
                    </CardAction>

                    <CardTitle>Finance</CardTitle>

                    <CardDescription>24 files · 2.4 GB</CardDescription>
                  </CardHeader>

                  <CardFooter>Modified 18 minutes ago</CardFooter>
                </Card>

                <Card variant="interactive">
                  <CardHeader>
                    <div className="mb-3 flex size-10 items-center justify-center rounded-lg bg-info/10 text-info">
                      <FileText className="size-5" />
                    </div>

                    <CardAction>
                      <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="File actions"
                      >
                        <MoreHorizontal />
                      </Button>
                    </CardAction>

                    <CardTitle>Quarterly report.pdf</CardTitle>

                    <CardDescription>PDF · 8.6 MB</CardDescription>
                  </CardHeader>

                  <CardFooter>Modified yesterday</CardFooter>
                </Card>

                <Card variant="selected">
                  <CardHeader>
                    <div className="mb-3 flex size-10 items-center justify-center rounded-lg bg-primary/15 text-primary">
                      <Folder className="size-5" />
                    </div>

                    <CardAction>
                      <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Folder actions"
                      >
                        <MoreHorizontal />
                      </Button>
                    </CardAction>

                    <CardTitle>Projects</CardTitle>

                    <CardDescription>68 files · Selected</CardDescription>
                  </CardHeader>

                  <CardFooter>Shared with 8 people</CardFooter>
                </Card>
              </div>
            </div>

            {/* Compact card */}
            <div>
              <h3 className="mb-4 text-sm font-semibold">Compact size</h3>

              <Card size="sm" className="max-w-md">
                <CardHeader>
                  <CardTitle>Upload completed</CardTitle>
                  <CardDescription>quarterly-report.pdf</CardDescription>
                </CardHeader>

                <CardFooter>8.6 MB · Just now</CardFooter>
              </Card>
            </div>

            {/* Arabic RTL */}
            <div lang="ar" dir="rtl" className="font-arabic">
              <h3 className="mb-4 text-sm font-semibold">العربية / RTL</h3>

              <div className="grid gap-5 md:grid-cols-2">
                <Card variant="interactive">
                  <CardHeader>
                    <div className="mb-3 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                      <Folder className="size-5" />
                    </div>

                    <CardAction>
                      <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="إجراءات المجلد"
                      >
                        <MoreHorizontal />
                      </Button>
                    </CardAction>

                    <CardTitle>المستندات الإدارية</CardTitle>

                    <CardDescription>٣٢ ملفًا · ١٫٨ جيجابايت</CardDescription>
                  </CardHeader>

                  <CardFooter>آخر تعديل منذ ٢٠ دقيقة</CardFooter>
                </Card>

                <Card variant="selected">
                  <CardHeader>
                    <div className="mb-3 flex size-10 items-center justify-center rounded-lg bg-info/10 text-info">
                      <FileText className="size-5" />
                    </div>

                    <CardTitle>التقرير السنوي.pdf</CardTitle>

                    <CardDescription>ملف PDF · تم تحديده</CardDescription>
                  </CardHeader>

                  <CardFooter>تمت مشاركته مع ٤ مستخدمين</CardFooter>
                </Card>
              </div>
            </div>
          </div>
        </section>
        <section className="border-t border-border py-10">
          <div className="mb-8">
            <h2 className="text-lg font-semibold">Badges & Status</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              STORVIA semantic status indicators for files, sharing,
              synchronization, processing, and access control.
            </p>
          </div>

          <div className="space-y-10">
            {/* Variants */}
            <div>
              <h3 className="mb-4 text-sm font-semibold">Variants</h3>

              <div className="flex flex-wrap items-center gap-3">
                <Badge>Default</Badge>
                <Badge variant="secondary">Secondary</Badge>
                <Badge variant="outline">Outline</Badge>
                <Badge variant="muted">Muted</Badge>
                <Badge variant="success">Success</Badge>
                <Badge variant="warning">Warning</Badge>
                <Badge variant="info">Info</Badge>
                <Badge variant="destructive">Destructive</Badge>
              </div>
            </div>

            {/* Real STORVIA states */}
            <div>
              <h3 className="mb-4 text-sm font-semibold">
                File & workspace states
              </h3>

              <div className="flex flex-wrap items-center gap-3">
                <Badge variant="success">
                  <CheckCircle2 data-icon="inline-start" />
                  Synced
                </Badge>

                <Badge variant="info">
                  <Globe2 data-icon="inline-start" />
                  Shared
                </Badge>

                <Badge variant="outline">
                  <ShieldCheck data-icon="inline-start" />
                  Private
                </Badge>

                <Badge variant="warning">
                  <UploadCloud data-icon="inline-start" />
                  Uploading
                </Badge>

                <Badge variant="warning">
                  <Clock3 data-icon="inline-start" />
                  Processing
                </Badge>

                <Badge variant="destructive">
                  <XCircle data-icon="inline-start" />
                  Failed
                </Badge>

                <Badge variant="muted">
                  <Archive data-icon="inline-start" />
                  Archived
                </Badge>

                <Badge variant="secondary">
                  <LockKeyhole data-icon="inline-start" />
                  Locked
                </Badge>
              </div>
            </div>

            {/* File examples */}
            <div>
              <h3 className="mb-4 text-sm font-semibold">
                File metadata examples
              </h3>

              <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div className="rounded-lg border border-border bg-card p-4">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="font-medium">Annual Report.pdf</p>
                      <p className="mt-1 text-xs text-muted-foreground">
                        PDF · 12.8 MB
                      </p>
                    </div>

                    <Badge variant="success">Synced</Badge>
                  </div>
                </div>

                <div className="rounded-lg border border-border bg-card p-4">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="font-medium">Finance</p>
                      <p className="mt-1 text-xs text-muted-foreground">
                        Folder · 24 files
                      </p>
                    </div>

                    <Badge variant="info">Shared</Badge>
                  </div>
                </div>

                <div className="rounded-lg border border-border bg-card p-4">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="font-medium">backup.zip</p>
                      <p className="mt-1 text-xs text-muted-foreground">
                        ZIP · 4.2 GB
                      </p>
                    </div>

                    <Badge variant="warning">Processing</Badge>
                  </div>
                </div>

                <div className="rounded-lg border border-border bg-card p-4">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="font-medium">malformed-file.bin</p>
                      <p className="mt-1 text-xs text-muted-foreground">
                        Upload interrupted
                      </p>
                    </div>

                    <Badge variant="destructive">Failed</Badge>
                  </div>
                </div>
              </div>
            </div>

            {/* Arabic / RTL */}
            <div
              lang="ar"
              dir="rtl"
              className="font-arabic rounded-xl border border-border bg-card p-6"
            >
              <h3 className="mb-4 text-sm font-semibold">العربية / RTL</h3>

              <div className="flex flex-wrap items-center gap-3">
                <Badge variant="success">
                  <CheckCircle2 data-icon="inline-start" />
                  متزامن
                </Badge>

                <Badge variant="info">
                  <Globe2 data-icon="inline-start" />
                  مشترك
                </Badge>

                <Badge variant="outline">
                  <ShieldCheck data-icon="inline-start" />
                  خاص
                </Badge>

                <Badge variant="warning">
                  <UploadCloud data-icon="inline-start" />
                  جارٍ الرفع
                </Badge>

                <Badge variant="warning">
                  <Clock3 data-icon="inline-start" />
                  قيد المعالجة
                </Badge>

                <Badge variant="destructive">
                  <XCircle data-icon="inline-start" />
                  فشل
                </Badge>

                <Badge variant="muted">
                  <Archive data-icon="inline-start" />
                  مؤرشف
                </Badge>

                <Badge variant="secondary">
                  <LockKeyhole data-icon="inline-start" />
                  مقفل
                </Badge>
              </div>
            </div>
          </div>
        </section>
        <section className="border-t border-border py-10">
          <div className="mb-8">
            <h2 className="text-lg font-semibold">Tables</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              STORVIA data tables for files, sharing, administration, and dense
              enterprise workflows.
            </p>
          </div>

          <div className="space-y-10">
            {/* File manager table */}
            <div>
              <h3 className="mb-4 text-sm font-semibold">File Manager</h3>

              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Owner</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Size</TableHead>
                    <TableHead>Modified</TableHead>
                    <TableHead className="w-12">
                      <span className="sr-only">Actions</span>
                    </TableHead>
                  </TableRow>
                </TableHeader>

                <TableBody>
                  <TableRow data-interactive="true">
                    <TableCell>
                      <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-primary/10 text-primary">
                          <Folder className="size-4" />
                        </div>

                        <div>
                          <p className="font-medium">Finance</p>
                          <p className="text-xs text-muted-foreground">
                            24 files
                          </p>
                        </div>
                      </div>
                    </TableCell>

                    <TableCell>Mohammed</TableCell>

                    <TableCell>
                      <Badge variant="info">Shared</Badge>
                    </TableCell>

                    <TableCell>2.4 GB</TableCell>
                    <TableCell>18 min ago</TableCell>

                    <TableCell>
                      <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Folder actions"
                      >
                        <MoreHorizontal />
                      </Button>
                    </TableCell>
                  </TableRow>

                  <TableRow data-interactive="true" data-state="selected">
                    <TableCell>
                      <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-info/10 text-info">
                          <FileText className="size-4" />
                        </div>

                        <div>
                          <p className="font-medium">Quarterly Report.pdf</p>
                          <p className="text-xs text-muted-foreground">
                            PDF document
                          </p>
                        </div>
                      </div>
                    </TableCell>

                    <TableCell>Ahmed</TableCell>

                    <TableCell>
                      <Badge variant="success">Synced</Badge>
                    </TableCell>

                    <TableCell>8.6 MB</TableCell>
                    <TableCell>Yesterday</TableCell>

                    <TableCell>
                      <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="File actions"
                      >
                        <MoreHorizontal />
                      </Button>
                    </TableCell>
                  </TableRow>

                  <TableRow data-interactive="true">
                    <TableCell>
                      <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-info/10 text-info">
                          <FileText className="size-4" />
                        </div>

                        <div>
                          <p className="font-medium">backup.zip</p>
                          <p className="text-xs text-muted-foreground">
                            ZIP archive
                          </p>
                        </div>
                      </div>
                    </TableCell>

                    <TableCell>System</TableCell>

                    <TableCell>
                      <Badge variant="warning">Processing</Badge>
                    </TableCell>

                    <TableCell>4.2 GB</TableCell>
                    <TableCell>2 min ago</TableCell>

                    <TableCell>
                      <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="File actions"
                      >
                        <MoreHorizontal />
                      </Button>
                    </TableCell>
                  </TableRow>

                  <TableRow data-interactive="true">
                    <TableCell>
                      <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-destructive/10 text-destructive">
                          <FileText className="size-4" />
                        </div>

                        <div>
                          <p className="font-medium">corrupted-file.bin</p>
                          <p className="text-xs text-muted-foreground">
                            Upload interrupted
                          </p>
                        </div>
                      </div>
                    </TableCell>

                    <TableCell>Ali</TableCell>

                    <TableCell>
                      <Badge variant="destructive">Failed</Badge>
                    </TableCell>

                    <TableCell>—</TableCell>
                    <TableCell>5 min ago</TableCell>

                    <TableCell>
                      <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="File actions"
                      >
                        <MoreHorizontal />
                      </Button>
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>

            {/* Compact */}
            <div>
              <h3 className="mb-4 text-sm font-semibold">Compact density</h3>

              <Table density="compact">
                <TableHeader>
                  <TableRow>
                    <TableHead>User</TableHead>
                    <TableHead>Role</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Storage</TableHead>
                  </TableRow>
                </TableHeader>

                <TableBody>
                  <TableRow>
                    <TableCell className="font-medium">
                      Mohammed Khaled
                    </TableCell>
                    <TableCell>Administrator</TableCell>
                    <TableCell>
                      <Badge variant="success">Active</Badge>
                    </TableCell>
                    <TableCell>82.4 GB</TableCell>
                  </TableRow>

                  <TableRow>
                    <TableCell className="font-medium">Ahmed Ali</TableCell>
                    <TableCell>Member</TableCell>
                    <TableCell>
                      <Badge variant="secondary">Member</Badge>
                    </TableCell>
                    <TableCell>16.8 GB</TableCell>
                  </TableRow>

                  <TableRow>
                    <TableCell className="font-medium">Sarah Omar</TableCell>
                    <TableCell>Viewer</TableCell>
                    <TableCell>
                      <Badge variant="muted">Inactive</Badge>
                    </TableCell>
                    <TableCell>3.1 GB</TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>

            {/* Empty */}
            <div>
              <h3 className="mb-4 text-sm font-semibold">Empty state</h3>

              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Owner</TableHead>
                    <TableHead>Status</TableHead>
                  </TableRow>
                </TableHeader>

                <TableBody>
                  <TableRow>
                    <TableEmpty colSpan={3}>
                      No files found in this folder.
                    </TableEmpty>
                  </TableRow>
                </TableBody>
              </Table>
            </div>

            {/* Arabic RTL */}
            <div lang="ar" dir="rtl" className="font-arabic">
              <h3 className="mb-4 text-sm font-semibold">العربية / RTL</h3>

              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>الاسم</TableHead>
                    <TableHead>المالك</TableHead>
                    <TableHead>الحالة</TableHead>
                    <TableHead>الحجم</TableHead>
                    <TableHead>آخر تعديل</TableHead>
                  </TableRow>
                </TableHeader>

                <TableBody>
                  <TableRow data-interactive="true">
                    <TableCell>
                      <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-primary/10 text-primary">
                          <Folder className="size-4" />
                        </div>

                        <span className="font-medium">المستندات الإدارية</span>
                      </div>
                    </TableCell>

                    <TableCell>محمد خالد</TableCell>

                    <TableCell>
                      <Badge variant="info">مشترك</Badge>
                    </TableCell>

                    <TableCell>١٫٨ جيجابايت</TableCell>
                    <TableCell>منذ ٢٠ دقيقة</TableCell>
                  </TableRow>

                  <TableRow data-interactive="true" data-state="selected">
                    <TableCell>
                      <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-info/10 text-info">
                          <FileText className="size-4" />
                        </div>

                        <span className="font-medium">التقرير السنوي.pdf</span>
                      </div>
                    </TableCell>

                    <TableCell>أحمد علي</TableCell>

                    <TableCell>
                      <Badge variant="success">متزامن</Badge>
                    </TableCell>

                    <TableCell>٨٫٦ ميجابايت</TableCell>
                    <TableCell>أمس</TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </div>
        </section>
        <FileDataTableDemo />
        <ComponentStatesDemo />
        <NavigationOverlaysDemo />
        <SystemStatesDemo />
        <ResponsiveDirectionDemo />
        <footer className="border-t border-border pt-7 text-xs text-muted-foreground">
          STORVIA · Design System Foundation
        </footer>
      </div>
    </main>
  );
}
