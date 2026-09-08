"use client";

import * as React from "react";
import Image from "next/image";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { KeenIcon } from "@/components/ui/keen-icon";
import { StorviaSelect } from "@/components/ui/storvia-select";
import { toast } from "@/components/ui/toast";
import {
  createAdministrationFileType,
  fetchAdministrationDepartmentFileTypePolicy,
  FILE_TYPE_CATEGORIES,
  FILE_TYPE_PREVIEW_MODES,
  listAdministrationFileTypes,
  updateAdministrationDepartmentFileTypePolicy,
  updateAdministrationFileType,
  type AdministrationDepartmentFileTypePolicy,
  type AdministrationFileType,
  type AdministrationFileTypeWritePayload,
  type FileTypeCategory,
  type FileTypePreviewMode,
} from "@/lib/api/administration-file-types-client";
import {
  fetchAdministrationStorageQuotaFilterOptions,
} from "@/lib/api/administration-storage-quotas-client";
import { ApiError } from "@/lib/api/api-error";
import type { StorviaLocale } from "@/lib/i18n";
import { organizationDescendants } from "@/lib/organization/organization-selects";
import { cn } from "@/lib/utils";

type EnabledFilter = "" | "1" | "0";
type LoadError = "forbidden" | "failed" | null;

type DepartmentOption = {
  id: string;
  name: string;
  parent_id: string | null;
  path: string;
};

