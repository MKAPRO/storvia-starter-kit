"use client";

import * as React from "react";

import { KeenIcon } from "@/components/ui/keen-icon";
import { toast } from "@/components/ui/toast";
import {
  fetchAdministrationUserFileTypePolicy,
  updateAdministrationUserFileTypePolicy,
  type AdministrationUserFileTypePolicy,
} from "@/lib/api/administration-users-client";
import type { StorviaLocale } from "@/lib/i18n";
import { cn } from "@/lib/utils";

const COPY = {
  en: {
    title: "User file type restrictions",
    description:
      "User-specific restrictions can only narrow the globally and department-allowed upload types.",
    authority: "system.manage",
    loading: "Loading user file type policy…",
    failed: "The user file type policy could not be loaded.",
    retry: "Retry",
    allowed: "Allowed for user",
    blocked: "Blocked for user",
    globalDisabled: "Globally disabled",
    save: "Save file type policy",
    saving: "Saving…",
    saved: "User file type policy updated.",
    saveFailed: "The user file type policy could not be updated.",
    noTypes: "No file types are registered.",
    note:
      "Department restrictions still apply in Company Drive. This editor cannot override an upper-layer deny.",
  },
  ar: {
    title: "قيود أنواع الملفات للمستخدم",
    description:
      "القيود الخاصة بالمستخدم تستطيع تضييق الأنواع المسموحة فقط، ولا تتجاوز التعطيل العام أو منع الإدارة.",
    authority: "system.manage",
    loading: "جاري تحميل سياسة أنواع الملفات للمستخدم…",
    failed: "تعذر تحميل سياسة أنواع الملفات للمستخدم.",
    retry: "إعادة المحاولة",
    allowed: "مسموح للمستخدم",
    blocked: "ممنوع للمستخدم",
    globalDisabled: "معطّل عالميًا",
    save: "حفظ سياسة أنواع الملفات",
    saving: "جاري الحفظ…",
    saved: "تم تحديث سياسة أنواع الملفات للمستخدم.",
    saveFailed: "تعذر تحديث سياسة أنواع الملفات للمستخدم.",
    noTypes: "لا توجد أنواع ملفات مسجلة.",
    note:
      "تبقى قيود الإدارة مطبقة داخل ملفات الشركة، ولا يمكن لهذا الإعداد تجاوز منع صادر من طبقة أعلى.",
  },
} as const;

type RequestState = {
  key: string;
  policy: AdministrationUserFileTypePolicy | null;
  error: boolean;
};

