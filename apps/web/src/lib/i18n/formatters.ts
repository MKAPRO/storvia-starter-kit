import type { StorviaLocale } from "@/lib/i18n/i18n-types";

const INTL_LOCALES: Record<StorviaLocale, string> = {
  en: "en-US",
  ar: "ar-YE",
};

export function intlLocale(locale: StorviaLocale): string {
  return INTL_LOCALES[locale];
}

export function formatDateTime(
  value: string | Date | null | undefined,
  locale: StorviaLocale,
  fallback = "—",
): string {
  if (!value) return fallback;

  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return fallback;

  return new Intl.DateTimeFormat(intlLocale(locale), {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

export function formatNumber(
  value: number,
  locale: StorviaLocale,
): string {
  return new Intl.NumberFormat(intlLocale(locale)).format(value);
}