const COPY = {
  en: {
    title: "File types",
    subtitle:
      "Manage the backend-authoritative file type registry, safe icons, and per-department upload policies.",
    authority: "system.manage",
    add: "Add file type",
    refresh: "Refresh",
    search: "Search",
    searchPlaceholder: "Search extension or label…",
    category: "Category",
    enabledState: "Global state",
    all: "All",
    enabled: "Enabled",
    disabled: "Disabled",
    extension: "Extension",
    label: "Label",
    mimeTypes: "Server MIME allowlist",
    preview: "Preview",
    icon: "Icon",
    actions: "Actions",
    edit: "Edit",
    loading: "Loading file type registry…",
    noPermission: "You do not have permission to manage file types.",
    loadFailed: "The file type registry could not be loaded.",
    empty: "No file types match these filters.",
    previous: "Previous",
    next: "Next",
    page: "Page {current} of {last}",
    total: "{count} file types",
    createTitle: "Add file type",
    editTitle: "Edit file type",
    formDescription:
      "The backend validates extension, MIME allowlist, and SVG before saving.",
    categoryDocument: "Document",
    categorySpreadsheet: "Spreadsheet",
    categoryPresentation: "Presentation",
    categoryText: "Text",
    categoryImage: "Image",
    categoryArchive: "Archive",
    categoryOther: "Other",
    previewNone: "No preview",
    previewImage: "Protected image preview",
    previewPdf: "PDF-capable (icon fallback)",
    mimeHelp: "One concrete MIME type per line. Wildcards are not accepted.",
    svgLabel: "SVG icon",
    svgHelp:
      "Optional. Submitted SVG is untrusted and will only be stored after server-side sanitization.",
    svgPlaceholder: "<svg viewBox=\"0 0 24 24\">…</svg>",
    globalEnabled: "Globally enabled",
    save: "Save",
    saving: "Saving…",
    cancel: "Cancel",
    saved: "File type saved.",
    saveFailed: "The file type could not be saved.",
    invalidForm: "Complete the required fields and provide at least one MIME type.",
    policyTitle: "Department file policies",
    policySubtitle:
      "A missing department deny means the globally enabled type is allowed. Global disable always wins.",
    administration: "Administration",
    chooseAdministration: "Choose an administration",
    department: "Department",
    chooseDepartment: "Choose a department",
    administrationItself: "Administration policy",
    noDepartments: "No departments are available.",
    policyLoading: "Loading department policy…",
    policyFailed: "The department file policy could not be loaded.",
    policyEmpty: "Choose a department to review its file type policy.",
    fileSpace: "File space",
    allowed: "Allowed",
    blocked: "Blocked",
    globalDisabled: "Globally disabled",
    policySave: "Save department policy",
    policySaving: "Saving policy…",
    policySaved: "Department file policy updated.",
    policySaveFailed: "The department file policy could not be updated.",
    policyNote:
      "These controls affect new uploads only. Existing files keep their current access behavior.",
    fallbackIcon: "Fallback icon",
  },
  ar: {
    title: "أنواع الملفات",
    subtitle:
      "إدارة السجل الموثوق من الخادم لأنواع الملفات والأيقونات الآمنة وسياسات الرفع لكل إدارة.",
    authority: "system.manage",
    add: "إضافة نوع ملف",
    refresh: "تحديث",
    search: "البحث",
    searchPlaceholder: "ابحث بالامتداد أو الاسم…",
    category: "الفئة",
    enabledState: "الحالة العامة",
    all: "الكل",
    enabled: "مفعّل",
    disabled: "معطّل",
    extension: "الامتداد",
    label: "الاسم",
    mimeTypes: "قائمة MIME الموثوقة من الخادم",
    preview: "المعاينة",
    icon: "الأيقونة",
    actions: "الإجراءات",
    edit: "تعديل",
    loading: "جاري تحميل سجل أنواع الملفات…",
    noPermission: "لا تملك صلاحية إدارة أنواع الملفات.",
    loadFailed: "تعذر تحميل سجل أنواع الملفات.",
    empty: "لا توجد أنواع ملفات مطابقة لهذه الفلاتر.",
    previous: "السابق",
    next: "التالي",
    page: "الصفحة {current} من {last}",
    total: "{count} نوع ملف",
    createTitle: "إضافة نوع ملف",
    editTitle: "تعديل نوع الملف",
    formDescription:
      "الخادم يتحقق من الامتداد وقائمة MIME وSVG قبل الحفظ.",
    categoryDocument: "مستند",
    categorySpreadsheet: "جدول بيانات",
    categoryPresentation: "عرض تقديمي",
    categoryText: "نصي",
    categoryImage: "صورة",
    categoryArchive: "أرشيف",
    categoryOther: "أخرى",
    previewNone: "بدون معاينة",
    previewImage: "معاينة صورة محمية",
    previewPdf: "يدعم PDF مع أيقونة بديلة",
    mimeHelp: "نوع MIME صريح واحد في كل سطر. لا تُقبل wildcards.",
    svgLabel: "أيقونة SVG",
    svgHelp:
      "اختياري. يتم اعتبار SVG غير موثوق ولا يُخزن إلا بعد تنقيته من الخادم.",
    svgPlaceholder: "<svg viewBox=\"0 0 24 24\">…</svg>",
    globalEnabled: "مفعّل على مستوى النظام",
    save: "حفظ",
    saving: "جاري الحفظ…",
    cancel: "إلغاء",
    saved: "تم حفظ نوع الملف.",
    saveFailed: "تعذر حفظ نوع الملف.",
    invalidForm: "أكمل الحقول المطلوبة وأدخل نوع MIME واحدًا على الأقل.",
    policyTitle: "سياسات أنواع الملفات للإدارات",
    policySubtitle:
      "عدم وجود منع صريح يعني السماح بالنوع المفعّل عالميًا، والتعطيل العام له الأولوية دائمًا.",
    administration: "الإدارة",
    chooseAdministration: "اختر الإدارة",
    department: "القسم",
    chooseDepartment: "اختر القسم",
    administrationItself: "سياسة الإدارة نفسها",
    noDepartments: "لا توجد أقسام متاحة.",
    policyLoading: "جاري تحميل سياسة الإدارة…",
    policyFailed: "تعذر تحميل سياسة أنواع الملفات للإدارة.",
    policyEmpty: "اختر إدارة لمراجعة سياسة أنواع الملفات الخاصة بها.",
    fileSpace: "مساحة الملفات",
    allowed: "مسموح",
    blocked: "ممنوع",
    globalDisabled: "معطّل عالميًا",
    policySave: "حفظ سياسة الإدارة",
    policySaving: "جاري حفظ السياسة…",
    policySaved: "تم تحديث سياسة أنواع الملفات للإدارة.",
    policySaveFailed: "تعذر تحديث سياسة أنواع الملفات للإدارة.",
    policyNote:
      "هذه الضوابط تؤثر على عمليات الرفع الجديدة فقط، ولا تغيّر صلاحيات الملفات الموجودة.",
    fallbackIcon: "أيقونة افتراضية",
  },
} as const;

