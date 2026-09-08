"use client";

import * as React from "react";

import { ApiError, isApiError } from "@/lib/api/api-error";
import {
  fetchCurrentUser,
  fetchSessionFreshness,
  loginUser,
  logoutUser,
  updateCurrentUserLocale,
} from "@/lib/auth/auth-client";
import type {
  AuthStatus,
  AuthUser,
  LoginCredentials,
  StorviaLocale,
} from "@/lib/auth/auth-types";

type AuthContextValue = {
  status: AuthStatus;
  user: AuthUser | null;
  error: ApiError | null;
  isAuthenticated: boolean;
  refreshSession: () => Promise<AuthUser | null>;
  login: (credentials: LoginCredentials) => Promise<AuthUser>;
  logout: () => Promise<void>;
  updateUserLocale: (locale: StorviaLocale) => Promise<AuthUser>;
  hasRole: (role: string) => boolean;
  hasPermission: (permission: string) => boolean;
};

const AuthContext = React.createContext<AuthContextValue | null>(null);

const SESSION_FRESHNESS_INTERVAL_MS = 30_000;
const SESSION_FRESHNESS_MIN_GAP_MS = 10_000;

function authFailureStatus(error: unknown): AuthStatus {
  if (isApiError(error) && error.code === "USER_DISABLED") {
    return "disabled";
  }

  return "unauthenticated";
}

