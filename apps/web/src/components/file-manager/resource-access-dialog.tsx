"use client";

import * as React from "react";

import { BidiText } from "@/components/ui/bidi-text";
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
import { isApiError } from "@/lib/api/api-error";
import {
  getFileManagerAccessPolicy,
  grantFileManagerViewAccess,
  listFileManagerAccessRecipients,
  removeFileManagerAccessPassword,
  revokeFileManagerViewAccess,
  setFileManagerAccessPassword,
  updateFileManagerAccessVisibility,
  type FileManagerAccessPolicy,
  type FileManagerAccessRecipient,
  type FileManagerAccessVisibility,
  type FileManagerNode,
} from "@/lib/api/file-manager-client";
import type { StorviaLocale } from "@/lib/i18n";
import { cn } from "@/lib/utils";

type ResourceAccessDialogProps = {
  node: FileManagerNode | null;
  fileSpaceId: string;
  locale: StorviaLocale;
  onOpenChange: (open: boolean) => void;
  onChanged: () => void;
};

const TEXT = {
  en: {
    title: "Privacy & access",
    description:
      "Resource privacy is enforced in addition to workspace scope and file capabilities.",
    loading: "Loading privacy settings…",
    loadFailed: "Could not load privacy settings.",
    visibility: "Visibility",
    inherit: "Inherit",
    inheritHint: "Keep the existing visibility behavior from parent policies.",
    private: "Private",
    privateHint: "Only the owner satisfies this resource visibility gate.",
    restricted: "Restricted",
    restrictedHint: "Owner plus explicitly granted users inside the existing workspace scope.",
    password: "Password protection",
    passwordHint:
      "Passwords add an unlock gate and never grant workspace or mutation permissions.",
    newPassword: "New password",
    setPassword: "Set / change password",
    removePassword: "Remove password",
    passwordProtected: "Password protection is enabled.",
    noPassword: "No resource password is set.",
    grants: "View access",
    search: "Search eligible users",
    noResults: "No eligible users found.",
    noGrants: "No direct view grants.",
    grant: "Grant view",
    revoke: "Revoke",
    close: "Close",
    saving: "Saving…",
    failed: "Could not update privacy settings.",
  },
  ar: {
    title: "الخصوصية والوصول",
    description:
      "تُطبق خصوصية العنصر إضافةً إلى نطاق مساحة العمل وصلاحيات الملفات الحالية.",
    loading: "جاري تحميل إعدادات الخصوصية…",
    loadFailed: "تعذر تحميل إعدادات الخصوصية.",
    visibility: "الرؤية",
    inherit: "وراثة",
    inheritHint: "الإبقاء على سلوك الرؤية الحالي الموروث من سياسات المجلدات الأب.",
    private: "خاص",
    privateHint: "مالك العنصر فقط يجتاز بوابة الرؤية الخاصة بهذا العنصر.",
    restricted: "مقيّد",
    restrictedHint: "المالك مع المستخدمين الممنوحين صراحةً داخل نطاق مساحة العمل الحالي.",
    password: "الحماية بكلمة مرور",
    passwordHint:
      "كلمة المرور تضيف بوابة فتح ولا تمنح صلاحيات مساحة العمل أو التعديل.",
    newPassword: "كلمة المرور الجديدة",
    setPassword: "تعيين / تغيير كلمة المرور",
    removePassword: "إزالة كلمة المرور",
    passwordProtected: "الحماية بكلمة مرور مفعلة.",
    noPassword: "لا توجد كلمة مرور لهذا العنصر.",
    grants: "صلاحية العرض",
    search: "ابحث عن مستخدم مؤهل",
    noResults: "لا يوجد مستخدم مؤهل مطابق.",
    noGrants: "لا توجد منح عرض مباشرة.",
    grant: "منح العرض",
    revoke: "إلغاء",
    close: "إغلاق",
    saving: "جاري الحفظ…",
    failed: "تعذر تحديث إعدادات الخصوصية.",
  },
} as const;

const VISIBILITIES: FileManagerAccessVisibility[] = [
  "inherit",
  "private",
  "restricted",
];

