export const STORVIA_LOCALES = ["en", "ar"] as const;

export type StorviaLocale = (typeof STORVIA_LOCALES)[number];

export type StorviaDirection = "ltr" | "rtl";
