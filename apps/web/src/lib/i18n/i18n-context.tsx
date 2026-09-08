"use client";

import * as React from "react";

import { DirectionProvider } from "@/components/ui/direction";
import { useAuth } from "@/lib/auth";
import {
  DEFAULT_LOCALE,
  directionForLocale,
  isStorviaLocale,
  LOCALE_CHANGE_EVENT,
  LOCALE_STORAGE_KEY,
} from "@/lib/i18n/i18n-config";
import type {
  StorviaDirection,
  StorviaLocale,
} from "@/lib/i18n/i18n-types";

type I18nContextValue = {
  locale: StorviaLocale;
  direction: StorviaDirection;
  setLocale: (locale: StorviaLocale) => void;
};

const I18nContext = React.createContext<I18nContextValue | null>(null);

function readStoredLocale(): StorviaLocale | null {
  if (typeof window === "undefined") {
    return null;
  }

  const stored = window.localStorage.getItem(LOCALE_STORAGE_KEY);

  return isStorviaLocale(stored) ? stored : null;
}

function subscribeToLocale(onStoreChange: () => void): () => void {
  window.addEventListener("storage", onStoreChange);
  window.addEventListener(LOCALE_CHANGE_EVENT, onStoreChange);

  return () => {
    window.removeEventListener("storage", onStoreChange);
    window.removeEventListener(LOCALE_CHANGE_EVENT, onStoreChange);
  };
}

export function I18nProvider({ children }: { children: React.ReactNode }) {
  const { user, isAuthenticated, updateUserLocale } = useAuth();

  const getLocaleSnapshot = React.useCallback(
    (): StorviaLocale => readStoredLocale() ?? user?.locale ?? DEFAULT_LOCALE,
    [user?.locale],
  );

  const getLocaleServerSnapshot = React.useCallback(
    (): StorviaLocale => user?.locale ?? DEFAULT_LOCALE,
    [user?.locale],
  );

  const locale = React.useSyncExternalStore(
    subscribeToLocale,
    getLocaleSnapshot,
    getLocaleServerSnapshot,
  );
  const direction = directionForLocale(locale);

  React.useEffect(() => {
    document.documentElement.lang = locale;
    document.documentElement.dir = direction;
  }, [direction, locale]);

  React.useEffect(() => {
    const stored = readStoredLocale();

    if (!isAuthenticated || !user || stored === null || stored === user.locale) {
      return;
    }

    void updateUserLocale(stored).catch(() => {
      // The local preference remains usable if persistence temporarily fails.
      // The next authenticated locale change or session refresh can retry it.
    });
  }, [isAuthenticated, updateUserLocale, user]);

  const setLocale = React.useCallback(
    (nextLocale: StorviaLocale): void => {
      window.localStorage.setItem(LOCALE_STORAGE_KEY, nextLocale);
      window.dispatchEvent(new Event(LOCALE_CHANGE_EVENT));

      if (isAuthenticated && user?.locale !== nextLocale) {
        void updateUserLocale(nextLocale).catch(() => {
          // UI locale changes should remain responsive even if the API is
          // temporarily unavailable. Server persistence is best-effort here.
        });
      }
    },
    [isAuthenticated, updateUserLocale, user?.locale],
  );

  const value = React.useMemo<I18nContextValue>(
    () => ({
      locale,
      direction,
      setLocale,
    }),
    [direction, locale, setLocale],
  );

  return (
    <I18nContext.Provider value={value}>
      <DirectionProvider direction={direction}>{children}</DirectionProvider>
    </I18nContext.Provider>
  );
}

export function PublicI18nProvider({ children }: { children: React.ReactNode }) {
  const getLocaleSnapshot = React.useCallback(
    (): StorviaLocale => readStoredLocale() ?? DEFAULT_LOCALE,
    [],
  );
  const getLocaleServerSnapshot = React.useCallback(
    (): StorviaLocale => DEFAULT_LOCALE,
    [],
  );

  const locale = React.useSyncExternalStore(
    subscribeToLocale,
    getLocaleSnapshot,
    getLocaleServerSnapshot,
  );
  const direction = directionForLocale(locale);

  React.useEffect(() => {
    document.documentElement.lang = locale;
    document.documentElement.dir = direction;
  }, [direction, locale]);

  const setLocale = React.useCallback((nextLocale: StorviaLocale): void => {
    window.localStorage.setItem(LOCALE_STORAGE_KEY, nextLocale);
    window.dispatchEvent(new Event(LOCALE_CHANGE_EVENT));
  }, []);

  const value = React.useMemo<I18nContextValue>(
    () => ({
      locale,
      direction,
      setLocale,
    }),
    [direction, locale, setLocale],
  );

  return (
    <I18nContext.Provider value={value}>
      <DirectionProvider direction={direction}>{children}</DirectionProvider>
    </I18nContext.Provider>
  );
}

export function useI18n(): I18nContextValue {
  const context = React.useContext(I18nContext);

  if (!context) {
    throw new Error("useI18n must be used inside I18nProvider.");
  }

  return context;
}
