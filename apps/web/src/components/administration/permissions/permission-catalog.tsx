"use client";

import * as React from "react";

import { KeenIcon } from "@/components/ui/keen-icon";
import { ApiError } from "@/lib/api/api-error";
import {
  fetchAdministrationPermissions,
  type AdministrationPermission,
} from "@/lib/api/administration-roles-client";
import { useAuth } from "@/lib/auth";
import type { StorviaLocale } from "@/lib/i18n";

const COPY = {
  en: {
    title: "Permissions",
    subtitle: "Read-only catalog of backend-defined capabilities available to STORVIA roles.",
    search: "Search permission name or description...",
    refresh: "Refresh",
    permissions: "permissions",
    domains: "domains",
    loading: "Loading permission catalog...",
    noPermission: "You do not have permission to view the permission catalog.",
    noResults: "No permissions match your search.",
    unknownError: "The permission catalog could not be loaded.",
    readOnly: "Read-only catalog",
    sourceOfTruth: "Backend source of truth",
    sourceHint: "Permission definitions are managed by the backend access-control contract. Roles only reference this catalog.",
  },
  ar: {
    title: "الصلاحيات",
    subtitle: "كتالوج للقراءة فقط لقدرات STORVIA المعرفة من الخادم والمتاحة للأدوار.",
    search: "بحث باسم الصلاحية أو الوصف...",
    refresh: "تحديث",
    permissions: "صلاحية",
    domains: "نطاقات",
    loading: "جاري تحميل كتالوج الصلاحيات...",
    noPermission: "لا تملك صلاحية عرض كتالوج الصلاحيات.",
    noResults: "لا توجد صلاحيات مطابقة للبحث.",
    unknownError: "تعذر تحميل كتالوج الصلاحيات.",
    readOnly: "كتالوج للقراءة فقط",
    sourceOfTruth: "الخادم هو مصدر الحقيقة",
    sourceHint: "تعريفات الصلاحيات تدار من عقد التحكم بالوصول في الخادم، بينما تقوم الأدوار بالإشارة إلى هذا الكتالوج فقط.",
  },
} as const;

const DOMAIN_LABELS = {
  en: {
    users: "Users",
    roles: "Roles",
    permissions: "Permissions",
    departments: "Departments",
    files: "Files",
    system: "System",
    other: "Other",
  },
  ar: {
    users: "المستخدمون",
    roles: "الأدوار",
    permissions: "الصلاحيات",
    departments: "الإدارات",
    files: "الملفات",
    system: "النظام",
    other: "أخرى",
  },
} as const;

type Domain = keyof (typeof DOMAIN_LABELS)["en"];

function readableError(error: unknown, fallback: string): string {
  if (error instanceof ApiError) return error.message;
  if (error instanceof Error) return error.message;
  return fallback;
}

function permissionDomain(permissionName: string): Domain {
  const prefix = permissionName.split(".")[0];
  if (prefix === "users") return "users";
  if (prefix === "roles") return "roles";
  if (prefix === "permissions") return "permissions";
  if (prefix === "departments") return "departments";
  if (prefix === "files") return "files";
  if (prefix === "system") return "system";
  return "other";
}

function groupPermissions(permissions: AdministrationPermission[]) {
  const order: Domain[] = [
    "users",
    "roles",
    "permissions",
    "departments",
    "files",
    "system",
    "other",
  ];

  return order
    .map((domain) => ({
      domain,
      permissions: permissions.filter(
        (permission) => permissionDomain(permission.name) === domain,
      ),
    }))
    .filter((group) => group.permissions.length > 0);
}