export function UserFileTypePolicyEditor({
  locale,
  userId,
}: {
  locale: StorviaLocale;
  userId: string;
}) {
  const copy = COPY[locale];
  const [reloadKey, setReloadKey] = React.useState(0);
  const [requestState, setRequestState] = React.useState<RequestState | null>(
    null,
  );
  const [disabledIds, setDisabledIds] = React.useState<Set<string>>(
    () => new Set(),
  );
  const [saving, setSaving] = React.useState(false);
  const requestKey = `${userId}\u0000${reloadKey}`;
  const loading = requestState?.key !== requestKey;
  const error = requestState?.key === requestKey && requestState.error;
  const policy =
    requestState?.key === requestKey ? requestState.policy : null;

  React.useEffect(() => {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => {
      void fetchAdministrationUserFileTypePolicy(userId, controller.signal)
        .then((nextPolicy) => {
          if (controller.signal.aborted) {
            return;
          }

          setDisabledIds(new Set(nextPolicy.disabled_file_type_ids));
          setRequestState({
            key: requestKey,
            policy: nextPolicy,
            error: false,
          });
        })
        .catch(() => {
          if (!controller.signal.aborted) {
            setRequestState({ key: requestKey, policy: null, error: true });
          }
        });
    }, 0);

    return () => {
      window.clearTimeout(timeout);
      controller.abort();
    };
  }, [requestKey, userId]);

  const dirty = React.useMemo(() => {
    if (!policy) {
      return false;
    }

    const saved = new Set(policy.disabled_file_type_ids);

    if (saved.size !== disabledIds.size) {
      return true;
    }

    return [...saved].some((id) => !disabledIds.has(id));
  }, [disabledIds, policy]);

  const toggle = (fileTypeId: string) => {
    setDisabledIds((current) => {
      const next = new Set(current);

      if (next.has(fileTypeId)) {
        next.delete(fileTypeId);
      } else {
        next.add(fileTypeId);
      }

      return next;
    });
  };

  const save = async () => {
    if (!policy || !dirty || saving) {
      return;
    }

    setSaving(true);

    try {
      const updated = await updateAdministrationUserFileTypePolicy(
        userId,
        [...disabledIds],
      );
      setDisabledIds(new Set(updated.disabled_file_type_ids));
      setRequestState({ key: requestKey, policy: updated, error: false });
      toast.add({ type: "success", title: copy.saved });
    } catch {
      toast.add({ type: "error", title: copy.saveFailed });
    } finally {
      setSaving(false);
    }
  };

  return (
    <section className="rounded-xl border border-border p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <h3 className="font-semibold">{copy.title}</h3>
          <p className="mt-1 text-xs leading-5 text-muted-foreground">
            {copy.description}
          </p>
        </div>
        <span
          className="rounded-md bg-muted px-2 py-1 font-mono text-[10px] text-muted-foreground"
          dir="ltr"
        >
          {copy.authority}
        </span>
      </div>

      {loading ? (
        <div className="mt-4 flex min-h-24 items-center justify-center gap-2 rounded-lg border border-dashed border-border text-sm text-muted-foreground">
          <KeenIcon name="loading" className="animate-spin text-[16px]" />
          {copy.loading}
        </div>
      ) : error ? (
        <div className="mt-4 rounded-lg border border-destructive/25 bg-destructive/5 p-3 text-sm text-destructive">
          <p>{copy.failed}</p>
          <button
            type="button"
            onClick={() => setReloadKey((current) => current + 1)}
            className="mt-2 rounded-md border border-current/20 px-2.5 py-1.5 text-xs font-semibold"
          >
            {copy.retry}
          </button>
        </div>
      ) : policy ? (
        <>
          <div className="mt-4 max-h-72 space-y-1 overflow-y-auto rounded-lg border border-border p-2">
            {policy.file_types.length > 0 ? (
              policy.file_types.map((fileType) => {
                const globallyDisabled = !fileType.is_enabled;
                const blockedByUser = disabledIds.has(fileType.id);
                const allowed = !globallyDisabled && !blockedByUser;

                return (
                  <label
                    key={fileType.id}
                    className={cn(
                      "flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm",
                      globallyDisabled
                        ? "cursor-default bg-muted/30 opacity-65"
                        : "cursor-pointer hover:bg-accent/50",
                    )}
                  >
                    <input
                      type="checkbox"
                      checked={allowed}
                      disabled={globallyDisabled || saving}
                      onChange={() => toggle(fileType.id)}
                    />
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-blue/8 font-mono text-[10px] font-bold uppercase text-brand-blue">
                      {fileType.extension}
                    </span>
                    <span className="min-w-0 flex-1">
                      <span className="block truncate font-medium">
                        {fileType.label}
                      </span>
                      <span className="mt-0.5 block text-[10px] text-muted-foreground">
                        {globallyDisabled
                          ? copy.globalDisabled
                          : blockedByUser
                            ? copy.blocked
                            : copy.allowed}
                      </span>
                    </span>
                  </label>
                );
              })
            ) : (
              <p className="px-2 py-4 text-center text-sm text-muted-foreground">
                {copy.noTypes}
              </p>
            )}
          </div>

          <p className="mt-3 text-[11px] leading-5 text-muted-foreground">
            {copy.note}
          </p>

          <div className="mt-3 flex justify-end">
            <button
              type="button"
              disabled={!dirty || saving}
              onClick={() => void save()}
              className="inline-flex h-9 items-center gap-2 rounded-lg bg-brand-cyan px-3.5 text-xs font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-45"
            >
              {saving ? (
                <KeenIcon name="loading" className="animate-spin text-[14px]" />
              ) : (
                <KeenIcon name="check" className="text-[14px]" />
              )}
              {saving ? copy.saving : copy.save}
            </button>
          </div>
        </>
      ) : null}
    </section>
  );
}
