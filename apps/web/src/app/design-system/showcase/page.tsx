"use client"

import * as React from "react"
import { useSyncExternalStore } from "react"
import { useTheme } from "@teispace/next-themes"
import {
  Archive,
  ArrowLeft,
  Bell,
  CheckCircle2,
  Cloud,
  FileArchive,
  FileImage,
  FileText,
  Folder,
  Grid2X2,
  HardDrive,
  Home,
  Languages,
  Link2,
  List,
  MoreHorizontal,
  Moon,
  Plus,
  Search,
  Settings,
  Share2,
  ShieldCheck,
  Star,
  Sun,
  Upload,
  Users,
} from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { BidiText } from "@/components/ui/bidi-text"
import { Button } from "@/components/ui/button"
import { Card } from "@/components/ui/card"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { toast } from "@/components/ui/toast"
import { StorviaMark } from "@/components/brand/storvia-mark"
import { StorviaFlowVisual } from "@/components/brand/storvia-flow-visual"

type Locale = "en" | "ar"
type ViewMode = "list" | "grid"

type WorkspaceFile = {
  id: string
  name: string
  kind: "folder" | "pdf" | "image" | "archive"
  owner: string
  size: string
  modifiedEn: string
  modifiedAr: string
  status: "shared" | "private" | "synced"
}

const emptySubscribe = () => () => {}

function useMounted() {
  return useSyncExternalStore(
    emptySubscribe,
    () => true,
    () => false,
  )
}

const navItems = [
  { key: "home", icon: Home },
  { key: "files", icon: Folder },
  { key: "shared", icon: Share2 },
  { key: "archive", icon: Archive },
] as const

const quickFolders = [
  {
    id: "q1",
    nameEn: "Finance",
    nameAr: "المالية",
    metaEn: "42 files · 2.4 GB",
    metaAr: "٤٢ ملفًا · ٢٫٤ جيجابايت",
    icon: Folder,
    tone: "primary",
  },
  {
    id: "q2",
    nameEn: "Projects",
    nameAr: "المشاريع",
    metaEn: "68 files · 4 shared",
    metaAr: "٦٨ ملفًا · ٤ مشاركات",
    icon: Folder,
    tone: "info",
  },
  {
    id: "q3",
    nameEn: "Brand assets",
    nameAr: "أصول الهوية",
    metaEn: "184 files · 6.8 GB",
    metaAr: "١٨٤ ملفًا · ٦٫٨ جيجابايت",
    icon: FileImage,
    tone: "success",
  },
  {
    id: "q4",
    nameEn: "Legal",
    nameAr: "الشؤون القانونية",
    metaEn: "Private · 920 MB",
    metaAr: "خاص · ٩٢٠ ميجابايت",
    icon: ShieldCheck,
    tone: "warning",
  },
] as const

const workspaceFiles: WorkspaceFile[] = [
  {
    id: "f1",
    name: "التقرير السنوي.pdf",
    kind: "pdf",
    owner: "Finance",
    size: "8.6 MB",
    modifiedEn: "18 min ago",
    modifiedAr: "منذ ١٨ دقيقة",
    status: "synced",
  },
  {
    id: "f2",
    name: "Q4-تقرير-final.xlsx",
    kind: "archive",
    owner: "Operations",
    size: "12.4 MB",
    modifiedEn: "Today",
    modifiedAr: "اليوم",
    status: "shared",
  },
  {
    id: "f3",
    name: "Product Screenshots",
    kind: "folder",
    owner: "Design",
    size: "3.3 GB",
    modifiedEn: "Yesterday",
    modifiedAr: "أمس",
    status: "shared",
  },
  {
    id: "f4",
    name: "storage-policy.pdf",
    kind: "pdf",
    owner: "Administrator",
    size: "2.1 MB",
    modifiedEn: "Aug 19",
    modifiedAr: "١٩ أغسطس",
    status: "private",
  },
  {
    id: "f5",
    name: "نسخة_backup.zip",
    kind: "archive",
    owner: "System",
    size: "4.2 GB",
    modifiedEn: "Aug 18",
    modifiedAr: "١٨ أغسطس",
    status: "synced",
  },
]

