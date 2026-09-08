"use client";

import * as React from "react";

import { formatStorageBytes } from "@/components/file-manager/storage-quota-summary";
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
  fetchAdministrationStorageQuotaFilterOptions,
  listAdministrationStorageQuotas,
  updateAdministrationStorageQuota,
  type AdministrationStorageQuota,
  type AdministrationStorageQuotaDepartmentOption,
} from "@/lib/api/administration-storage-quotas-client";
import { ApiError } from "@/lib/api/api-error";
import type { StorviaLocale } from "@/lib/i18n";
import { cn } from "@/lib/utils";

const MAX_QUOTA_BYTES = Number.MAX_SAFE_INTEGER;
type SpaceFilter = "all" | "personal" | "department";
type DepartmentScope = "self" | "descendants" | "self_and_descendants";
type QuotaLoadError = "forbidden" | "failed";
type QuotaUnit = "KB" | "MB" | "GB" | "TB";

const QUOTA_FACTORS: Record<QuotaUnit, number> = {
  KB: 1024,
  MB: 1024 ** 2,
  GB: 1024 ** 3,
  TB: 1024 ** 4,
};

type ListRequestState = {
  spaceFilter: SpaceFilter;
  departmentId: string;
  departmentScope: DepartmentScope;
  search: string;
  page: number;
  reloadKey: number;
  error: QuotaLoadError | null;
};

type FilterOptionsRequestState = {
  reloadKey: number;
  error: QuotaLoadError | null;
};