const CATEGORY_LABEL_KEYS: Record<
  FileTypeCategory,
  keyof (typeof COPY)["en"]
> = {
  document: "categoryDocument",
  spreadsheet: "categorySpreadsheet",
  presentation: "categoryPresentation",
  text: "categoryText",
  image: "categoryImage",
  archive: "categoryArchive",
  other: "categoryOther",
};

const PREVIEW_LABEL_KEYS: Record<
  FileTypePreviewMode,
  keyof (typeof COPY)["en"]
> = {
  none: "previewNone",
  image: "previewImage",
  pdf: "previewPdf",
};

export function FileTypeManagement({ locale }: { locale: StorviaLocale }) {
  const copy = COPY[locale];
  const [items, setItems] = React.useState<AdministrationFileType[]>([]);
  const [searchInput, setSearchInput] = React.useState("");
  const [search, setSearch] = React.useState("");
  const [category, setCategory] = React.useState<FileTypeCategory | "">("");
  const [enabled, setEnabled] = React.useState<EnabledFilter>("");
  const [page, setPage] = React.useState(1);
  const [lastPage, setLastPage] = React.useState(1);
  const [total, setTotal] = React.useState(0);
  const [reloadKey, setReloadKey] = React.useState(0);
  const [registryRequestState, setRegistryRequestState] = React.useState<{
    key: string;
    error: LoadError;
  } | null>(null);
  const [editing, setEditing] = React.useState<AdministrationFileType | "new" | null>(null);
  const [departments, setDepartments] = React.useState<DepartmentOption[]>([]);
  const [administrationId, setAdministrationId] = React.useState("");
  const [departmentId, setDepartmentId] = React.useState("");
  const [departmentPolicy, setDepartmentPolicy] =
    React.useState<AdministrationDepartmentFileTypePolicy | null>(null);
  const [departmentPolicyDraft, setDepartmentPolicyDraft] = React.useState<Set<string>>(
    () => new Set(),
  );
  const [departmentRequestState, setDepartmentRequestState] = React.useState<{
    key: string;
    error: boolean;
  } | null>(null);
  const [departmentSaving, setDepartmentSaving] = React.useState(false);

  const registryRequestKey = `${search}\u0000${category}\u0000${enabled}\u0000${page}\u0000${reloadKey}`;
  const loading = registryRequestState?.key !== registryRequestKey;
  const error =
    registryRequestState?.key === registryRequestKey
      ? registryRequestState.error
      : null;
  const departmentRequestKey = departmentId
    ? `${departmentId}\u0000${reloadKey}`
    : null;
  const departmentLoading =
    departmentRequestKey !== null && departmentRequestState?.key !== departmentRequestKey;
  const departmentError =
    departmentRequestKey !== null &&
    departmentRequestState?.key === departmentRequestKey &&
    departmentRequestState.error;
  const activeDepartmentPolicy =
    departmentRequestKey !== null && departmentRequestState?.key === departmentRequestKey
      ? departmentPolicy
      : null;

  React.useEffect(() => {
    const timer = window.setTimeout(() => {
      setSearch(searchInput.trim());
      setPage(1);
    }, 250);

    return () => window.clearTimeout(timer);
  }, [searchInput]);

  React.useEffect(() => {
    const controller = new AbortController();
    const requestKey = registryRequestKey;

    void listAdministrationFileTypes(
      { search, category, enabled },
      page,
      controller.signal,
    )
      .then((result) => {
        if (controller.signal.aborted) return;
        setItems(result.data);
        setPage(result.current_page);
        setLastPage(result.last_page);
        setTotal(result.total);
        setRegistryRequestState({ key: requestKey, error: null });
      })
      .catch((loadError: unknown) => {
        if (controller.signal.aborted) return;
        setRegistryRequestState({
          key: requestKey,
          error:
            loadError instanceof ApiError && loadError.status === 403
              ? "forbidden"
              : "failed",
        });
      });

    return () => controller.abort();
  }, [category, enabled, page, registryRequestKey, search]);

  React.useEffect(() => {
    const controller = new AbortController();

    void fetchAdministrationStorageQuotaFilterOptions(controller.signal)
      .then((result) => {
        if (!controller.signal.aborted) {
          setDepartments(
            result.departments.map((department) => ({
              id: department.id,
              name: department.name,
              parent_id: department.parent_id,
              path:
                department.path.length > 0
                  ? department.path.map((segment) => segment.name).join(" \\ ")
                  : department.name,
            })),
          );
        }
      })
      .catch(() => {
        if (!controller.signal.aborted) setDepartments([]);
      });

    return () => controller.abort();
  }, [reloadKey]);

  React.useEffect(() => {
    if (!departmentId || !departmentRequestKey) return;

    const controller = new AbortController();
    const requestKey = departmentRequestKey;

    void fetchAdministrationDepartmentFileTypePolicy(
      departmentId,
      controller.signal,
    )
      .then((policy) => {
        if (controller.signal.aborted) return;
        setDepartmentPolicy(policy);
        setDepartmentPolicyDraft(new Set(policy.disabled_file_type_ids));
        setDepartmentRequestState({ key: requestKey, error: false });
      })
      .catch(() => {
        if (controller.signal.aborted) return;
        setDepartmentPolicy(null);
        setDepartmentRequestState({ key: requestKey, error: true });
      });

    return () => controller.abort();
  }, [departmentId, departmentRequestKey]);

  const administrations = React.useMemo(
    () => departments.filter((department) => department.parent_id === null),
    [departments],
  );
  const administrationDepartments = React.useMemo(
    () =>
      administrationId
        ? organizationDescendants(departments, administrationId)
        : [],
    [administrationId, departments],
  );

  const policyDirty = React.useMemo(() => {
    if (!activeDepartmentPolicy) return false;
    const current = new Set(activeDepartmentPolicy.disabled_file_type_ids);
    if (current.size !== departmentPolicyDraft.size) return true;
    return [...current].some((id) => !departmentPolicyDraft.has(id));
  }, [activeDepartmentPolicy, departmentPolicyDraft]);

  const saveDepartmentPolicy = async () => {
    if (
      !departmentId ||
      !departmentRequestKey ||
      !activeDepartmentPolicy ||
      departmentSaving
    )
      return;
    setDepartmentSaving(true);

    try {
      const updated = await updateAdministrationDepartmentFileTypePolicy(
        departmentId,
        [...departmentPolicyDraft],
      );
      setDepartmentPolicy(updated);
      setDepartmentPolicyDraft(new Set(updated.disabled_file_type_ids));
      setDepartmentRequestState({ key: departmentRequestKey, error: false });
      toast.add({ type: "success", title: copy.policySaved });
    } catch {
      toast.add({ type: "error", title: copy.policySaveFailed });
    } finally {
      setDepartmentSaving(false);
    }
  };

  return (
    <main className="min-w-0 flex-1 overflow-y-auto bg-workspace-background p-4 sm:p-5 lg:p-6">
      <div className="mx-auto max-w-[1320px] space-y-5">
        <section className="overflow-hidden rounded-xl border border-workspace-content-border bg-card shadow-xs">
          <header className="flex flex-wrap items-start gap-3 border-b border-border px-4 py-4 sm:px-5">
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2">
                <span className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <KeenIcon name="document" className="text-[18px]" />
                </span>
                <div>
                  <h1 className="text-base font-bold tracking-tight">{copy.title}</h1>
                  <p className="mt-0.5 text-[11px] leading-5 text-muted-foreground">
                    {copy.subtitle}
                  </p>
                </div>
              </div>
            </div>
            <span className="rounded-md bg-muted px-2 py-1 text-[10px] font-medium text-muted-foreground" dir="ltr">
              {copy.authority}
            </span>
            <Button type="button" size="sm" variant="secondary" onClick={() => setReloadKey((v) => v + 1)} disabled={loading}>
              <KeenIcon name="arrows-circle" className={cn("text-[15px]", loading && "animate-spin")} />
              {copy.refresh}
            </Button>
            <Button type="button" size="sm" onClick={() => setEditing("new")}>
              <KeenIcon name="plus" className="text-[15px]" />
              {copy.add}
            </Button>
          </header>

          <div className="grid gap-3 border-b border-border bg-muted/10 px-4 py-3 md:grid-cols-3 sm:px-5">
            <FilterField label={copy.search}>
              <Input value={searchInput} onChange={(event) => setSearchInput(event.target.value)} placeholder={copy.searchPlaceholder} />
            </FilterField>
            <FilterField label={copy.category}>
              <StorviaSelect className={selectClassName} value={category} onValueChange={(value) => { setCategory(value as FileTypeCategory | ""); setPage(1); }} options={[
                { value: "", label: copy.all },
                ...FILE_TYPE_CATEGORIES.map((item) => ({ value: item, label: copy[CATEGORY_LABEL_KEYS[item]] })),
              ]} ariaLabel={copy.category} />
            </FilterField>
            <FilterField label={copy.enabledState}>
              <StorviaSelect className={selectClassName} value={enabled} onValueChange={(value) => { setEnabled(value as EnabledFilter); setPage(1); }} options={[
                { value: "", label: copy.all },
                { value: "1", label: copy.enabled },
                { value: "0", label: copy.disabled },
              ]} ariaLabel={copy.enabledState} />
            </FilterField>
          </div>

          {loading ? (
            <Status icon="loading" label={copy.loading} spinning />
          ) : error ? (
            <Status icon="information-2" label={error === "forbidden" ? copy.noPermission : copy.loadFailed} />
          ) : items.length === 0 ? (
            <Status icon="document" label={copy.empty} />
          ) : (
            <>
              <div className="overflow-x-auto">
                <table className="w-full min-w-[1040px] text-start text-xs">
                  <thead className="bg-muted/30 text-[10px] uppercase tracking-[0.04em] text-muted-foreground">
                    <tr>
                      <Th>{copy.icon}</Th><Th>{copy.extension}</Th><Th>{copy.label}</Th><Th>{copy.category}</Th><Th>{copy.mimeTypes}</Th><Th>{copy.preview}</Th><Th>{copy.enabledState}</Th><Th><span className="sr-only">{copy.actions}</span></Th>
                    </tr>
                  </thead>
                  <tbody>
                    {items.map((item) => (
                      <tr key={item.id} className="border-t border-border/70">
                        <Td><SafeRegistryIcon svg={item.icon_svg} fallbackLabel={item.extension} /></Td>
                        <Td><code className="rounded bg-muted px-1.5 py-0.5 text-[11px]" dir="ltr">.{item.extension}</code></Td>
                        <Td><span className="font-semibold text-foreground">{item.label}</span></Td>
                        <Td>{copy[CATEGORY_LABEL_KEYS[item.category]]}</Td>
                        <Td><div className="max-w-[290px] space-y-0.5 text-[10px] text-muted-foreground" dir="ltr">{item.mime_types.map((mime) => <div key={mime} className="truncate">{mime}</div>)}</div></Td>
                        <Td>{copy[PREVIEW_LABEL_KEYS[item.preview_mode]]}</Td>
                        <Td><StatePill enabled={item.is_enabled} enabledLabel={copy.enabled} disabledLabel={copy.disabled} /></Td>
                        <Td><Button type="button" size="xs" variant="secondary" onClick={() => setEditing(item)}><KeenIcon name="pencil" className="text-[13px]" />{copy.edit}</Button></Td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3 sm:px-5">
                <span className="text-[11px] text-muted-foreground">{copy.total.replace("{count}", String(total))}</span>
                <div className="flex items-center gap-2">
                  <Button type="button" size="xs" variant="secondary" disabled={page <= 1} onClick={() => setPage((v) => Math.max(1, v - 1))}>{copy.previous}</Button>
                  <span className="text-[11px] font-medium text-muted-foreground">{copy.page.replace("{current}", String(page)).replace("{last}", String(lastPage))}</span>
                  <Button type="button" size="xs" variant="secondary" disabled={page >= lastPage} onClick={() => setPage((v) => Math.min(lastPage, v + 1))}>{copy.next}</Button>
                </div>
              </footer>
            </>
          )}
        </section>

        <section className="overflow-hidden rounded-xl border border-workspace-content-border bg-card shadow-xs">
          <header className="border-b border-border px-4 py-4 sm:px-5">
            <div className="flex items-center gap-2">
              <span className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <KeenIcon name="people" className="text-[18px]" />
              </span>
              <div>
                <h2 className="text-base font-bold tracking-tight">{copy.policyTitle}</h2>
                <p className="mt-0.5 text-[11px] leading-5 text-muted-foreground">{copy.policySubtitle}</p>
              </div>
            </div>
          </header>

          <div className="grid gap-3 border-b border-border bg-muted/10 px-4 py-3 sm:grid-cols-2 sm:px-5">
            <FilterField label={copy.administration}>
              <StorviaSelect
                className={selectClassName}
                value={administrationId}
                onValueChange={(value) => {
                  setAdministrationId(value);
                  setDepartmentId("");
                }}
                options={administrations.map((administration) => ({
                  value: administration.id,
                  label: administration.name,
                }))}
                placeholder={copy.chooseAdministration}
                searchable
                searchPlaceholder={copy.searchPlaceholder}
                ariaLabel={copy.administration}
              />
            </FilterField>

            <FilterField label={copy.department}>
              <StorviaSelect
                className={selectClassName}
                value={departmentId}
                onValueChange={setDepartmentId}
                options={
                  administrationId
                    ? [
                        {
                          value: administrationId,
                          label: copy.administrationItself,
                        },
                        ...administrationDepartments.map(({ node, depth }) => ({
                          value: node.id,
                          label: `${"— ".repeat(Math.max(0, depth - 1))}${node.name}`,
                          keywords: node.path,
                        })),
                      ]
                    : []
                }
                placeholder={
                  administrationId ? copy.chooseDepartment : copy.chooseAdministration
                }
                searchable
                searchPlaceholder={copy.searchPlaceholder}
                emptyText={copy.noDepartments}
                ariaLabel={copy.department}
                disabled={!administrationId}
              />
            </FilterField>
          </div>

          {!departmentId ? (
            <Status icon="people" label={copy.policyEmpty} />
          ) : departmentLoading ? (
            <Status icon="loading" label={copy.policyLoading} spinning />
          ) : departmentError || !activeDepartmentPolicy ? (
            <Status icon="information-2" label={copy.policyFailed} />
          ) : (
            <div>
              <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3 sm:px-5">
                <div>
                  <div className="text-sm font-semibold">{activeDepartmentPolicy.department.name}</div>
                  <div className="mt-0.5 text-[10px] text-muted-foreground"><span>{copy.fileSpace}: </span><code dir="ltr">{activeDepartmentPolicy.file_space_id}</code></div>
                </div>
                <Button type="button" size="sm" onClick={saveDepartmentPolicy} disabled={!policyDirty || departmentSaving}>
                  <KeenIcon name={departmentSaving ? "loading" : "check"} className={cn("text-[14px]", departmentSaving && "animate-spin")} />
                  {departmentSaving ? copy.policySaving : copy.policySave}
                </Button>
              </div>
              <div className="grid gap-2 p-4 sm:grid-cols-2 lg:grid-cols-3 sm:p-5">
                {activeDepartmentPolicy.file_types.map((type) => {
                  const blocked = departmentPolicyDraft.has(type.id);
                  const globallyDisabled = !type.is_enabled;
                  return (
                    <label key={type.id} className={cn("flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3", globallyDisabled && "cursor-not-allowed opacity-65")}>
                      <input
                        type="checkbox"
                        className="mt-1 size-4 accent-primary"
                        checked={!blocked && !globallyDisabled}
                        disabled={globallyDisabled}
                        onChange={(event) => {
                          const allowed = event.target.checked;
                          setDepartmentPolicyDraft((current) => {
                            const next = new Set(current);
                            if (allowed) next.delete(type.id); else next.add(type.id);
                            return next;
                          });
                        }}
                      />
                      <SafeRegistryIcon svg={type.icon_svg} fallbackLabel={type.extension} />
                      <span className="min-w-0">
                        <span className="block truncate text-xs font-semibold">{type.label} <span className="text-muted-foreground" dir="ltr">(.{type.extension})</span></span>
                        <span className={cn("mt-1 block text-[10px]", globallyDisabled ? "text-destructive" : blocked ? "text-amber-600 dark:text-amber-400" : "text-emerald-600 dark:text-emerald-400")}>
                          {globallyDisabled ? copy.globalDisabled : blocked ? copy.blocked : copy.allowed}
                        </span>
                      </span>
                    </label>
                  );
                })}
              </div>
              <div className="border-t border-border bg-muted/10 px-4 py-3 text-[10px] leading-5 text-muted-foreground sm:px-5">{copy.policyNote}</div>
            </div>
          )}
        </section>
      </div>

      {editing ? (
        <FileTypeDialog
          key={editing === "new" ? "new" : editing.id}
          locale={locale}
          item={editing === "new" ? null : editing}
          onOpenChange={(open) => { if (!open) setEditing(null); }}
          onSaved={() => {
            setEditing(null);
            setReloadKey((value) => value + 1);
          }}
        />
      ) : null}
    </main>
  );
}

function FileTypeDialog({ locale, item, onOpenChange, onSaved }: { locale: StorviaLocale; item: AdministrationFileType | null; onOpenChange: (open: boolean) => void; onSaved: () => void; }) {
  const copy = COPY[locale];
  const [extension, setExtension] = React.useState(item?.extension ?? "");
  const [label, setLabel] = React.useState(item?.label ?? "");
  const [category, setCategory] = React.useState<FileTypeCategory>(item?.category ?? "document");
  const [mimeText, setMimeText] = React.useState(item?.mime_types.join("\n") ?? "");
  const [enabled, setEnabled] = React.useState(item?.is_enabled ?? true);
  const [previewMode, setPreviewMode] = React.useState<FileTypePreviewMode>(item?.preview_mode ?? "none");
  const [iconSvg, setIconSvg] = React.useState(item?.icon_svg ?? "");
  const [saving, setSaving] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  const submit = async () => {
    const mimes = mimeText.split(/[\n,]/).map((value) => value.trim().toLowerCase()).filter(Boolean);
    if (!extension.trim() || !label.trim() || mimes.length === 0) {
      setError(copy.invalidForm);
      return;
    }

    const payload: AdministrationFileTypeWritePayload = {
      extension: extension.trim().replace(/^\.+/, "").toLowerCase(),
      label: label.trim(),
      category,
      mime_types: [...new Set(mimes)],
      is_enabled: enabled,
      preview_mode: previewMode,
      icon_svg: iconSvg.trim() === "" ? null : iconSvg,
    };

    setSaving(true);
    setError(null);
    try {
      if (item) await updateAdministrationFileType(item.id, payload);
      else await createAdministrationFileType(payload);
      toast.add({ type: "success", title: copy.saved });
      onSaved();
    } catch (saveError: unknown) {
      setError(saveError instanceof ApiError ? saveError.message : copy.saveFailed);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{item ? copy.editTitle : copy.createTitle}</DialogTitle>
          <DialogDescription>{copy.formDescription}</DialogDescription>
        </DialogHeader>
        <div className="grid gap-4 py-2 sm:grid-cols-2">
          <Field label={copy.extension}><Input value={extension} onChange={(event) => setExtension(event.target.value)} dir="ltr" /></Field>
          <Field label={copy.label}><Input value={label} onChange={(event) => setLabel(event.target.value)} /></Field>
          <Field label={copy.category}>
            <StorviaSelect className={selectClassName} value={category} onValueChange={(value) => setCategory(value as FileTypeCategory)} options={FILE_TYPE_CATEGORIES.map((value) => ({ value, label: copy[CATEGORY_LABEL_KEYS[value]] }))} ariaLabel={copy.category} />
          </Field>
          <Field label={copy.preview}>
            <StorviaSelect className={selectClassName} value={previewMode} onValueChange={(value) => setPreviewMode(value as FileTypePreviewMode)} options={FILE_TYPE_PREVIEW_MODES.map((value) => ({ value, label: copy[PREVIEW_LABEL_KEYS[value]] }))} ariaLabel={copy.preview} />
          </Field>
          <div className="sm:col-span-2"><Field label={copy.mimeTypes} help={copy.mimeHelp}><textarea className={textareaClassName} rows={4} value={mimeText} onChange={(event) => setMimeText(event.target.value)} dir="ltr" /></Field></div>
          <div className="sm:col-span-2"><Field label={copy.svgLabel} help={copy.svgHelp}><textarea className={textareaClassName} rows={6} value={iconSvg} onChange={(event) => setIconSvg(event.target.value)} placeholder={copy.svgPlaceholder} dir="ltr" /></Field></div>
          <label className="flex items-center gap-2 text-xs font-medium"><input type="checkbox" className="size-4 accent-primary" checked={enabled} onChange={(event) => setEnabled(event.target.checked)} />{copy.globalEnabled}</label>
          <div className="flex items-center gap-3"><SafeRegistryIcon svg={item?.icon_svg ?? null} fallbackLabel={extension || copy.fallbackIcon} /><span className="text-[10px] text-muted-foreground">{item?.icon_svg ? copy.icon : copy.fallbackIcon}</span></div>
        </div>
        {error ? <p className="rounded-md bg-destructive/10 px-3 py-2 text-xs text-destructive">{error}</p> : null}
        <DialogFooter>
          <Button type="button" variant="secondary" onClick={() => onOpenChange(false)} disabled={saving}>{copy.cancel}</Button>
          <Button type="button" onClick={submit} disabled={saving}><KeenIcon name={saving ? "loading" : "check"} className={cn("text-[14px]", saving && "animate-spin")} />{saving ? copy.saving : copy.save}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function SafeRegistryIcon({ svg, fallbackLabel }: { svg: string | null; fallbackLabel: string }) {
  if (!svg) {
    return <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground" title={fallbackLabel}><KeenIcon name="document" variant="outline" className="text-[18px]" /></span>;
  }

  const source = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
  return <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted p-1.5" title={fallbackLabel}><Image src={source} alt="" width={24} height={24} unoptimized className="size-6 object-contain" /></span>;
}

function StatePill({ enabled, enabledLabel, disabledLabel }: { enabled: boolean; enabledLabel: string; disabledLabel: string }) {
  return <span className={cn("inline-flex rounded-full px-2 py-1 text-[10px] font-semibold", enabled ? "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300" : "bg-muted text-muted-foreground")}>{enabled ? enabledLabel : disabledLabel}</span>;
}

function FilterField({ label, children }: { label: string; children: React.ReactNode }) {
  return <fieldset className="min-w-0"><legend className="mb-1 text-[10px] font-semibold text-muted-foreground">{label}</legend>{children}</fieldset>;
}

function Field({ label, help, children }: { label: string; help?: string; children: React.ReactNode }) {
  return <fieldset className="min-w-0"><legend className="mb-1 text-xs font-medium">{label}</legend>{children}{help ? <span className="mt-1 block text-[10px] leading-4 text-muted-foreground">{help}</span> : null}</fieldset>;
}

function Status({ icon, label, spinning = false }: { icon: string; label: string; spinning?: boolean }) {
  return <div className="flex min-h-40 items-center justify-center px-5 py-8 text-center text-sm text-muted-foreground"><div><KeenIcon name={icon} className={cn("mx-auto mb-2 text-[22px]", spinning && "animate-spin")} /><div>{label}</div></div></div>;
}

function Th({ children }: { children: React.ReactNode }) { return <th className="px-4 py-2.5 text-start font-semibold">{children}</th>; }
function Td({ children }: { children: React.ReactNode }) { return <td className="px-4 py-3 align-top">{children}</td>; }

const selectClassName = "h-9 w-full rounded-md border border-input bg-background px-3 text-xs outline-none focus:ring-2 focus:ring-ring/30";
const textareaClassName = "w-full rounded-md border border-input bg-background px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-ring/30";
