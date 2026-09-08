import type { StorviaDirection, StorviaLocale } from "@/lib/i18n/i18n-types";

export const DEFAULT_LOCALE: StorviaLocale = "en";

// Keep the existing storage key so current STORVIA installations preserve
// the user's selected language while the locale state becomes app-wide.
export const LOCALE_STORAGE_KEY = "storvia.workspace.locale";
export const LOCALE_CHANGE_EVENT = "storvia:locale-change";

export function isStorviaLocale(value: unknown): value is StorviaLocale {
  return value === "en" || value === "ar";
}

export function directionForLocale(locale: StorviaLocale): StorviaDirection {
  return locale === "ar" ? "rtl" : "ltr";
}