export function PermissionCatalog({ locale }: { locale: StorviaLocale }) {
  const t = COPY[locale];
  const { user: actor } = useAuth();
  const actorIsSuperAdmin = actor?.roles.includes("super_admin") ?? false;
  const canView =
    actorIsSuperAdmin || (actor?.permissions ?? []).includes("permissions.view");

  const [permissions, setPermissions] = React.useState<AdministrationPermission[]>([]);
  const [query, setQuery] = React.useState("");
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState<string | null>(null);

  const loadPermissions = React.useCallback(async () => {
    if (!canView) {
      setPermissions([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);

    try {
      setPermissions(await fetchAdministrationPermissions());
    } catch (requestError) {
      setPermissions([]);
      setError(readableError(requestError, t.unknownError));
    } finally {
      setLoading(false);
    }
  }, [canView, t.unknownError]);

  React.useEffect(() => {
    const timeout = window.setTimeout(() => {
      void loadPermissions();
    }, 0);

    return () => window.clearTimeout(timeout);
  }, [loadPermissions]);

  const filtered = React.useMemo(() => {
    const normalized = query.trim().toLocaleLowerCase();
    if (!normalized) return permissions;

    return permissions.filter((permission) =>
      `${permission.name} ${permission.description ?? ""}`
        .toLocaleLowerCase()
        .includes(normalized),
    );
  }, [permissions, query]);

  const groups = React.useMemo(() => groupPermissions(filtered), [filtered]);
  const allDomains = React.useMemo(() => groupPermissions(permissions).length, [permissions]);

  if (!canView) {
    return (
      <main className="min-w-0 flex-1 overflow-y-auto bg-workspace-background p-4 sm:p-5 lg:p-6">
        <div className="mx-auto flex min-h-[420px] max-w-[980px] items-center justify-center rounded-xl border border-dashed border-workspace-content-border bg-card/55 p-8 text-center">
          <div className="max-w-md">
            <div className="mx-auto flex size-12 items-center justify-center rounded-xl bg-destructive/10 text-destructive">
              <KeenIcon name="shield-tick" className="text-[21px]" />
            </div>
            <p className="mt-4 text-sm font-semibold">{t.noPermission}</p>
          </div>
        </div>
      </main>
    );
  }

  return (
    <main className="min-w-0 flex-1 overflow-y-auto bg-workspace-background p-4 sm:p-5 lg:p-6">
      <div className="mx-auto flex w-full max-w-[1640px] flex-col gap-5">
        <header className="rounded-xl border border-workspace-content-border bg-workspace-panel p-5 shadow-sm">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex min-w-0 items-center gap-3">
              <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-blue/10 text-brand-blue ring-1 ring-brand-blue/15">
                <KeenIcon name="shield-tick" className="text-[22px]" />
              </div>
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <h1 className="text-xl font-bold text-foreground">{t.title}</h1>
                  <span className="rounded-full bg-brand-blue/10 px-2.5 py-1 text-xs font-semibold text-brand-blue">
                    {t.readOnly}
                  </span>
                </div>
                <p className="mt-1 text-sm text-muted-foreground">{t.subtitle}</p>
              </div>
            </div>

            <button
              type="button"
              onClick={() => void loadPermissions()}
              className="inline-flex h-10 items-center gap-2 self-start rounded-lg border border-border bg-background px-3 text-sm font-medium transition-colors hover:bg-accent lg:self-auto"
            >
              <KeenIcon name="arrows-circle" className="text-[16px]" />
              {t.refresh}
            </button>
          </div>

          <div className="mt-5 relative">
            <KeenIcon name="magnifier" className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-[17px] text-muted-foreground" />
            <input
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder={t.search}
              className="h-10 w-full rounded-lg border border-border bg-background ps-9 pe-3 text-sm outline-none transition-shadow focus:ring-2 focus:ring-brand-cyan/25"
            />
          </div>
        </header>

        {error && (
          <div className="rounded-xl border border-destructive/30 bg-destructive/8 px-4 py-3 text-sm text-destructive">
            {error}
          </div>
        )}

        <div className="grid gap-4 sm:grid-cols-3">
          <Metric label={t.permissions} value={String(permissions.length)} icon="shield-tick" />
          <Metric label={t.domains} value={String(allDomains)} icon="element-11" />
          <Metric label={t.sourceOfTruth} value="API" icon="document" />
        </div>

        <div className="rounded-xl border border-brand-blue/20 bg-brand-blue/6 px-4 py-3 text-sm leading-6 text-muted-foreground">
          <span className="font-semibold text-foreground">{t.sourceOfTruth}: </span>
          {t.sourceHint}
        </div>

        {loading ? (
          <div className="flex min-h-[420px] items-center justify-center gap-2 rounded-xl border border-workspace-content-border bg-workspace-panel text-sm text-muted-foreground shadow-sm">
            <KeenIcon name="loading" className="animate-spin text-[17px] text-brand-cyan" />
            {t.loading}
          </div>
        ) : groups.length ? (
          <div className="grid gap-4 xl:grid-cols-2">
            {groups.map((group) => (
              <section
                key={group.domain}
                className="overflow-hidden rounded-xl border border-workspace-content-border bg-workspace-panel shadow-sm"
              >
                <div className="flex items-center justify-between border-b border-border px-4 py-3.5">
                  <div className="flex items-center gap-2">
                    <span className="size-1.5 rounded-full bg-brand-cyan" />
                    <h2 className="text-sm font-bold">{DOMAIN_LABELS[locale][group.domain]}</h2>
                  </div>
                  <span className="rounded-full bg-muted px-2 py-1 text-[11px] font-semibold text-muted-foreground">
                    {group.permissions.length}
                  </span>
                </div>
                <div className="divide-y divide-border/70">
                  {group.permissions.map((permission) => (
                    <article key={permission.name} className="px-4 py-4">
                      <code className="block break-all font-mono text-xs font-semibold text-foreground" dir="ltr">
                        {permission.name}
                      </code>
                      {permission.description && (
                        <p className="mt-1.5 text-xs leading-5 text-muted-foreground">
                          {permission.description}
                        </p>
                      )}
                    </article>
                  ))}
                </div>
              </section>
            ))}
          </div>
        ) : (
          <div className="flex min-h-[360px] items-center justify-center rounded-xl border border-dashed border-workspace-content-border bg-card/55 px-8 text-center">
            <div>
              <div className="mx-auto flex size-11 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                <KeenIcon name="magnifier" className="text-[19px]" />
              </div>
              <p className="mt-3 text-sm font-semibold">{t.noResults}</p>
            </div>
          </div>
        )}
      </div>
    </main>
  );
}

function Metric({
  label,
  value,
  icon,
}: {
  label: string;
  value: string;
  icon: string;
}) {
  return (
    <div className="flex items-center gap-3 rounded-xl border border-workspace-content-border bg-workspace-panel p-4 shadow-sm">
      <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-cyan/10 text-brand-cyan">
        <KeenIcon name={icon} className="text-[18px]" />
      </div>
      <div className="min-w-0">
        <p className="truncate text-xs font-medium text-muted-foreground">{label}</p>
        <p className="mt-0.5 text-lg font-bold text-foreground">{value}</p>
      </div>
    </div>
  );
}