const activity = [
  {
    id: "a1",
    icon: Upload,
    en: "Quarterly Report.pdf was uploaded",
    ar: "تم رفع Quarterly Report.pdf",
    timeEn: "2 min ago",
    timeAr: "منذ دقيقتين",
  },
  {
    id: "a2",
    icon: Share2,
    en: "Finance folder was shared with 3 people",
    ar: "تمت مشاركة مجلد المالية مع ٣ مستخدمين",
    timeEn: "18 min ago",
    timeAr: "منذ ١٨ دقيقة",
  },
  {
    id: "a3",
    icon: CheckCircle2,
    en: "Workspace backup completed",
    ar: "اكتملت النسخة الاحتياطية لمساحة العمل",
    timeEn: "1 hour ago",
    timeAr: "منذ ساعة",
  },
]

function fileIcon(kind: WorkspaceFile["kind"]) {
  if (kind === "folder") return Folder
  if (kind === "image") return FileImage
  if (kind === "archive") return FileArchive
  return FileText
}

function statusBadge(status: WorkspaceFile["status"], isAr: boolean) {
  if (status === "shared") {
    return <Badge variant="info">{isAr ? "مشترك" : "Shared"}</Badge>
  }

  if (status === "synced") {
    return <Badge variant="success">{isAr ? "متزامن" : "Synced"}</Badge>
  }

  return <Badge variant="outline">{isAr ? "خاص" : "Private"}</Badge>
}

