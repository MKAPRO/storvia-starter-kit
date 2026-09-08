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
import { unlockFileManagerNode } from "@/lib/api/file-manager-client";
import type { StorviaLocale } from "@/lib/i18n";

export type ResourceUnlockTarget = {
  id: string;
  name: string;
  channel: "normal";
  navigateAfterUnlock?: boolean;
};

type ResourceUnlockDialogProps = {
  target: ResourceUnlockTarget | null;
  fileSpaceId?: string;
  locale: StorviaLocale;
  onOpenChange: (open: boolean) => void;
  onUnlocked: (fullyUnlocked: boolean) => void;
};

const TEXT = {
  en: {
    title: "Unlock protected item",
    description:
      "Enter the resource password. Access is scoped to this signed-in browser session and expires automatically.",
    password: "Password",
    unlock: "Unlock",
    unlocking: "Unlocking…",
    cancel: "Cancel",
    invalid: "The password is incorrect.",
    throttled: "Too many attempts. Please wait and try again.",
    failed: "Could not unlock this item.",
    another: "One protection layer was unlocked. Enter the next required password.",
  },
  ar: {
    title: "فتح العنصر المحمي",
    description:
      "أدخل كلمة مرور العنصر. صلاحية الفتح مرتبطة بجلسة المتصفح الحالية وتنتهي تلقائيًا.",
    password: "كلمة المرور",
    unlock: "فتح",
    unlocking: "جاري الفتح…",
    cancel: "إلغاء",
    invalid: "كلمة المرور غير صحيحة.",
    throttled: "محاولات كثيرة. انتظر قليلًا ثم حاول مرة أخرى.",
    failed: "تعذر فتح هذا العنصر.",
    another: "تم فتح طبقة حماية واحدة. أدخل كلمة المرور المطلوبة للطبقة التالية.",
  },
} as const;

export function ResourceUnlockDialog({
  target,
  fileSpaceId,
  locale,
  onOpenChange,
  onUnlocked,
}: ResourceUnlockDialogProps) {
  const t = TEXT[locale];
  const [password, setPassword] = React.useState("");
  const [pending, setPending] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const [notice, setNotice] = React.useState<string | null>(null);

  const resetAndClose = () => {
    setPassword("");
    setPending(false);
    setError(null);
    setNotice(null);
    onOpenChange(false);
  };

  const handleOpenChange = (open: boolean) => {
    if (!open && pending) {
      return;
    }

    if (!open) {
      resetAndClose();
      return;
    }

    onOpenChange(true);
  };

  const submit = async () => {
    if (!target || pending || password.length < 8) {
      return;
    }

    setPending(true);
    setError(null);
    setNotice(null);

    try {
      const result = fileSpaceId
        ? await unlockFileManagerNode(fileSpaceId, target.id, password)
        : null;

      if (!result) {
        setError(t.failed);
        return;
      }

      if (result.password_required) {
        setPassword("");
        setNotice(t.another);
        onUnlocked(false);
        return;
      }

      setPassword("");
      onUnlocked(true);
      resetAndClose();
    } catch (caught) {
      if (isApiError(caught) && caught.code === "RESOURCE_PASSWORD_INVALID") {
        setError(t.invalid);
      } else if (
        isApiError(caught) &&
        caught.code === "TOO_MANY_RESOURCE_PASSWORD_ATTEMPTS"
      ) {
        setError(t.throttled);
      } else {
        setError(t.failed);
      }
    } finally {
      setPending(false);
    }
  };

  return (
    <Dialog open={target !== null} onOpenChange={handleOpenChange}>
      <DialogContent showCloseButton={false}>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <KeenIcon name="lock" variant="outline" className="text-[18px]" />
            {t.title}
          </DialogTitle>
          <DialogDescription>{t.description}</DialogDescription>
        </DialogHeader>

        {target ? (
          <div className="space-y-4">
            <div className="rounded-lg border border-border bg-muted/25 px-3 py-2.5">
              <BidiText className="block truncate text-sm font-semibold">
                {target.name}
              </BidiText>
            </div>

            <label className="grid gap-1.5 text-sm font-medium">
              <span>{t.password}</span>
              <Input
                type="password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                autoComplete="current-password"
                minLength={8}
                maxLength={128}
                autoFocus
                disabled={pending}
                onKeyDown={(event) => {
                  if (event.key === "Enter") {
                    event.preventDefault();
                    void submit();
                  }
                }}
              />
            </label>

            {notice ? (
              <p className="text-xs text-muted-foreground" role="status">
                {notice}
              </p>
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
            disabled={pending}
            onClick={() => handleOpenChange(false)}
          >
            {t.cancel}
          </Button>
          <Button
            type="button"
            disabled={pending || password.length < 8}
            onClick={() => void submit()}
          >
            {pending ? (
              <KeenIcon name="loading" className="animate-spin" />
            ) : (
              <KeenIcon name="lock-2" />
            )}
            {pending ? t.unlocking : t.unlock}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
