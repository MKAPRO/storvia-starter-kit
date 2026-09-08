export type StableApiErrorCode =
  | "AUTH_REQUIRED"
  | "INVALID_CREDENTIALS"
  | "USER_DISABLED"
  | "ACCESS_DENIED"
  | "VALIDATION_FAILED"
  | "RESOURCE_CONFLICT"
  | "RESOURCE_NOT_FOUND"
  | "FILE_CONTENT_UNAVAILABLE"
  | "STORAGE_QUOTA_EXCEEDED"
  | "RESOURCE_PASSWORD_REQUIRED"
  | "RESOURCE_PASSWORD_INVALID"
  | "TOO_MANY_RESOURCE_PASSWORD_ATTEMPTS"
  | "SESSION_EXPIRED"
  | "TOO_MANY_LOGIN_ATTEMPTS";

export type ClientApiErrorCode = StableApiErrorCode | "NETWORK_ERROR" | "HTTP_ERROR";

export type ApiFieldErrors = Record<string, string[]>;

export type ApiErrorDetails = {
  fields?: ApiFieldErrors;
  [key: string]: unknown;
};

export type ApiErrorPayload = {
  error: {
    code: string;
    message: string;
    details?: ApiErrorDetails;
  };
};

export class ApiError extends Error {
  readonly code: ClientApiErrorCode | string;
  readonly status: number;
  readonly details?: ApiErrorDetails;

  constructor({
    code,
    message,
    status,
    details,
    cause,
  }: {
    code: ClientApiErrorCode | string;
    message: string;
    status: number;
    details?: ApiErrorDetails;
    cause?: unknown;
  }) {
    super(message, { cause });
    this.name = "ApiError";
    this.code = code;
    this.status = status;
    this.details = details;
  }
}

export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError;
}
