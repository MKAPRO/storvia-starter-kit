import { ApiError, type ApiErrorPayload } from "@/lib/api/api-error";

const DEFAULT_API_ORIGIN = "http://localhost:8000";
const SAFE_METHODS = new Set(["GET", "HEAD", "OPTIONS"]);

const configuredApiOrigin = process.env.NEXT_PUBLIC_STORVIA_API_URL?.trim();

if (process.env.NODE_ENV === "production" && !configuredApiOrigin) {
  throw new Error(
    "NEXT_PUBLIC_STORVIA_API_URL is required for STORVIA production builds.",
  );
}

export const apiOrigin = (configuredApiOrigin || DEFAULT_API_ORIGIN).replace(
  /\/+$/,
  "",
);

export function buildApiUrl(path: string): string {
  if (/^https?:\/\//i.test(path)) {
    return path;
  }

  return `${apiOrigin}${path.startsWith("/") ? path : `/${path}`}`;
}

function readCookie(name: string): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  const prefix = `${encodeURIComponent(name)}=`;
  const cookie = document.cookie
    .split(";")
    .map((part) => part.trim())
    .find((part) => part.startsWith(prefix));

  if (!cookie) {
    return null;
  }

  return decodeURIComponent(cookie.slice(prefix.length));
}

export function getXsrfToken(): string | null {
  return readCookie("XSRF-TOKEN");
}

function isJsonBody(body: BodyInit | null | undefined): boolean {
  if (body == null) {
    return false;
  }

  if (typeof FormData !== "undefined" && body instanceof FormData) {
    return false;
  }

  if (typeof URLSearchParams !== "undefined" && body instanceof URLSearchParams) {
    return false;
  }

  return typeof body === "string";
}

async function parseApiError(
  response: Response,
  fallbackMessage = "The API request failed.",
): Promise<ApiError> {
  let payload: ApiErrorPayload | null = null;

  try {
    payload = (await response.json()) as ApiErrorPayload;
  } catch {
    payload = null;
  }

  if (payload?.error?.code && payload.error.message) {
    return new ApiError({
      code: payload.error.code,
      message: payload.error.message,
      status: response.status,
      details: payload.error.details,
    });
  }

  return new ApiError({
    code: "HTTP_ERROR",
    message: response.statusText || fallbackMessage,
    status: response.status,
  });
}

export type ApiFetchResponseOptions = {
  fallbackErrorMessage?: string;
};

export async function apiFetchResponse(
  path: string,
  init: RequestInit = {},
  options: ApiFetchResponseOptions = {},
): Promise<Response> {
  const method = (init.method ?? "GET").toUpperCase();
  const headers = new Headers(init.headers);

  if (!headers.has("Accept")) {
    headers.set("Accept", "application/json");
  }

  if (isJsonBody(init.body) && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  if (!SAFE_METHODS.has(method) && !headers.has("X-XSRF-TOKEN")) {
    const token = getXsrfToken();

    if (token) {
      headers.set("X-XSRF-TOKEN", token);
    }
  }

  let response: Response;

  try {
    response = await fetch(buildApiUrl(path), {
      ...init,
      method,
      headers,
      credentials: "include",
    });
  } catch (cause) {
    throw new ApiError({
      code: "NETWORK_ERROR",
      message: "Unable to reach the STORVIA API.",
      status: 0,
      cause,
    });
  }

  if (!response.ok) {
    throw await parseApiError(
      response,
      options.fallbackErrorMessage ?? "The API request failed.",
    );
  }

  return response;
}

export async function apiFetch<T>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const response = await apiFetchResponse(path, init);

  if (response.status === 204) {
    return undefined as T;
  }

  return (await response.json()) as T;
}

export async function ensureCsrfCookie(force = false): Promise<void> {
  if (!force && getXsrfToken()) {
    return;
  }

  await apiFetch<void>("/sanctum/csrf-cookie", {
    method: "GET",
  });
}

export async function csrfApiFetch<T>(
  path: string,
  init: RequestInit,
): Promise<T> {
  await ensureCsrfCookie();

  try {
    return await apiFetch<T>(path, init);
  } catch (error) {
    if (error instanceof ApiError && error.status === 419) {
      await ensureCsrfCookie(true);
      return apiFetch<T>(path, init);
    }

    throw error;
  }
}