const COPY = {
  en: {
    title: "Storage quotas",
    subtitle: "Find the exact Personal, Department, or child Department file space before changing its limit.",
    refresh: "Refresh",
    loading: "Loading storage quotas...",
    loadFailed: "Storage quotas could not be loaded.",
    noPermission: "You do not have permission to manage storage quotas.",
    empty: "No file spaces match these filters.",
    filters: "Filters",
    spaceType: "Space type",
    all: "All",
    personal: "Personal",
    administrative: "Administrative",
    mainDepartment: "Main department",
    chooseDepartment: "Choose a department",
    departmentScope: "Scope",
    scopeSelf: "Department itself",
    scopeDescendants: "Child departments",
    scopeCombined: "Department + all child departments",
    search: "Search",
    searchPlaceholder: "Search user or department name...",
    clearFilters: "Clear filters",
    space: "File space",
    type: "Type",
    mainDepartmentType: "Main department",
    childDepartmentType: "Child department",
    organizationalPath: "Organizational path",
    used: "Used",
    limit: "Limit",
    remaining: "Remaining",
    status: "Status",
    unlimited: "Unlimited",
    available: "Available",
    overLimit: "Over limit",
    edit: "Edit limit",
    editTitle: "Edit storage limit",
    editDescription: "Verify the exact file-space identity and scope before saving the new limit.",
    currentLimit: "Current limit",
    newLimit: "New limit",
    limitBytes: "Limit in bytes",
    amount: "Amount",
    unit: "Unit",
    legacyExactPreserved:
      "This legacy byte value is preserved exactly until you enter a new amount.",
    setUnlimited: "Set unlimited",
    useFiniteLimit: "Use finite limit",
    save: "Save limit",
    saving: "Saving...",
    cancel: "Cancel",
    invalidLimit: `Enter a whole number from 0 to ${MAX_QUOTA_BYTES}.`,
    updated: "Storage quota updated.",
    updateFailed: "The storage quota could not be updated.",
    finiteParentUnlimitedChild:
      "Set a finite quota on every direct child department before limiting this department.",
    unlimitedChildFiniteParent:
      "This department cannot be unlimited while its parent administration has a finite quota.",
    finiteParentLegacySibling:
      "Finish assigning finite quotas to the remaining direct child departments first.",
    quotaBelowAllocations:
      "The new quota is below this department's direct usage and its direct-child allocations.",
    parentCapacityInsufficient:
      "The parent administration does not have enough unallocated storage for this quota.",
    previous: "Previous",
    next: "Next",
    page: "Page {current} of {last}",
    total: "{count} file spaces",
  },
  ar: {
    title: "حصص التخزين",
    subtitle: "حدد مساحة الملفات الشخصية أو الإدارة أو القسم التابع بدقة قبل تعديل الحد.",
    refresh: "تحديث",
    loading: "جاري تحميل حصص التخزين...",
    loadFailed: "تعذر تحميل حصص التخزين.",
    noPermission: "لا تملك صلاحية إدارة حصص التخزين.",
    empty: "لا توجد مساحات ملفات مطابقة لهذه الفلاتر.",
    filters: "الفلاتر",
    spaceType: "نوع المساحة",
    all: "الكل",
    personal: "شخصية",
    administrative: "إدارية",
    mainDepartment: "الإدارة الرئيسية",
    chooseDepartment: "اختر الإدارة",
    departmentScope: "النطاق",
    scopeSelf: "الإدارة نفسها",
    scopeDescendants: "الأقسام التابعة",
    scopeCombined: "الإدارة + جميع الأقسام التابعة",
    search: "البحث",
    searchPlaceholder: "ابحث باسم المستخدم أو الإدارة أو القسم...",
    clearFilters: "مسح الفلاتر",
    space: "مساحة الملفات",
    type: "النوع",
    mainDepartmentType: "إدارة رئيسية",
    childDepartmentType: "قسم تابع",
    organizationalPath: "التبعية التنظيمية",
    used: "المستخدم",
    limit: "الحد",
    remaining: "المتبقي",
    status: "الحالة",
    unlimited: "غير محدود",
    available: "متاح",
    overLimit: "متجاوز للحد",
    edit: "تعديل الحد",
    editTitle: "تعديل حصة التخزين",
    editDescription: "تحقق من هوية المساحة وتبعيتها بدقة قبل حفظ الحد الجديد.",
    currentLimit: "الحصة الحالية",
    newLimit: "الحصة الجديدة",
    limitBytes: "الحد بالبايت",
    amount: "الكمية",
    unit: "الوحدة",
    legacyExactPreserved:
      "سيتم الحفاظ على قيمة البايت القديمة كما هي حتى تُدخل كمية جديدة.",
    setUnlimited: "جعله غير محدود",
    useFiniteLimit: "استخدام حد محدد",
    save: "حفظ الحد",
    saving: "جاري الحفظ...",
    cancel: "إلغاء",
    invalidLimit: `أدخل رقمًا صحيحًا من 0 إلى ${MAX_QUOTA_BYTES}.`,
    updated: "تم تحديث حصة التخزين.",
    updateFailed: "تعذر تحديث حصة التخزين.",
    finiteParentUnlimitedChild:
      "حدد حصة تخزين محددة لكل قسم تابع مباشر أولًا قبل تحديد حصة لهذه الإدارة.",
    unlimitedChildFiniteParent:
      "لا يمكن جعل هذا القسم غير محدود لأن الإدارة الأعلى لها حصة تخزين محددة.",
    finiteParentLegacySibling:
      "أكمل تحديد حصص الأقسام التابعة المباشرة المتبقية أولًا.",
    quotaBelowAllocations:
      "الحصة الجديدة أقل من الاستخدام المباشر وحصص الأقسام التابعة المباشرة.",
    parentCapacityInsufficient:
      "لا توجد سعة غير مخصصة كافية في الإدارة الأعلى لهذه الحصة.",
    previous: "السابق",
    next: "التالي",
    page: "الصفحة {current} من {last}",
    total: "{count} مساحة ملفات",
  },
} as const;

