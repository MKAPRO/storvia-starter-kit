import { apiFetch, csrfApiFetch } from "@/lib/api/api-client";
import type {
  AuthSessionFreshness,
  AuthUser,
  LoginCredentials,
  StorviaLocale,
} from "@/lib/auth/auth-types";

type ResourceEnvelope<T> = {
  data: T;
};

export async function fetchCurrentUser(signal?: AbortSignal): Promise<AuthUser> {
  const response = await apiFetch<ResourceEnvelope<AuthUser>>(
    "/api/v1/auth/me",
    { signal },
  );

  return response.data;
}

export async function fetchSessionFreshness(
  signal?: AbortSignal,
): Promise<AuthSessionFreshness> {
  const response = await apiFetch<ResourceEnvelope<AuthSessionFreshness>>(
    "/api/v1/auth/freshness",
    { signal },
  );

  return response.data;
}

export async function loginUser(
  credentials: LoginCredentials,
): Promise<AuthUser> {
  const response = await csrfApiFetch<ResourceEnvelope<AuthUser>>(
    "/api/v1/auth/login",
    {
      method: "POST",
      body: JSON.stringify({
        // API v1 keeps the historical `email` key for backward compatibility.
        // Laravel accepts either an email address or a username in this field.
        email: credentials.identifier,
        password: credentials.password,
      }),
    },
  );

  return response.data;
}

export async function logoutUser(): Promise<void> {
  await csrfApiFetch<void>("/api/v1/auth/logout", {
    method: "POST",
  });
}

export async function updateCurrentUserLocale(
  locale: StorviaLocale,
): Promise<AuthUser> {
  const response = await csrfApiFetch<ResourceEnvelope<AuthUser>>(
    "/api/v1/auth/locale",
    {
      method: "PUT",
      body: JSON.stringify({ locale }),
    },
  );

  return response.data;
}
