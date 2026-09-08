import type { StorviaLocale } from "@/lib/i18n/i18n-types";

export type { StorviaLocale } from "@/lib/i18n/i18n-types";

export type AuthUserStatus = "active" | "disabled";

export type AuthWorkspaceEntitlements = {
  personal_space_enabled: boolean;
  has_organizational_file_access: boolean;
};

export type AuthUser = {
  id: string;
  name: string;
  username: string | null;
  email: string;
  status: AuthUserStatus;
  locale: StorviaLocale;
  roles: string[];
  permissions: string[];
  workspace_entitlements: AuthWorkspaceEntitlements;
  organizational_scope_revision: string;
  session_freshness_revision: string;
  last_login_at: string | null;
};

export type AuthSessionFreshness = {
  revision: string;
};

export type LoginCredentials = {
  identifier: string;
  password: string;
};

export type AuthStatus =
  | "loading"
  | "authenticated"
  | "unauthenticated"
  | "disabled";
