"use client";

import * as React from "react";
import { createPortal } from "react-dom";

import { KeenIcon } from "@/components/ui/keen-icon";
import { cn } from "@/lib/utils";

export type StorviaSelectOption = {
  value: string;
  label: string;
  keywords?: string;
  disabled?: boolean;
};

type Props = {
  value: string;
  onValueChange: (value: string) => void;
  options: StorviaSelectOption[];
  placeholder?: string;
  searchable?: boolean;
  externalSearch?: boolean;
  onSearchChange?: (query: string) => void;
  searchPlaceholder?: string;
  emptyText?: string;
  disabled?: boolean;
  loading?: boolean;
  invalid?: boolean;
  className?: string;
  ariaLabel?: string;
};

export function StorviaSelect({
  value,
  onValueChange,
  options,
  placeholder = "Select",
  searchable = false,
  externalSearch = false,
  onSearchChange,
  searchPlaceholder = "Search...",
  emptyText = "No options",
  disabled = false,
  loading = false,
  invalid = false,
  className,
  ariaLabel,
}: Props) {
  const triggerRef = React.useRef<HTMLButtonElement>(null);
  const panelRef = React.useRef<HTMLDivElement>(null);
  const searchRef = React.useRef<HTMLInputElement>(null);
  const optionRefs = React.useRef<Array<HTMLButtonElement | null>>([]);
  const listboxId = React.useId();
  const [open, setOpen] = React.useState(false);
  const [query, setQuery] = React.useState("");
  const [activeIndex, setActiveIndex] = React.useState(0);
  const [panelDirection, setPanelDirection] = React.useState<"rtl" | "ltr">(
    "ltr",
  );
  const [panelStyle, setPanelStyle] = React.useState<React.CSSProperties>({});

  const selected = options.find((option) => option.value === value);
  const filtered = React.useMemo(() => {
    const needle = query.trim().toLocaleLowerCase();

    if (!searchable || externalSearch || !needle) {
      return options;
    }

    return options.filter((option) =>
      `${option.label} ${option.keywords ?? ""}`
        .toLocaleLowerCase()
        .includes(needle),
    );
  }, [externalSearch, options, query, searchable]);

  const enabledIndices = React.useMemo(
    () =>
      filtered.flatMap((option, index) => (option.disabled ? [] : [index])),
    [filtered],
  );

  const placePanel = React.useCallback(() => {
    const rect = triggerRef.current?.getBoundingClientRect();
    if (!rect) return;
    const availableBelow = window.innerHeight - rect.bottom - 12;
    const availableAbove = rect.top - 12;
    const openAbove = availableBelow < 220 && availableAbove > availableBelow;
    const maxHeight = Math.max(160, Math.min(360, openAbove ? availableAbove : availableBelow));

    setPanelStyle({
      position: "fixed",
      left: rect.left,
      top: openAbove ? undefined : rect.bottom + 6,
      bottom: openAbove ? window.innerHeight - rect.top + 6 : undefined,
      width: rect.width,
      maxHeight,
    });
  }, []);

  React.useEffect(() => {
    if (!open) return;
    placePanel();
    const reposition = () => placePanel();
    window.addEventListener("resize", reposition);
    window.addEventListener("scroll", reposition, true);
    return () => {
      window.removeEventListener("resize", reposition);
      window.removeEventListener("scroll", reposition, true);
    };
  }, [open, placePanel]);

  React.useEffect(() => {
    if (!open) return;
    const closeFromOutside = (event: PointerEvent) => {
      const node = event.target as Node;
      if (triggerRef.current?.contains(node) || panelRef.current?.contains(node)) return;
      setOpen(false);
      setQuery("");
      onSearchChange?.("");
    };
    document.addEventListener("pointerdown", closeFromOutside);
    return () => document.removeEventListener("pointerdown", closeFromOutside);
  }, [onSearchChange, open]);

  function moveActive(direction: 1 | -1) {
    if (!enabledIndices.length) return;
    const currentPosition = enabledIndices.indexOf(activeIndex);
    const nextPosition =
      currentPosition < 0
        ? 0
        : (currentPosition + direction + enabledIndices.length) % enabledIndices.length;
    const nextIndex = enabledIndices[nextPosition];
    setActiveIndex(nextIndex);
    optionRefs.current[nextIndex]?.focus();
  }

  function openSelect() {
    const selectedIndex = options.findIndex(
      (option) => option.value === value && !option.disabled,
    );
    const firstEnabledIndex = options.findIndex((option) => !option.disabled);
    const nextIndex = selectedIndex >= 0
      ? selectedIndex
      : Math.max(0, firstEnabledIndex);
    const direction = triggerRef.current
      ?.closest<HTMLElement>("[dir]")
      ?.getAttribute("dir");

    setQuery("");
    onSearchChange?.("");
    setActiveIndex(nextIndex);
    setPanelDirection(direction === "rtl" ? "rtl" : "ltr");
    setOpen(true);

    window.requestAnimationFrame(() => {
      if (searchable) searchRef.current?.focus();
      else optionRefs.current[nextIndex]?.focus();
    });
  }

  function choose(option: StorviaSelectOption) {
    if (option.disabled) return;
    onValueChange(option.value);
    setOpen(false);
    setQuery("");
    onSearchChange?.("");
    window.requestAnimationFrame(() => triggerRef.current?.focus());
  }

  function handleListKey(event: React.KeyboardEvent) {
    if (event.key === "ArrowDown") {
      event.preventDefault();
      moveActive(1);
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      moveActive(-1);
    } else if (event.key === "Home" && enabledIndices.length) {
      event.preventDefault();
      setActiveIndex(enabledIndices[0]);
      optionRefs.current[enabledIndices[0]]?.focus();
    } else if (event.key === "End" && enabledIndices.length) {
      event.preventDefault();
      const last = enabledIndices.at(-1) ?? 0;
      setActiveIndex(last);
      optionRefs.current[last]?.focus();
    } else if (event.key === "Escape") {
      event.preventDefault();
      setOpen(false);
      setQuery("");
      triggerRef.current?.focus();
    }
  }

  const panel =
    open && typeof document !== "undefined"
      ? createPortal(
          <div
            ref={panelRef}
            style={panelStyle}
            className="z-[140] flex min-w-0 flex-col overflow-hidden rounded-xl border border-border bg-popover text-popover-foreground shadow-xl"
            dir={panelDirection}
          >
            {searchable ? (
              <div className="border-b border-border p-2">
                <div className="relative">
                  <KeenIcon
                    name="magnifier"
                    className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-[15px] text-muted-foreground"
                  />
                  <input
                    ref={searchRef}
                    aria-label={searchPlaceholder}
                    value={query}
                    onChange={(event) => {
                      const nextQuery = event.target.value;
                      setQuery(nextQuery);
                      onSearchChange?.(nextQuery);
                      setActiveIndex(0);
                    }}
                    onKeyDown={(event) => {
                      if (event.key === "ArrowDown" || event.key === "ArrowUp") {
                        event.preventDefault();
                        const index =
                          event.key === "ArrowDown"
                            ? (enabledIndices[0] ?? 0)
                            : (enabledIndices.at(-1) ?? 0);
                        setActiveIndex(index);
                        optionRefs.current[index]?.focus();
                      } else if (event.key === "Escape") {
                        event.preventDefault();
                        setOpen(false);
                        setQuery("");
                        onSearchChange?.("");
                        triggerRef.current?.focus();
                      }
                    }}
                    placeholder={searchPlaceholder}
                    className="h-9 w-full rounded-lg border border-input bg-background ps-9 pe-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                  />
                </div>
              </div>
            ) : null}

            <div
              id={listboxId}
              role="listbox"
              aria-label={ariaLabel}
              onKeyDown={handleListKey}
              className="min-h-0 flex-1 overflow-y-auto p-1.5"
            >
              {loading ? (
                <div className="px-3 py-5 text-center text-sm text-muted-foreground">...</div>
              ) : filtered.length ? (
                filtered.map((option, index) => (
                  <button
                    key={option.value}
                    ref={(node) => {
                      optionRefs.current[index] = node;
                    }}
                    type="button"
                    role="option"
                    aria-selected={option.value === value}
                    disabled={option.disabled}
                    tabIndex={activeIndex === index ? 0 : -1}
                    onFocus={() => setActiveIndex(index)}
                    onClick={() => choose(option)}
                    className={cn(
                      "flex w-full items-center gap-2 rounded-lg px-3 py-2 text-start text-sm outline-none transition-colors",
                      "hover:bg-accent focus-visible:bg-accent disabled:cursor-not-allowed disabled:opacity-45",
                      option.value === value && "bg-accent/70 font-medium",
                    )}
                  >
                    <span className="min-w-0 flex-1 truncate">{option.label}</span>
                    <KeenIcon
                      name="check"
                      className={cn(
                        "shrink-0 text-[15px] text-brand-cyan",
                        option.value !== value && "invisible",
                      )}
                    />
                  </button>
                ))
              ) : (
                <div className="px-3 py-5 text-center text-sm text-muted-foreground">
                  {emptyText}
                </div>
              )}
            </div>
          </div>,
          document.body,
        )
      : null;

  return (
    <>
      <button
        ref={triggerRef}
        type="button"
        role="combobox"
        aria-label={ariaLabel}
        aria-controls={listboxId}
        aria-expanded={open}
        aria-haspopup="listbox"
        aria-invalid={invalid || undefined}
        disabled={disabled}
        onClick={() => {
          if (open) {
            setOpen(false);
            setQuery("");
            onSearchChange?.("");
          } else {
            openSelect();
          }
        }}
        onKeyDown={(event) => {
          if (event.key === "ArrowDown" || event.key === "ArrowUp" || event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            if (!open) openSelect();
          } else if (event.key === "Escape") {
            setOpen(false);
          }
        }}
        className={cn(
          "flex h-10 w-full min-w-0 items-center gap-2 rounded-lg border border-input bg-background px-3 text-sm outline-none transition-colors",
          "focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30",
          "disabled:cursor-not-allowed disabled:opacity-55",
          invalid && "border-destructive",
          className,
        )}
      >
        <span className={cn("min-w-0 flex-1 truncate text-start", !selected && "text-muted-foreground")}>
          {selected?.label ?? placeholder}
        </span>
        <KeenIcon
          name="down"
          className={cn("shrink-0 text-[14px] text-muted-foreground transition-transform", open && "rotate-180")}
        />
      </button>
      {panel}
    </>
  );
}
