"use client";

import * as React from "react";

import { Input } from "@/components/ui/input";
import { KeenIcon } from "@/components/ui/keen-icon";
import { cn } from "@/lib/utils";

type InlineNodeNameEditorProps = {
  value: string;
  onChange: (value: string) => void;
  onConfirm: () => void;
  onCancel: () => void;
  error: string | null;
  pending: boolean;
  placeholder: string;
  confirmLabel: string;
  cancelLabel: string;
  selectionEnd?: number;
  compact?: boolean;
};

export function InlineNodeNameEditor({
  value,
  onChange,
  onConfirm,
  onCancel,
  error,
  pending,
  placeholder,
  confirmLabel,
  cancelLabel,
  selectionEnd,
  compact = false,
}: InlineNodeNameEditorProps) {
  const inputRef = React.useRef<HTMLInputElement>(null);
  const errorId = React.useId();

  React.useEffect(() => {
    const input = inputRef.current;

    if (!input) {
      return;
    }

    input.focus({ preventScroll: true });
    input.setSelectionRange(0, selectionEnd ?? input.value.length);
  }, [selectionEnd]);

  const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
    if (event.nativeEvent.isComposing) {
      return;
    }

    if (event.key === "Enter") {
      event.preventDefault();

      if (!pending) {
        onConfirm();
      }

      return;
    }

    if (event.key === "Escape") {
      event.preventDefault();

      if (!pending) {
        onCancel();
      }
    }
  };

  return (
    <div className={cn("min-w-0", compact ? "w-full" : "max-w-[520px]")}>
      <div className="flex min-w-0 items-center gap-1.5">
        <Input
          ref={inputRef}
          value={value}
          onChange={(event) => onChange(event.target.value)}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? errorId : undefined}
          disabled={pending}
          className={cn(
            "h-9 min-w-0 bg-background text-[12.5px] shadow-none",
            error && "border-destructive focus-visible:ring-destructive/20",
          )}
        />

        <button
          type="button"
          title={confirmLabel}
          aria-label={confirmLabel}
          onClick={onConfirm}
          disabled={pending}
          className="flex size-9 shrink-0 items-center justify-center rounded-md bg-brand-blue/10 text-brand-blue transition-colors hover:bg-brand-blue/15 disabled:cursor-wait disabled:opacity-60"
        >
          <KeenIcon
            name={pending ? "loading" : "check"}
            className={cn("text-[16px]", pending && "animate-spin")}
          />
        </button>

        <button
          type="button"
          title={cancelLabel}
          aria-label={cancelLabel}
          onClick={onCancel}
          disabled={pending}
          className="flex size-9 shrink-0 items-center justify-center rounded-md bg-destructive/8 text-destructive transition-colors hover:bg-destructive/12 disabled:cursor-wait disabled:opacity-50"
        >
          <KeenIcon name="cross" className="text-[16px]" />
        </button>
      </div>

      {error ? (
        <p
          id={errorId}
          role="alert"
          className="mt-1.5 text-[10.5px] font-medium text-destructive"
        >
          {error}
        </p>
      ) : null}
    </div>
  );
}