function isSessionInvalidationFailure(error: unknown): boolean {
  return (
    isApiError(error) &&
    (error.status === 401 ||
      error.code === "AUTH_REQUIRED" ||
      error.code === "SESSION_EXPIRED" ||
      error.code === "USER_DISABLED")
  );
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [status, setStatus] = React.useState<AuthStatus>("loading");
  const [user, setUser] = React.useState<AuthUser | null>(null);
  const [error, setError] = React.useState<ApiError | null>(null);
  const initialSessionRequestRef = React.useRef<Promise<AuthUser> | null>(null);
  const freshnessRevisionRef = React.useRef<string | null>(null);
  const lastFreshnessCheckAtRef = React.useRef(0);

  const refreshSession = React.useCallback(async (): Promise<AuthUser | null> => {
    setError(null);

    try {
      const currentUser = await fetchCurrentUser();
      freshnessRevisionRef.current = currentUser.session_freshness_revision;
      lastFreshnessCheckAtRef.current = Date.now();
      setUser(currentUser);
      setStatus("authenticated");
      return currentUser;
    } catch (caught) {
      const apiError = isApiError(caught) ? caught : null;
      setError(apiError);

      if (isSessionInvalidationFailure(caught)) {
        setUser(null);
        setStatus(authFailureStatus(caught));
      }

      return null;
    }
  }, []);

  React.useEffect(() => {
    let active = true;
    const request = initialSessionRequestRef.current ?? fetchCurrentUser();
    initialSessionRequestRef.current = request;

    void request
      .then((currentUser) => {
        if (!active) {
          return;
        }

        freshnessRevisionRef.current = currentUser.session_freshness_revision;
        lastFreshnessCheckAtRef.current = Date.now();
        setUser(currentUser);
        setError(null);
        setStatus("authenticated");
      })
      .catch((caught: unknown) => {
        if (!active) {
          return;
        }

        const apiError = isApiError(caught) ? caught : null;
        setUser(null);
        setError(apiError);
        setStatus(authFailureStatus(caught));
      })
      .finally(() => {
        if (initialSessionRequestRef.current === request) {
          initialSessionRequestRef.current = null;
        }
      });

    return () => {
      active = false;
    };
  }, []);

  React.useEffect(() => {
    freshnessRevisionRef.current = user?.session_freshness_revision ?? null;
  }, [user]);

  React.useEffect(() => {
    if (status !== "authenticated") {
      return;
    }

    let controller: AbortController | null = null;
    let refreshQueued = false;
    let disposed = false;

    const refreshFreshSession = () => {
      if (document.visibilityState !== "visible" || disposed) {
        return;
      }

      const now = Date.now();

      if (now - lastFreshnessCheckAtRef.current < SESSION_FRESHNESS_MIN_GAP_MS) {
        return;
      }

      if (controller) {
        refreshQueued = true;
        return;
      }

      lastFreshnessCheckAtRef.current = now;

      const requestController = new AbortController();
      controller = requestController;

      void fetchSessionFreshness(requestController.signal)
        .then(async ({ revision }) => {
          if (requestController.signal.aborted) {
            return;
          }

          if (revision === freshnessRevisionRef.current) {
            setError(null);
            return;
          }

          const currentUser = await fetchCurrentUser(requestController.signal);

          if (requestController.signal.aborted) {
            return;
          }

          freshnessRevisionRef.current = currentUser.session_freshness_revision;
          setUser(currentUser);
          setError(null);
        })
        .catch((caught: unknown) => {
          if (requestController.signal.aborted) {
            return;
          }

          if (isSessionInvalidationFailure(caught)) {
            freshnessRevisionRef.current = null;
            setUser(null);
            setError(isApiError(caught) ? caught : null);
            setStatus(authFailureStatus(caught));
          }
        })
        .finally(() => {
          if (controller === requestController) {
            controller = null;
          }

          if (!disposed && refreshQueued) {
            refreshQueued = false;
            queueMicrotask(refreshFreshSession);
          }
        });
    };

    const onVisibilityChange = () => {
      if (document.visibilityState === "visible") {
        refreshFreshSession();
      }
    };

    const intervalId = window.setInterval(
      refreshFreshSession,
      SESSION_FRESHNESS_INTERVAL_MS,
    );

    window.addEventListener("focus", refreshFreshSession);
    document.addEventListener("visibilitychange", onVisibilityChange);

    return () => {
      disposed = true;
      refreshQueued = false;
      window.clearInterval(intervalId);
      window.removeEventListener("focus", refreshFreshSession);
      document.removeEventListener("visibilitychange", onVisibilityChange);
      controller?.abort();
    };
  }, [status]);

  const login = React.useCallback(
    async (credentials: LoginCredentials): Promise<AuthUser> => {
      setStatus("loading");
      setError(null);

      try {
        const currentUser = await loginUser(credentials);
        freshnessRevisionRef.current = currentUser.session_freshness_revision;
        lastFreshnessCheckAtRef.current = Date.now();
        setUser(currentUser);
        setStatus("authenticated");
        return currentUser;
      } catch (caught) {
        const apiError = isApiError(caught) ? caught : null;
        setUser(null);
        setError(apiError);
        setStatus(authFailureStatus(caught));
        throw caught;
      }
    },
    [],
  );

  const logout = React.useCallback(async (): Promise<void> => {
    try {
      await logoutUser();
    } finally {
      freshnessRevisionRef.current = null;
      lastFreshnessCheckAtRef.current = 0;
      setUser(null);
      setError(null);
      setStatus("unauthenticated");
    }
  }, []);

  const updateUserLocale = React.useCallback(
    async (locale: StorviaLocale): Promise<AuthUser> => {
      const currentUser = await updateCurrentUserLocale(locale);
      freshnessRevisionRef.current = currentUser.session_freshness_revision;
      lastFreshnessCheckAtRef.current = Date.now();
      setUser(currentUser);
      return currentUser;
    },
    [],
  );

  const hasRole = React.useCallback(
    (role: string): boolean => Boolean(user?.roles.includes(role)),
    [user],
  );

  const hasPermission = React.useCallback(
    (permission: string): boolean =>
      Boolean(user?.permissions.includes(permission)),
    [user],
  );

  const value = React.useMemo<AuthContextValue>(
    () => ({
      status,
      user,
      error,
      isAuthenticated: status === "authenticated" && user !== null,
      refreshSession,
      login,
      logout,
      updateUserLocale,
      hasRole,
      hasPermission,
    }),
    [
      status,
      user,
      error,
      refreshSession,
      login,
      logout,
      updateUserLocale,
      hasRole,
      hasPermission,
    ],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = React.useContext(AuthContext);

  if (!context) {
    throw new Error("useAuth must be used inside AuthProvider.");
  }

  return context;
}