export function ResourceAccessDialog({
  node,
  fileSpaceId,
  locale,
  onOpenChange,
  onChanged,
}: ResourceAccessDialogProps) {
  const t = TEXT[locale];
  const nodeId = node?.id ?? null;
  const [policy, setPolicy] = React.useState<FileManagerAccessPolicy | null>(null);
  const [loadedNodeId, setLoadedNodeId] = React.useState<string | null>(null);
  const [query, setQuery] = React.useState("");
  const [recipients, setRecipients] = React.useState<FileManagerAccessRecipient[]>([]);
  const [password, setPassword] = React.useState("");
  const [pendingKey, setPendingKey] = React.useState<string | null>(null);
  const [error, setError] = React.useState<string | null>(null);

  const loading = Boolean(nodeId && loadedNodeId !== nodeId);

  React.useEffect(() => {
    if (!nodeId) {
      return;
    }

    const controller = new AbortController();

    void getFileManagerAccessPolicy(fileSpaceId, nodeId, controller.signal)
      .then((result) => {
        if (!controller.signal.aborted) {
          setPolicy(result);
          setError(null);
        }
      })
      .catch(() => {
        if (!controller.signal.aborted) {
          setError(t.loadFailed);
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) {
          setLoadedNodeId(nodeId);
        }
      });

    return () => controller.abort();
  }, [fileSpaceId, nodeId, t.loadFailed]);

  React.useEffect(() => {
    if (!nodeId || policy?.visibility !== "restricted") {
      return;
    }

    const controller = new AbortController();
    const timer = window.setTimeout(() => {
      void listFileManagerAccessRecipients(
        fileSpaceId,
        nodeId,
        query,
        controller.signal,
      )
        .then((rows) => {
          if (!controller.signal.aborted) {
            setRecipients(rows);
          }
        })
        .catch(() => {
          if (!controller.signal.aborted) {
            setRecipients([]);
          }
        });
    }, 220);

    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [fileSpaceId, nodeId, policy?.visibility, query]);

  const applyPolicy = (next: FileManagerAccessPolicy) => {
    setPolicy(next);
    if (next.visibility !== "restricted") {
      setRecipients([]);
      setQuery("");
    }
    setError(null);
    onChanged();
  };

  const changeVisibility = async (visibility: FileManagerAccessVisibility) => {
    if (!node || pendingKey || policy?.visibility === visibility) {
      return;
    }

    setPendingKey(`visibility:${visibility}`);
    setError(null);

    try {
      applyPolicy(
        await updateFileManagerAccessVisibility(fileSpaceId, node.id, visibility),
      );
    } catch (caught) {
      setError(errorMessage(caught, t.failed));
    } finally {
      setPendingKey(null);
    }
  };

  const savePassword = async () => {
    if (!node || pendingKey || password.length < 8) {
      return;
    }

    setPendingKey("password");
    setError(null);

    try {
      applyPolicy(
        await setFileManagerAccessPassword(fileSpaceId, node.id, password),
      );
      setPassword("");
    } catch (caught) {
      setError(errorMessage(caught, t.failed));
    } finally {
      setPendingKey(null);
    }
  };

  const removePassword = async () => {
    if (!node || pendingKey) {
      return;
    }

    setPendingKey("remove-password");
    setError(null);

    try {
      applyPolicy(await removeFileManagerAccessPassword(fileSpaceId, node.id));
      setPassword("");
    } catch (caught) {
      setError(errorMessage(caught, t.failed));
    } finally {
      setPendingKey(null);
    }
  };

  const grant = async (recipient: FileManagerAccessRecipient) => {
    if (!node || pendingKey) {
      return;
    }

    setPendingKey(`grant:${recipient.id}`);
    setError(null);

    try {
      applyPolicy(
        await grantFileManagerViewAccess(fileSpaceId, node.id, recipient.id),
      );
    } catch (caught) {
      setError(errorMessage(caught, t.failed));
    } finally {
      setPendingKey(null);
    }
  };

  const revoke = async (grantId: string) => {
    if (!node || pendingKey) {
      return;
    }

    setPendingKey(`revoke:${grantId}`);
    setError(null);

    try {
      applyPolicy(
        await revokeFileManagerViewAccess(fileSpaceId, node.id, grantId),
      );
    } catch (caught) {
      setError(errorMessage(caught, t.failed));
    } finally {
      setPendingKey(null);
    }
  };

  const handleOpenChange = (open: boolean) => {
    if (!open && pendingKey) {
      return;
    }

    if (!open) {
      setPolicy(null);
      setLoadedNodeId(null);
      setQuery("");
      setRecipients([]);
      setPassword("");
      setPendingKey(null);
      setError(null);
    }

    onOpenChange(open);
  };

  const grantedIds = new Set(policy?.grants.map((item) => item.recipient.id) ?? []);
  const availableRecipients = recipients.filter(
    (recipient) => !grantedIds.has(recipient.id),
  );

  return (
    <Dialog open={node !== null} onOpenChange={handleOpenChange}>
      <DialogContent className="max-w-2xl" showCloseButton={false}>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <KeenIcon name="shield-tick" variant="outline" className="text-[18px]" />
            {t.title}
          </DialogTitle>
          <DialogDescription>{t.description}</DialogDescription>
        </DialogHeader>

        {node ? (
          <div className="space-y-5">
            <div className="rounded-lg border border-border bg-muted/25 px-3 py-2.5">
              <BidiText className="block truncate text-sm font-semibold">
                {node.name}
              </BidiText>
            </div>

            {loading ? (
              <div className="flex min-h-32 items-center justify-center gap-2 text-sm text-muted-foreground">
                <KeenIcon name="loading" className="animate-spin" />
                {t.loading}
              </div>
            ) : policy ? (
              <>
                <section className="space-y-2">
                  <h3 className="text-sm font-semibold">{t.visibility}</h3>
                  <div className="grid gap-2 sm:grid-cols-3">
                    {VISIBILITIES.map((visibility) => (
                      <button
                        key={visibility}
                        type="button"
                        disabled={Boolean(pendingKey)}
                        aria-pressed={policy.visibility === visibility}
                        onClick={() => void changeVisibility(visibility)}
                        className={cn(
                          "rounded-lg border px-3 py-3 text-start transition-colors",
                          policy.visibility === visibility
                            ? "border-foreground/25 bg-accent"
                            : "border-border hover:bg-accent/60",
                        )}
                      >
                        <span className="block text-sm font-semibold">
                          {t[visibility]}
                        </span>
                        <span className="mt-1 block text-[11px] leading-5 text-muted-foreground">
                          {t[`${visibility}Hint` as const]}
                        </span>
                      </button>
                    ))}
                  </div>
                </section>

                <section className="space-y-3 border-t border-border pt-4">
                  <div>
                    <h3 className="text-sm font-semibold">{t.password}</h3>
                    <p className="mt-1 text-xs leading-5 text-muted-foreground">
                      {t.passwordHint}
                    </p>
                  </div>
                  <p className="text-xs text-muted-foreground">
                    {policy.password_protected ? t.passwordProtected : t.noPassword}
                  </p>
                  <div className="flex flex-col gap-2 sm:flex-row">
                    <Input
                      type="password"
                      value={password}
                      onChange={(event) => setPassword(event.target.value)}
                      minLength={8}
                      maxLength={128}
                      autoComplete="new-password"
                      aria-label={t.newPassword}
                      placeholder={t.newPassword}
                      disabled={Boolean(pendingKey)}
                    />
                    <Button
                      type="button"
                      disabled={Boolean(pendingKey) || password.length < 8}
                      onClick={() => void savePassword()}
                    >
                      {pendingKey === "password" ? t.saving : t.setPassword}
                    </Button>
                    {policy.password_protected ? (
                      <Button
                        type="button"
                        variant="secondary"
                        disabled={Boolean(pendingKey)}
                        onClick={() => void removePassword()}
                      >
                        {t.removePassword}
                      </Button>
                    ) : null}
                  </div>
                </section>

                {policy.visibility === "restricted" ? (
                  <section className="space-y-3 border-t border-border pt-4">
                    <h3 className="text-sm font-semibold">{t.grants}</h3>
                    <Input
                      value={query}
                      onChange={(event) => setQuery(event.target.value)}
                      aria-label={t.search}
                      placeholder={t.search}
                      disabled={Boolean(pendingKey)}
                    />

                    <div className="space-y-2">
                      {availableRecipients.length === 0 ? (
                        <p className="text-xs text-muted-foreground">{t.noResults}</p>
                      ) : (
                        availableRecipients.slice(0, 8).map((recipient) => (
                          <div
                            key={recipient.id}
                            className="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2"
                          >
                            <div className="min-w-0">
                              <BidiText className="block truncate text-xs font-semibold">
                                {recipient.name}
                              </BidiText>
                              <BidiText className="block truncate text-[10px] text-muted-foreground">
                                {recipient.username ?? recipient.email ?? recipient.id}
                              </BidiText>
                            </div>
                            <Button
                              type="button"
                              size="xs"
                              variant="secondary"
                              disabled={Boolean(pendingKey)}
                              onClick={() => void grant(recipient)}
                            >
                              {t.grant}
                            </Button>
                          </div>
                        ))
                      )}
                    </div>

                    <div className="space-y-2">
                      {policy.grants.length === 0 ? (
                        <p className="text-xs text-muted-foreground">{t.noGrants}</p>
                      ) : (
                        policy.grants.map((item) => (
                          <div
                            key={item.id}
                            className="flex items-center justify-between gap-3 rounded-lg bg-muted/35 px-3 py-2"
                          >
                            <div className="min-w-0">
                              <BidiText className="block truncate text-xs font-semibold">
                                {item.recipient.name}
                              </BidiText>
                              <BidiText className="block truncate text-[10px] text-muted-foreground">
                                {item.recipient.username ?? item.recipient.email ?? item.recipient.id}
                              </BidiText>
                            </div>
                            <Button
                              type="button"
                              size="xs"
                              variant="ghost"
                              disabled={Boolean(pendingKey)}
                              onClick={() => void revoke(item.id)}
                            >
                              {t.revoke}
                            </Button>
                          </div>
                        ))
                      )}
                    </div>
                  </section>
                ) : null}
              </>
            ) : null}

            {error ? (
              <p className="text-xs text-destructive" role="alert">
                {error}
              </p>
            ) : null}
          </div>
        ) : null}

        <DialogFooter>
          <Button
            type="button"
            variant="secondary"
            disabled={Boolean(pendingKey)}
            onClick={() => handleOpenChange(false)}
          >
            {t.close}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function errorMessage(error: unknown, fallback: string): string {
  if (!isApiError(error)) {
    return fallback;
  }

  if (error.code === "NETWORK_ERROR") {
    return fallback;
  }

  const fields = error.details?.fields;
  const firstFieldMessage = fields
    ? Object.values(fields).flat().find((message) => typeof message === "string")
    : null;

  return firstFieldMessage ?? error.message ?? fallback;
}