export function StorageQuotaManagement({ locale }: { locale: StorviaLocale }) {
  const copy = COPY[locale];
  const [items, setItems] = React.useState<AdministrationStorageQuota[]>([]);
  const [departments, setDepartments] = React.useState<AdministrationStorageQuotaDepartmentOption[]>([]);
  const [spaceFilter, setSpaceFilter] = React.useState<SpaceFilter>("all");
  const [departmentId, setDepartmentId] = React.useState("");
  const [departmentScope, setDepartmentScope] = React.useState<DepartmentScope>("self");
  const [searchInput, setSearchInput] = React.useState("");
  const [search, setSearch] = React.useState("");
  const [page, setPage] = React.useState(1);
  const [lastPage, setLastPage] = React.useState(1);
  const [total, setTotal] = React.useState(0);
  const [reloadKey, setReloadKey] = React.useState(0);
  const [listRequestState, setListRequestState] = React.useState<ListRequestState | null>(null);
  const [filterOptionsRequestState, setFilterOptionsRequestState] = React.useState<FilterOptionsRequestState | null>(null);
  const [editing, setEditing] = React.useState<AdministrationStorageQuota | null>(null);

  React.useEffect(() => {
    const timer = window.setTimeout(() => {
      setSearch(searchInput.trim());
      setPage(1);
    }, 250);
    return () => window.clearTimeout(timer);
  }, [searchInput]);

  React.useEffect(() => {
    const controller = new AbortController();
    void fetchAdministrationStorageQuotaFilterOptions(controller.signal)
      .then((result) => {
        if (controller.signal.aborted) return;
        setDepartments(result.departments);
        setFilterOptionsRequestState({ reloadKey, error: null });
      })
      .catch((loadError: unknown) => {
        if (controller.signal.aborted) return;
        setFilterOptionsRequestState({
          reloadKey,
          error: loadError instanceof ApiError && loadError.status === 403 ? "forbidden" : "failed",
        });
      });
    return () => controller.abort();
  }, [reloadKey]);

  React.useEffect(() => {
    const controller = new AbortController();

    void listAdministrationStorageQuotas(
      {
        type: spaceFilter === "all" ? undefined : spaceFilter,
        departmentId: spaceFilter === "department" && departmentId ? departmentId : undefined,
        departmentScope: spaceFilter === "department" && departmentId ? departmentScope : undefined,
        search,
      },
      page,
      controller.signal,
    )
      .then((result) => {
        if (controller.signal.aborted) return;
        setItems(result.data);
        setLastPage(result.last_page);
        setTotal(result.total);
        setListRequestState({
          spaceFilter,
          departmentId,
          departmentScope,
          search,
          page,
          reloadKey,
          error: null,
        });
      })
      .catch((loadError: unknown) => {
        if (controller.signal.aborted) return;
        setListRequestState({
          spaceFilter,
          departmentId,
          departmentScope,
          search,
          page,
          reloadKey,
          error: loadError instanceof ApiError && loadError.status === 403 ? "forbidden" : "failed",
        });
      });

    return () => controller.abort();
  }, [departmentId, departmentScope, page, reloadKey, search, spaceFilter]);

  const loading =
    listRequestState === null ||
    listRequestState.spaceFilter !== spaceFilter ||
    listRequestState.departmentId !== departmentId ||
    listRequestState.departmentScope !== departmentScope ||
    listRequestState.search !== search ||
    listRequestState.page !== page ||
    listRequestState.reloadKey !== reloadKey;
  const listError = !loading && listRequestState ? listRequestState.error : null;
  const filterOptionsError =
    filterOptionsRequestState?.reloadKey === reloadKey ? filterOptionsRequestState.error : null;
  const error = listError ?? filterOptionsError;

  const rootDepartments = React.useMemo(
    () => departments.filter((department) => department.parent_id === null),
    [departments],
  );

  const replaceItem = React.useCallback((updated: AdministrationStorageQuota) => {
    setItems((current) => current.map((item) => (item.id === updated.id ? updated : item)));
  }, []);

  const resetFilters = () => {
    setSpaceFilter("all");
    setDepartmentId("");
    setDepartmentScope("self");
    setSearchInput("");
    setSearch("");
    setPage(1);
  };

  return (
    <main className="min-w-0 flex-1 overflow-y-auto bg-workspace-background p-4 sm:p-5 lg:p-6">
      <section className="mx-auto max-w-[1280px] overflow-hidden rounded-xl border border-workspace-content-border bg-card shadow-xs">
        <header className="flex flex-wrap items-start gap-3 border-b border-border px-4 py-4 sm:px-5">
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2">
              <span className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <KeenIcon name="folder" className="text-[18px]" />
              </span>
              <div>
                <h1 className="text-base font-bold tracking-tight">{copy.title}</h1>
                <p className="mt-0.5 text-[11px] leading-5 text-muted-foreground">{copy.subtitle}</p>
              </div>
            </div>
          </div>
          <Button type="button" size="sm" variant="secondary" onClick={() => setReloadKey((v) => v + 1)} disabled={loading}>
            <KeenIcon name="arrows-circle" className={cn("text-[15px]", loading && "animate-spin")} />
            {copy.refresh}
          </Button>
        </header>

        <div className="border-b border-border bg-muted/10 px-4 py-3 sm:px-5">
          <div className="mb-2 text-[10px] font-semibold uppercase tracking-[0.04em] text-muted-foreground">{copy.filters}</div>
          <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <FilterField label={copy.spaceType}>
              <StorviaSelect className={selectClassName} value={spaceFilter} onValueChange={(value) => {
                const next = value as SpaceFilter;
                setSpaceFilter(next);
                setDepartmentId("");
                setDepartmentScope("self");
                setPage(1);
              }} options={[
                { value: "all", label: copy.all },
                { value: "personal", label: copy.personal },
                { value: "department", label: copy.administrative },
              ]} ariaLabel={copy.spaceType} />
            </FilterField>

            {spaceFilter === "department" ? (
              <FilterField label={copy.mainDepartment}>
                <StorviaSelect className={selectClassName} value={departmentId} onValueChange={(value) => { setDepartmentId(value); setPage(1); }} options={rootDepartments.map((department) => ({ value: department.id, label: department.name }))} placeholder={copy.chooseDepartment} searchable searchPlaceholder={copy.searchPlaceholder} ariaLabel={copy.mainDepartment} />
              </FilterField>
            ) : null}

            {spaceFilter === "department" && departmentId ? (
              <FilterField label={copy.departmentScope}>
                <StorviaSelect className={selectClassName} value={departmentScope} onValueChange={(value) => { setDepartmentScope(value as DepartmentScope); setPage(1); }} options={[
                  { value: "self", label: copy.scopeSelf },
                  { value: "descendants", label: copy.scopeDescendants },
                  { value: "self_and_descendants", label: copy.scopeCombined },
                ]} ariaLabel={copy.departmentScope} />
              </FilterField>
            ) : null}

            <FilterField label={copy.search}>
              <Input value={searchInput} onChange={(event) => setSearchInput(event.target.value)} placeholder={copy.searchPlaceholder} />
            </FilterField>
          </div>
          <div className="mt-3">
            <Button type="button" size="xs" variant="ghost" onClick={resetFilters}>{copy.clearFilters}</Button>
          </div>
        </div>

        {loading ? <Status icon="loading" label={copy.loading} spinning /> : error ? (
          <Status icon="information-2" label={error === "forbidden" ? copy.noPermission : copy.loadFailed} />
        ) : items.length === 0 ? <Status icon="folder" label={copy.empty} /> : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[1080px] text-start text-xs">
                <thead className="bg-muted/30 text-[10px] uppercase tracking-[0.04em] text-muted-foreground">
                  <tr><Th>{copy.space}</Th><Th>{copy.type}</Th><Th>{copy.used}</Th><Th>{copy.limit}</Th><Th>{copy.remaining}</Th><Th>{copy.status}</Th><Th><span className="sr-only">{copy.edit}</span></Th></tr>
                </thead>
                <tbody>
                  {items.map((item) => {
                    const subject = item.owner ?? item.department;
                    const path = organizationalPath(item);
                    return (
                      <tr key={item.id} className="border-t border-border/70">
                        <Td>
                          <div className="min-w-0">
                            <div className="font-semibold text-foreground">{subject?.name ?? item.id}</div>
                            {path ? <div className="mt-0.5 max-w-[360px] text-[10px] text-muted-foreground">{path}</div> : null}
                            <div className="mt-0.5 max-w-[260px] truncate text-[10px] text-muted-foreground" dir="ltr">{item.id}</div>
                          </div>
                        </Td>
                        <Td>{visualType(item, copy)}</Td>
                        <Td>{formatStorageBytes(item.quota.used_bytes, locale)}</Td>
                        <Td>{item.quota.limit_bytes === null ? copy.unlimited : formatStorageBytes(item.quota.limit_bytes, locale)}</Td>
                        <Td>{item.quota.remaining_bytes === null ? copy.unlimited : formatStorageBytes(item.quota.remaining_bytes, locale)}</Td>
                        <Td><QuotaStatus item={item} locale={locale} /></Td>
                        <Td><Button type="button" size="xs" variant="secondary" onClick={() => setEditing(item)}><KeenIcon name="pencil" className="text-[13px]" />{copy.edit}</Button></Td>
                      </tr>
                    );
                  })}
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

      {editing ? <QuotaEditDialog key={editing.id} item={editing} locale={locale} onOpenChange={(open) => { if (!open) setEditing(null); }} onUpdated={(updated) => { replaceItem(updated); setEditing(null); }} /> : null}
    </main>
  );
}

function QuotaEditDialog({ item, locale, onOpenChange, onUpdated }: { item: AdministrationStorageQuota; locale: StorviaLocale; onOpenChange: (open: boolean) => void; onUpdated: (item: AdministrationStorageQuota) => void; }) {
  const copy = COPY[locale];
  const initialFinite = exactQuotaInput(item.quota.limit_bytes);
  const [unlimited, setUnlimited] = React.useState(item.quota.limit_bytes === null);
  const [draft, setDraft] = React.useState(initialFinite.amount);
  const [unit, setUnit] = React.useState<QuotaUnit>(initialFinite.unit);
  const [quotaChanged, setQuotaChanged] = React.useState(false);
  const [saving, setSaving] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const subject = item.owner ?? item.department;
  const path = organizationalPath(item);

  const submit = async () => {
    setError(null);
    let limitBytes: number | null = null;
    if (!unlimited) {
      if (!quotaChanged && item.quota.limit_bytes !== null) {
        limitBytes = item.quota.limit_bytes;
      } else {
        if (!/^\d+$/.test(draft)) {
          setError(copy.invalidLimit);
          return;
        }
        const amount = Number(draft);
        const numeric = amount * QUOTA_FACTORS[unit];
        if (
          !Number.isSafeInteger(numeric) ||
          numeric < 0 ||
          numeric > MAX_QUOTA_BYTES
        ) {
          setError(copy.invalidLimit);
          return;
        }
        limitBytes = numeric;
      }
    }
    setSaving(true);
    try {
      const updated = await updateAdministrationStorageQuota(item.id, limitBytes);
      toast.add({ type: "success", title: copy.updated });
      onUpdated(updated);
    } catch (updateError: unknown) {
      setError(quotaUpdateErrorMessage(updateError, copy));
    } finally { setSaving(false); }
  };

  return (
    <Dialog open onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader><DialogTitle>{copy.editTitle}</DialogTitle><DialogDescription>{copy.editDescription}</DialogDescription></DialogHeader>
        <div className="space-y-4">
          <div className="grid gap-2 rounded-lg border border-border bg-muted/20 p-3 text-xs sm:grid-cols-2">
            <Info label={copy.space} value={subject?.name ?? item.id} />
            <Info label={copy.type} value={visualType(item, copy)} />
            {path ? <div className="sm:col-span-2"><Info label={copy.organizationalPath} value={path} /></div> : null}
            <Info label={copy.used} value={formatStorageBytes(item.quota.used_bytes, locale)} />
            <Info label={copy.currentLimit} value={item.quota.limit_bytes === null ? copy.unlimited : formatStorageBytes(item.quota.limit_bytes, locale)} />
            <Info label={copy.remaining} value={item.quota.remaining_bytes === null ? copy.unlimited : formatStorageBytes(item.quota.remaining_bytes, locale)} />
          </div>
          <div className="flex flex-wrap gap-2">
            <Button type="button" size="sm" variant={unlimited ? "default" : "secondary"} onClick={() => { setUnlimited(true); setQuotaChanged(true); }}>{copy.setUnlimited}</Button>
            <Button type="button" size="sm" variant={!unlimited ? "default" : "secondary"} onClick={() => { setUnlimited(false); if (item.quota.limit_bytes === null) setQuotaChanged(true); }}>{copy.useFiniteLimit}</Button>
          </div>
          {!unlimited ? <div className="space-y-2"><div className="grid grid-cols-[minmax(0,1fr)_120px] gap-2"><label className="block space-y-1.5 text-xs font-medium"><span>{copy.amount}</span><Input value={draft} onChange={(event) => { const next = event.target.value.trim(); if (next === "" || /^\d+$/.test(next)) { setDraft(next); setQuotaChanged(true); } }} inputMode="numeric" dir="ltr" autoComplete="off" /></label><div className="space-y-1.5 text-xs font-medium"><span className="block">{copy.unit}</span><StorviaSelect value={unit} onValueChange={(value) => { setUnit(value as QuotaUnit); setQuotaChanged(true); }} options={(["KB", "MB", "GB", "TB"] as QuotaUnit[]).map((value) => ({ value, label: quotaUnitLabel(value, locale) }))} ariaLabel={copy.unit} /></div></div>{!initialFinite.exact && !quotaChanged ? <p className="text-[11px] text-muted-foreground">{copy.legacyExactPreserved}</p> : null}</div> : <Info label={copy.newLimit} value={copy.unlimited} />}
          {error ? <p className="text-xs font-medium text-destructive" role="alert">{error}</p> : null}
        </div>
        <DialogFooter><Button type="button" variant="secondary" onClick={() => onOpenChange(false)} disabled={saving}>{copy.cancel}</Button><Button type="button" onClick={() => void submit()} disabled={saving}>{saving ? copy.saving : copy.save}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function quotaUpdateErrorMessage(
  error: unknown,
  copy: (typeof COPY)[StorviaLocale],
): string {
  if (!(error instanceof ApiError)) {
    return copy.updateFailed;
  }

  if (error.status === 403) {
    return copy.noPermission;
  }

  const limitMessage = error.details?.fields?.limit_bytes?.[0];

  switch (limitMessage) {
    case "A finite department cannot contain an unlimited direct child.":
      return copy.finiteParentUnlimitedChild;
    case "An unlimited department cannot be placed beneath a finite administration.":
      return copy.unlimitedChildFiniteParent;
    case "A finite administration cannot contain an unlimited direct child.":
      return copy.finiteParentLegacySibling;
    case "The quota is below the department direct usage and child allocations.":
      return copy.quotaBelowAllocations;
    case "The parent administration does not have enough unallocated storage.":
      return copy.parentCapacityInsufficient;
    default:
      return typeof limitMessage === "string" && limitMessage.trim() !== ""
        ? limitMessage
        : error.message;
  }
}

function exactQuotaInput(limitBytes: number | null): {
  amount: string;
  unit: QuotaUnit;
  exact: boolean;
} {
  if (limitBytes === null) return { amount: "", unit: "GB", exact: true };
  for (const unit of ["TB", "GB", "MB", "KB"] as QuotaUnit[]) {
    const factor = QUOTA_FACTORS[unit];
    if (limitBytes % factor === 0) {
      return { amount: String(limitBytes / factor), unit, exact: true };
    }
  }
  return { amount: "", unit: "KB", exact: false };
}

function quotaUnitLabel(unit: QuotaUnit, locale: StorviaLocale): string {
  if (locale === "en") return unit;
  return { KB: "ك.ب", MB: "م.ب", GB: "غ.ب", TB: "ت.ب" }[unit];
}

function visualType(item: AdministrationStorageQuota, copy: (typeof COPY)[StorviaLocale]): string {
  if (item.type === "personal") return copy.personal;
  return item.department_path.length > 1 ? copy.childDepartmentType : copy.mainDepartmentType;
}

function organizationalPath(item: AdministrationStorageQuota): string {
  return item.department_path.map((segment) => segment.name).join(" \\ ");
}

function Info({ label, value }: { label: string; value: string }) { return <div><div className="text-[10px] font-medium text-muted-foreground">{label}</div><div className="mt-0.5 font-semibold text-foreground">{value}</div></div>; }
function FilterField({ label, children }: { label: string; children: React.ReactNode }) { return <fieldset className="min-w-0 space-y-1.5 text-xs font-medium"><legend className="text-muted-foreground">{label}</legend>{children}</fieldset>; }
const selectClassName = "h-9 w-full rounded-md border border-input bg-background px-3 text-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50";

function QuotaStatus({ item, locale }: { item: AdministrationStorageQuota; locale: StorviaLocale }) {
  const copy = COPY[locale];
  const label = item.quota.is_over_limit ? copy.overLimit : item.quota.is_unlimited ? copy.unlimited : copy.available;
  return <span className={cn("inline-flex rounded-full border px-2 py-1 text-[10px] font-semibold", item.quota.is_over_limit ? "border-destructive/25 bg-destructive/10 text-destructive" : "border-border bg-muted/30 text-muted-foreground")}>{label}</span>;
}
function Th({ children }: { children: React.ReactNode }) { return <th className="px-4 py-2.5 text-start font-semibold">{children}</th>; }
function Td({ children }: { children: React.ReactNode }) { return <td className="px-4 py-3 align-middle text-muted-foreground">{children}</td>; }
function Status({ icon, label, spinning = false }: { icon: string; label: string; spinning?: boolean }) { return <div className="flex min-h-[320px] items-center justify-center p-8 text-center"><div><KeenIcon name={icon} className={cn("mx-auto text-[24px] text-muted-foreground", spinning && "animate-spin")} /><p className="mt-3 text-sm font-medium text-muted-foreground">{label}</p></div></div>; }