export default function StorviaFinalShowcasePage() {
  const { resolvedTheme, setTheme } = useTheme()
  const mounted = useMounted()

  const [locale, setLocale] = React.useState<Locale>("en")
  const [activeNav, setActiveNav] = React.useState("files")
  const [viewMode, setViewMode] = React.useState<ViewMode>("list")
  const [query, setQuery] = React.useState("")
  const [favorites, setFavorites] = React.useState<string[]>(["f1"])

  const isAr = locale === "ar"
  const isDark = mounted && resolvedTheme === "dark"

  const filteredFiles = React.useMemo(() => {
    const normalized = query.trim().toLocaleLowerCase()

    if (!normalized) return workspaceFiles

    return workspaceFiles.filter((file) =>
      [file.name, file.owner, file.size]
        .join(" ")
        .toLocaleLowerCase()
        .includes(normalized),
    )
  }, [query])

  const navLabels: Record<string, [string, string]> = {
    home: ["Overview", "نظرة عامة"],
    files: ["My files", "ملفاتي"],
    shared: ["Shared", "المشاركات"],
    archive: ["Archive", "الأرشيف"],
  }

  function showAction(
    type: "success" | "info",
    titleEn: string,
    titleAr: string,
    descriptionEn: string,
    descriptionAr: string,
  ) {
    toast.add({
      type,
      title: isAr ? titleAr : titleEn,
      description: isAr ? descriptionAr : descriptionEn,
    })
  }

  function toggleFavorite(id: string) {
    setFavorites((current) =>
      current.includes(id)
        ? current.filter((item) => item !== id)
        : [...current, id],
    )
  }

  return (
    <main
      lang={isAr ? "ar" : "en"}
      dir={isAr ? "rtl" : "ltr"}
      className={[
        "min-h-screen bg-background text-foreground",
        isAr ? "font-arabic" : "",
      ].join(" ")}
    >
      <div className="grid min-h-screen lg:grid-cols-[248px_minmax(0,1fr)]">
        {/* Sidebar */}
        <aside className="hidden border-e border-sidebar-border bg-sidebar lg:flex lg:flex-col">
          <div className="flex h-16 items-center gap-3 border-b border-sidebar-border px-5">
            <div className="flex size-9 items-center justify-center rounded-lg bg-brand-navy text-brand-cyan shadow-xs dark:bg-brand-blue dark:text-brand-navy">
              <StorviaMark className="size-6" />
            </div>

            <div>
              <p className="font-semibold tracking-tight">STORVIA</p>
              <p className="text-[0.6875rem] text-muted-foreground">
                {isAr ? "التخزين السحابي الخاص" : "Private cloud workspace"}
              </p>
            </div>
          </div>

          <div className="flex-1 space-y-6 p-3">
            <div>
              <p className="px-2 pb-2 text-[0.6875rem] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                {isAr ? "مساحة العمل" : "Workspace"}
              </p>

              <nav className="space-y-1">
                {navItems.map((item) => {
                  const Icon = item.icon
                  const active = activeNav === item.key
                  const [en, ar] = navLabels[item.key]

                  return (
                    <button
                      key={item.key}
                      type="button"
                      onClick={() => setActiveNav(item.key)}
                      className={[
                        "flex h-10 w-full items-center gap-2.5 rounded-md px-3 text-sm font-medium transition-colors",
                        active
                          ? "bg-sidebar-accent text-sidebar-accent-foreground shadow-xs"
                          : "text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
                      ].join(" ")}
                    >
                      <Icon className="size-4 shrink-0" />
                      <span>{isAr ? ar : en}</span>

                      {item.key === "shared" ? (
                        <span className="ms-auto rounded-full bg-primary/10 px-2 py-0.5 text-[0.6875rem] text-primary">
                          12
                        </span>
                      ) : null}
                    </button>
                  )
                })}
              </nav>
            </div>

            <div>
              <p className="px-2 pb-2 text-[0.6875rem] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                {isAr ? "الإدارة" : "Management"}
              </p>

              <nav className="space-y-1">
                <button
                  type="button"
                  className="flex h-10 w-full items-center gap-2.5 rounded-md px-3 text-sm font-medium text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                >
                  <Users className="size-4" />
                  {isAr ? "الأعضاء" : "Members"}
                </button>

                <button
                  type="button"
                  className="flex h-10 w-full items-center gap-2.5 rounded-md px-3 text-sm font-medium text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                >
                  <Settings className="size-4" />
                  {isAr ? "الإعدادات" : "Settings"}
                </button>
              </nav>
            </div>
          </div>

          <div className="m-3 rounded-lg border border-sidebar-border bg-background/70 p-4">
            <div className="flex items-center justify-between">
              <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Cloud className="size-4" />
              </div>
              <Badge variant="success">{isAr ? "سليم" : "Healthy"}</Badge>
            </div>

            <p className="mt-4 text-sm font-semibold">
              {isAr ? "تخزين المؤسسة" : "Organization storage"}
            </p>

            <div className="mt-3 h-2 overflow-hidden rounded-full bg-muted">
              <div className="h-full w-[42%] rounded-full bg-primary" />
            </div>

            <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
              <span>{isAr ? "٤٢٨ جيجابايت" : "428 GB"}</span>
              <span>{isAr ? "من ١ تيرابايت" : "of 1 TB"}</span>
            </div>
          </div>
        </aside>

        {/* Main workspace */}
        <section className="min-w-0">
          <header className="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-border bg-background/90 px-4 backdrop-blur-md sm:px-6 xl:px-8">
            <a
              href="/design-system"
              className="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
              aria-label={isAr ? "العودة لنظام التصميم" : "Back to design system"}
            >
              <ArrowLeft className="size-4 rtl:rotate-180" />
            </a>

            <div className="hidden h-6 w-px bg-border sm:block" />

            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-semibold">
                {isAr ? "STORVIA — مساحة العمل" : "STORVIA — Workspace"}
              </p>
              <p className="hidden truncate text-xs text-muted-foreground sm:block">
                {isAr
                  ? "عرض نهائي لنظام التصميم — بدون بيانات حقيقية"
                  : "Final design-system composition — no live data"}
              </p>
            </div>

            <Badge variant="success" className="hidden sm:inline-flex">
              <CheckCircle2 data-icon="inline-start" />
              {isAr ? "02I جاهز" : "02I Ready"}
            </Badge>

            <Button
              variant="ghost"
              size="icon"
              onClick={() =>
                showAction(
                  "info",
                  "No new alerts",
                  "لا توجد تنبيهات جديدة",
                  "Your workspace is up to date.",
                  "مساحة العمل محدثة بالكامل.",
                )
              }
              aria-label={isAr ? "التنبيهات" : "Notifications"}
            >
              <Bell />
            </Button>

            <Button
              variant="outline"
              size="sm"
              onClick={() => setLocale(isAr ? "en" : "ar")}
            >
              <Languages />
              <span className="hidden sm:inline">
                {isAr ? "English" : "العربية"}
              </span>
            </Button>

            <Button
              variant="outline"
              size="icon-sm"
              disabled={!mounted}
              onClick={() => setTheme(isDark ? "light" : "dark")}
              aria-label={isAr ? "تبديل المظهر" : "Toggle theme"}
            >
              {isDark ? <Sun /> : <Moon />}
            </Button>
          </header>

          <div className="mx-auto max-w-[1600px] p-4 sm:p-6 xl:p-8">
            {/* Mobile navigation */}
            <div className="mb-5 flex gap-2 overflow-x-auto pb-1 lg:hidden">
              {navItems.map((item) => {
                const [en, ar] = navLabels[item.key]
                const active = activeNav === item.key

                return (
                  <Button
                    key={item.key}
                    size="sm"
                    variant={active ? "default" : "outline"}
                    onClick={() => setActiveNav(item.key)}
                  >
                    {isAr ? ar : en}
                  </Button>
                )
              })}
            </div>

            {/* R02 Hero */}
            <div className="relative overflow-hidden rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-7">
              <div className="absolute inset-x-0 top-0 h-px bg-brand-cyan/60" />
              <div className="pointer-events-none absolute -end-24 -top-28 size-80 rounded-full bg-brand-cyan/10 blur-3xl" />
              <div className="pointer-events-none absolute -bottom-36 start-1/4 size-72 rounded-full bg-brand-blue/10 blur-3xl" />

              <div className="relative grid gap-8 xl:grid-cols-[minmax(0,1fr)_430px] xl:items-center">
                <div className="max-w-3xl">
                  <div className="mb-4 flex flex-wrap items-center gap-2">
                    <Badge>
                      <SparklesMark />
                      {isAr ? "سحابتك. تحت سيطرتك." : "Your cloud. Your control."}
                    </Badge>

                    <Badge variant="outline">
                      {isAr ? "ذاتي الاستضافة" : "Self-hosted"}
                    </Badge>

                    <Badge variant="info">R02</Badge>
                  </div>

                  <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl xl:text-5xl">
                    {isAr
                      ? "ملفاتك المهمة، في مساحة عمل واحدة آمنة."
                      : "Important files. One secure workspace."}
                  </h1>

                  <p className="mt-4 max-w-2xl text-sm leading-7 text-muted-foreground sm:text-base">
                    {isAr
                      ? "هوية STORVIA الجديدة تجمع الكحلي العميق، الأزرق السحابي، ولمسات السيان الهادئة مع خطوط بصرية تعبّر عن تدفق الملفات والاتصال الآمن."
                      : "STORVIA now combines deep navy, cloud blue, restrained cyan, and quiet data-flow strokes to express secure storage and connected file movement."}
                  </p>

                  <div className="mt-6 flex flex-wrap gap-2">
                    <Button
                      size="lg"
                      onClick={() =>
                        showAction(
                          "success",
                          "Upload ready",
                          "الرفع جاهز",
                          "The upload workflow is ready for the future file feature.",
                          "تدفق الرفع جاهز لميزة الملفات المستقبلية.",
                        )
                      }
                    >
                      <Upload />
                      {isAr ? "رفع الملفات" : "Upload files"}
                    </Button>

                    <Button
                      size="lg"
                      variant="outline"
                      onClick={() =>
                        showAction(
                          "info",
                          "Folder action",
                          "إجراء المجلد",
                          "This is a design-system interaction preview.",
                          "هذه معاينة تفاعلية لنظام التصميم.",
                        )
                      }
                    >
                      <Plus />
                      {isAr ? "مجلد جديد" : "New folder"}
                    </Button>
                  </div>
                </div>

                <div className="relative mx-auto w-full max-w-[430px]">
                  <StorviaFlowVisual />
                </div>
              </div>
            </div>
            {/* Metrics */}
            <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              {[
                {
                  icon: HardDrive,
                  labelEn: "Storage used",
                  labelAr: "التخزين المستخدم",
                  valueEn: "428 GB",
                  valueAr: "٤٢٨ جيجابايت",
                  metaEn: "42% of organization quota",
                  metaAr: "٤٢٪ من حصة المؤسسة",
                },
                {
                  icon: FileText,
                  labelEn: "Total files",
                  labelAr: "إجمالي الملفات",
                  valueEn: "18,420",
                  valueAr: "١٨٬٤٢٠",
                  metaEn: "+248 this month",
                  metaAr: "+٢٤٨ هذا الشهر",
                },
                {
                  icon: Share2,
                  labelEn: "Active shares",
                  labelAr: "المشاركات النشطة",
                  valueEn: "126",
                  valueAr: "١٢٦",
                  metaEn: "12 external links",
                  metaAr: "١٢ رابطًا خارجيًا",
                },
                {
                  icon: Users,
                  labelEn: "Members",
                  labelAr: "الأعضاء",
                  valueEn: "84",
                  valueAr: "٨٤",
                  metaEn: "78 active",
                  metaAr: "٧٨ نشطًا",
                },
              ].map((item) => {
                const Icon = item.icon

                return (
                  <Card key={item.labelEn} className="p-4 sm:p-5">
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="text-sm text-muted-foreground">
                          {isAr ? item.labelAr : item.labelEn}
                        </p>
                        <p className="mt-2 text-2xl font-semibold tracking-tight">
                          {isAr ? item.valueAr : item.valueEn}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                          {isAr ? item.metaAr : item.metaEn}
                        </p>
                      </div>

                      <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Icon className="size-4" />
                      </div>
                    </div>
                  </Card>
                )
              })}
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
              <div className="min-w-0 space-y-6">
                {/* Quick access */}
                <section>
                  <div className="mb-4 flex items-end justify-between gap-4">
                    <div>
                      <h2 className="text-lg font-semibold">
                        {isAr ? "الوصول السريع" : "Quick access"}
                      </h2>
                      <p className="mt-1 text-sm text-muted-foreground">
                        {isAr
                          ? "المجلدات والمساحات الأكثر استخدامًا."
                          : "Your most-used folders and spaces."}
                      </p>
                    </div>

                    <Button variant="ghost" size="sm">
                      {isAr ? "عرض الكل" : "View all"}
                    </Button>
                  </div>

                  <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                    {quickFolders.map((folder) => {
                      const Icon = folder.icon
                      const toneClass =
                        folder.tone === "info"
                          ? "bg-info/10 text-info"
                          : folder.tone === "success"
                            ? "bg-success/10 text-success"
                            : folder.tone === "warning"
                              ? "bg-warning/15 text-warning-foreground dark:text-warning"
                              : "bg-primary/10 text-primary"

                      return (
                        <Card
                          key={folder.id}
                          variant="interactive"
                          className="cursor-pointer p-4"
                        >
                          <div className="flex items-start justify-between gap-3">
                            <div className={`flex size-10 items-center justify-center rounded-lg ${toneClass}`}>
                              <Icon className="size-5" />
                            </div>

                            <Button
                              variant="ghost"
                              size="icon-sm"
                              aria-label={isAr ? "خيارات" : "Options"}
                            >
                              <MoreHorizontal />
                            </Button>
                          </div>

                          <p className="mt-5 truncate font-semibold">
                            {isAr ? folder.nameAr : folder.nameEn}
                          </p>
                          <p className="mt-1 truncate text-xs text-muted-foreground">
                            {isAr ? folder.metaAr : folder.metaEn}
                          </p>
                        </Card>
                      )
                    })}
                  </div>
                </section>

                {/* Recent files */}
                <section>
                  <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                      <h2 className="text-lg font-semibold">
                        {isAr ? "الملفات الأخيرة" : "Recent files"}
                      </h2>
                      <p className="mt-1 text-sm text-muted-foreground">
                        {isAr
                          ? "أحدث العناصر في مساحة العمل."
                          : "Latest items across the workspace."}
                      </p>
                    </div>

                    <div className="flex flex-col gap-2 sm:flex-row">
                      <div className="relative min-w-0 sm:w-72">
                        <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                          value={query}
                          onChange={(event) => setQuery(event.target.value)}
                          placeholder={isAr ? "ابحث في الملفات..." : "Search files..."}
                          className="ps-9"
                        />
                      </div>

                      <div className="flex rounded-md border border-border bg-card p-0.5 shadow-xs">
                        <Button
                          variant={viewMode === "list" ? "secondary" : "ghost"}
                          size="icon-sm"
                          onClick={() => setViewMode("list")}
                          aria-label={isAr ? "عرض القائمة" : "List view"}
                        >
                          <List />
                        </Button>
                        <Button
                          variant={viewMode === "grid" ? "secondary" : "ghost"}
                          size="icon-sm"
                          onClick={() => setViewMode("grid")}
                          aria-label={isAr ? "عرض الشبكة" : "Grid view"}
                        >
                          <Grid2X2 />
                        </Button>
                      </div>
                    </div>
                  </div>

                  {viewMode === "list" ? (
                    <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                      <div className="hidden grid-cols-[minmax(0,1.8fr)_0.8fr_0.7fr_0.7fr_44px] gap-4 border-b border-border bg-surface-muted/70 px-4 py-3 text-xs font-semibold text-muted-foreground md:grid">
                        <span>{isAr ? "الاسم" : "Name"}</span>
                        <span>{isAr ? "المالك" : "Owner"}</span>
                        <span>{isAr ? "الحالة" : "Status"}</span>
                        <span>{isAr ? "آخر تعديل" : "Modified"}</span>
                        <span />
                      </div>

                      {filteredFiles.length > 0 ? (
                        filteredFiles.map((file) => {
                          const Icon = fileIcon(file.kind)
                          const favorite = favorites.includes(file.id)

                          return (
                            <div
                              key={file.id}
                              className="grid gap-3 border-b border-border px-4 py-3 last:border-b-0 hover:bg-surface-interactive/35 md:grid-cols-[minmax(0,1.8fr)_0.8fr_0.7fr_0.7fr_44px] md:items-center md:gap-4"
                            >
                              <div className="flex min-w-0 items-center gap-3">
                                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-info/10 text-info">
                                  <Icon className="size-4" />
                                </div>

                                <div className="min-w-0">
                                  <BidiText className="block truncate font-medium">
                                    {file.name}
                                  </BidiText>
                                  <p className="mt-0.5 text-xs text-muted-foreground">
                                    {file.size}
                                  </p>
                                </div>
                              </div>

                              <p className="text-sm text-muted-foreground">
                                {file.owner}
                              </p>

                              <div>{statusBadge(file.status, isAr)}</div>

                              <p className="text-sm text-muted-foreground">
                                {isAr ? file.modifiedAr : file.modifiedEn}
                              </p>

                              <DropdownMenu>
                                <DropdownMenuTrigger
                                  render={
                                    <Button
                                      variant="ghost"
                                      size="icon-sm"
                                      aria-label={isAr ? "إجراءات الملف" : "File actions"}
                                    />
                                  }
                                >
                                  <MoreHorizontal />
                                </DropdownMenuTrigger>

                                <DropdownMenuContent>
                                  <DropdownMenuLabel>
                                    <BidiText>{file.name}</BidiText>
                                  </DropdownMenuLabel>
                                  <DropdownMenuSeparator />
                                  <DropdownMenuGroup>
                                    <DropdownMenuItem
                                      onClick={() => toggleFavorite(file.id)}
                                    >
                                      <Star />
                                      {favorite
                                        ? isAr
                                          ? "إزالة من المفضلة"
                                          : "Remove favorite"
                                        : isAr
                                          ? "إضافة للمفضلة"
                                          : "Add favorite"}
                                    </DropdownMenuItem>

                                    <DropdownMenuItem
                                      onClick={() =>
                                        showAction(
                                          "info",
                                          "Share preview",
                                          "معاينة المشاركة",
                                          "Share permissions will be enforced by the backend.",
                                          "سيتم فرض صلاحيات المشاركة من الباك إند.",
                                        )
                                      }
                                    >
                                      <Link2 />
                                      {isAr ? "مشاركة" : "Share"}
                                    </DropdownMenuItem>
                                  </DropdownMenuGroup>
                                </DropdownMenuContent>
                              </DropdownMenu>
                            </div>
                          )
                        })
                      ) : (
                        <div className="px-6 py-12 text-center">
                          <Search className="mx-auto size-6 text-muted-foreground" />
                          <p className="mt-3 font-medium">
                            {isAr ? "لا توجد نتائج" : "No matching files"}
                          </p>
                          <p className="mt-1 text-sm text-muted-foreground">
                            {isAr
                              ? "جرّب تعديل عبارة البحث."
                              : "Try changing your search query."}
                          </p>
                        </div>
                      )}
                    </div>
                  ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                      {filteredFiles.map((file) => {
                        const Icon = fileIcon(file.kind)

                        return (
                          <Card
                            key={file.id}
                            variant="interactive"
                            className="p-4"
                          >
                            <div className="flex items-start justify-between gap-3">
                              <div className="flex size-10 items-center justify-center rounded-lg bg-info/10 text-info">
                                <Icon className="size-5" />
                              </div>
                              {statusBadge(file.status, isAr)}
                            </div>

                            <BidiText className="mt-5 block truncate font-semibold">
                              {file.name}
                            </BidiText>
                            <p className="mt-1 text-xs text-muted-foreground">
                              {file.owner} · {file.size}
                            </p>
                          </Card>
                        )
                      })}
                    </div>
                  )}
                </section>
              </div>

              {/* Right rail */}
              <aside className="space-y-5">
                <Card className="p-5">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="font-semibold">
                        {isAr ? "حالة التخزين" : "Storage overview"}
                      </p>
                      <p className="mt-1 text-xs text-muted-foreground">
                        {isAr ? "مساحة المؤسسة" : "Organization quota"}
                      </p>
                    </div>
                    <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                      <HardDrive className="size-5" />
                    </div>
                  </div>

                  <div className="mt-6 flex items-end gap-2">
                    <span className="text-3xl font-semibold tracking-tight">
                      {isAr ? "٤٢٨" : "428"}
                    </span>
                    <span className="pb-1 text-sm text-muted-foreground">
                      {isAr ? "جيجابايت مستخدمة" : "GB used"}
                    </span>
                  </div>

                  <div className="mt-4 h-2.5 overflow-hidden rounded-full bg-muted">
                    <div className="h-full w-[42%] rounded-full bg-primary" />
                  </div>

                  <div className="mt-3 flex justify-between text-xs text-muted-foreground">
                    <span>42%</span>
                    <span>{isAr ? "١ تيرابايت" : "1 TB"}</span>
                  </div>

                  <div className="mt-5 grid grid-cols-2 gap-3">
                    <div className="rounded-lg bg-surface-muted/70 p-3">
                      <p className="text-xs text-muted-foreground">
                        {isAr ? "الملفات" : "Files"}
                      </p>
                      <p className="mt-1 font-semibold">
                        {isAr ? "١٨٬٤٢٠" : "18,420"}
                      </p>
                    </div>
                    <div className="rounded-lg bg-surface-muted/70 p-3">
                      <p className="text-xs text-muted-foreground">
                        {isAr ? "المتاح" : "Available"}
                      </p>
                      <p className="mt-1 font-semibold">
                        {isAr ? "٥٩٦ GB" : "596 GB"}
                      </p>
                    </div>
                  </div>
                </Card>

                <Card className="p-5">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="font-semibold">
                        {isAr ? "النشاط الأخير" : "Recent activity"}
                      </p>
                      <p className="mt-1 text-xs text-muted-foreground">
                        {isAr ? "آخر أحداث مساحة العمل" : "Latest workspace events"}
                      </p>
                    </div>
                    <Badge variant="secondary">3</Badge>
                  </div>

                  <div className="mt-5 space-y-5">
                    {activity.map((item) => {
                      const Icon = item.icon

                      return (
                        <div key={item.id} className="flex gap-3">
                          <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-surface-muted text-muted-foreground">
                            <Icon className="size-4" />
                          </div>
                          <div className="min-w-0">
                            <p className="text-sm leading-5">
                              {isAr ? item.ar : item.en}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                              {isAr ? item.timeAr : item.timeEn}
                            </p>
                          </div>
                        </div>
                      )
                    })}
                  </div>
                </Card>

                <Card variant="selected" className="p-5">
                  <div className="flex items-center gap-3">
                    <div className="flex size-10 items-center justify-center rounded-lg bg-primary/15 text-primary">
                      <ShieldCheck className="size-5" />
                    </div>
                    <div>
                      <p className="font-semibold">
                        {isAr ? "جاهزية نظام التصميم" : "Design-system readiness"}
                      </p>
                      <p className="mt-1 text-xs opacity-70">
                        {isAr ? "المرحلة 02I" : "STAGE 02I"}
                      </p>
                    </div>
                  </div>

                  <div className="mt-5 space-y-2 text-sm">
                    {[
                      isAr ? "Light / Dark" : "Light / Dark",
                      isAr ? "العربية / الإنجليزية" : "Arabic / English",
                      isAr ? "RTL / LTR" : "RTL / LTR",
                      isAr ? "Responsive" : "Responsive",
                      isAr ? "حالات النظام" : "System states",
                    ].map((item) => (
                      <div key={item} className="flex items-center gap-2">
                        <CheckCircle2 className="size-4 text-success" />
                        <span>{item}</span>
                      </div>
                    ))}
                  </div>
                </Card>
              </aside>
            </div>

            <footer className="mt-8 flex flex-col gap-3 border-t border-border py-6 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
              <span>
                STORVIA · Final Design System Composition · STAGE 02I
              </span>
              <span>
                {isAr
                  ? "معاينة تصميم فقط — لا توجد Features أو بيانات حقيقية"
                  : "Design preview only — no live features or backend data"}
              </span>
            </footer>
          </div>
        </section>
      </div>
    </main>
  )
}

function SparklesMark() {
  return (
    <span
      aria-hidden="true"
      className="inline-block size-1.5 rounded-full bg-primary-foreground"
    />
  )
}
