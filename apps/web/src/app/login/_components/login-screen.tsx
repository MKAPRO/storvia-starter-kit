"use client";

import * as React from "react";
import { useRouter } from "next/navigation";
import { useTheme } from "@teispace/next-themes";
import {
  ArrowLeft,
  ArrowRight,
  Cloud,
  Eye,
  EyeOff,
  FileText,
  FolderOpen,
  HardDrive,
  Languages,
  LoaderCircle,
  LockKeyhole,
  Moon,
  Share2,
  ShieldCheck,
  Sun,
} from "lucide-react";

import { StorviaFlowVisual } from "@/components/brand/storvia-flow-visual";
import { StorviaMark } from "@/components/brand/storvia-mark";
import { BidiText } from "@/components/ui/bidi-text";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { isApiError, type ApiError } from "@/lib/api/api-error";
import { useAuth } from "@/lib/auth";
import { useI18n, type StorviaLocale } from "@/lib/i18n";
import { storviaDocumentTitle } from "@/lib/routing/page-titles";
import { safeWorkspaceNext } from "@/lib/routing/workspace-routes";
import { cn } from "@/lib/utils";

type LoginCopy = {
  eyebrow: string;
  title: string;
  description: string;
  identifierLabel: string;
  identifierPlaceholder: string;
  passwordLabel: string;
  passwordPlaceholder: string;
  showPassword: string;
  hidePassword: string;
  signIn: string;
  signingIn: string;
  managedAccess: string;
  noRegistration: string;
  visualEyebrow: string;
  visualTitle: string;
  visualDescription: string;
  privateStorage: string;
  secureSharing: string;
  controlledAccess: string;
  themeLight: string;
  themeDark: string;
  requiredFields: string;
  privateByDesign: string;
};

const COPY: Record<StorviaLocale, LoginCopy> = {
  en: {
    eyebrow: "Secure self-hosted access",
    title: "Welcome back",
    description: "Sign in to your private STORVIA workspace.",
    identifierLabel: "Email or username",
    identifierPlaceholder: "you@company.com or username",
    passwordLabel: "Password",
    passwordPlaceholder: "Enter your password",
    showPassword: "Show password",
    hidePassword: "Hide password",
    signIn: "Sign in",
    signingIn: "Signing in…",
    managedAccess: "Access is managed by your organization.",
    noRegistration: "Public registration is disabled.",
    visualEyebrow: "Your cloud. Your control.",
    visualTitle: "Files move. Control stays with you.",
    visualDescription:
      "A focused workspace for private storage, secure sharing, and controlled access.",
    privateStorage: "Private storage",
    secureSharing: "Secure sharing",
    controlledAccess: "Controlled access",
    themeLight: "Use light theme",
    themeDark: "Use dark theme",
    requiredFields: "Enter your email or username and password.",
    privateByDesign: "Private by design",
  },
  ar: {
    eyebrow: "دخول آمن إلى منصتك الخاصة",
    title: "مرحبًا بعودتك",
    description: "سجّل الدخول إلى مساحة STORVIA الخاصة بك.",
    identifierLabel: "البريد الإلكتروني أو اسم المستخدم",
    identifierPlaceholder: "البريد الإلكتروني أو اسم المستخدم",
    passwordLabel: "كلمة المرور",
    passwordPlaceholder: "أدخل كلمة المرور",
    showPassword: "إظهار كلمة المرور",
    hidePassword: "إخفاء كلمة المرور",
    signIn: "تسجيل الدخول",
    signingIn: "جارٍ تسجيل الدخول…",
    managedAccess: "تتم إدارة الوصول بواسطة مؤسستك.",
    noRegistration: "التسجيل العام غير متاح.",
    visualEyebrow: "سحابتك. تحت سيطرتك.",
    visualTitle: "ملفاتك تتحرك، والتحكم يبقى لديك.",
    visualDescription:
      "مساحة مركزة للتخزين الخاص والمشاركة الآمنة والتحكم الدقيق في الوصول.",
    privateStorage: "تخزين خاص",
    secureSharing: "مشاركة آمنة",
    controlledAccess: "وصول مُتحكم به",
    themeLight: "استخدام الوضع الفاتح",
    themeDark: "استخدام الوضع الداكن",
    requiredFields: "أدخل البريد الإلكتروني أو اسم المستخدم وكلمة المرور.",
    privateByDesign: "الخصوصية جزء من التصميم",
  },
};

function localizedError(error: ApiError | null, locale: StorviaLocale): string | null {
  if (!error) {
    return null;
  }

  const ar = locale === "ar";

  switch (error.code) {
    case "INVALID_CREDENTIALS":
      return ar
        ? "بيانات تسجيل الدخول غير صحيحة. تحقق من اسم المستخدم أو البريد وكلمة المرور."
        : "The username/email or password is incorrect.";
    case "USER_DISABLED":
      return ar
        ? "هذا الحساب معطّل. تواصل مع مسؤول STORVIA في مؤسستك."
        : "This account is disabled. Contact your STORVIA administrator.";
    case "TOO_MANY_LOGIN_ATTEMPTS":
      return ar
        ? "محاولات تسجيل دخول كثيرة. حاول مرة أخرى بعد قليل."
        : "Too many sign-in attempts. Try again shortly.";
    case "SESSION_EXPIRED":
      return ar
        ? "انتهت جلسة الحماية. أعد المحاولة."
        : "The security session expired. Please try again.";
    case "NETWORK_ERROR":
      return ar
        ? "تعذر الوصول إلى خادم STORVIA. تحقق من تشغيل الـAPI والاتصال."
        : "STORVIA API is unreachable. Check the API service and connection.";
    case "VALIDATION_FAILED":
      return ar ? "تحقق من بيانات تسجيل الدخول." : "Check your sign-in details.";
    default:
      return ar
        ? "تعذر إكمال تسجيل الدخول. حاول مرة أخرى."
        : "Sign in could not be completed. Please try again.";
  }
}

function loginDestination(): string {
  if (typeof window === "undefined") {
    return safeWorkspaceNext(null);
  }

  return safeWorkspaceNext(
    new URLSearchParams(window.location.search).get("next"),
  );
}

function FilePreviewCard({
  icon,
  name,
  meta,
  className,
}: {
  icon: React.ReactNode;
  name: string;
  meta: string;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "flex items-center gap-3 rounded-xl border border-white/12 bg-white/9 p-3.5 shadow-lg shadow-black/10 backdrop-blur-md",
        className,
      )}
    >
      <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white/12 text-brand-cyan">
        {icon}
      </div>
      <div className="min-w-0">
        <BidiText className="block truncate text-sm font-semibold text-white">
          {name}
        </BidiText>
        <p className="mt-0.5 text-xs text-white/55">{meta}</p>
      </div>
    </div>
  );
}

export function LoginScreen() {
  const router = useRouter();
  const { resolvedTheme, setTheme } = useTheme();
  const { isAuthenticated, login, status } = useAuth();
  const { locale, direction, setLocale } = useI18n();

  const [identifier, setIdentifier] = React.useState("");
  const [password, setPassword] = React.useState("");
  const [showPassword, setShowPassword] = React.useState(false);
  const [submitting, setSubmitting] = React.useState(false);
  const [apiError, setApiError] = React.useState<ApiError | null>(null);
  const [formError, setFormError] = React.useState<string | null>(null);

  const copy = COPY[locale];
  const isDark = resolvedTheme === "dark";
  const DirectionArrow = direction === "rtl" ? ArrowLeft : ArrowRight;

  React.useEffect(() => {
    if (isAuthenticated) {
      router.replace(loginDestination());
    }
  }, [isAuthenticated, router]);

  const switchLocale = (nextLocale: StorviaLocale) => {
    setLocale(nextLocale);
    setApiError(null);
    setFormError(null);
  };

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setApiError(null);
    setFormError(null);

    const normalizedIdentifier = identifier.trim();

    if (!normalizedIdentifier || !password) {
      setFormError(copy.requiredFields);
      return;
    }

    setSubmitting(true);

    try {
      await login({
        identifier: normalizedIdentifier,
        password,
      });
      router.replace(loginDestination());
    } catch (caught) {
      setApiError(isApiError(caught) ? caught : null);
      if (!isApiError(caught)) {
        setFormError(
          locale === "ar"
            ? "حدث خطأ غير متوقع أثناء تسجيل الدخول."
            : "An unexpected sign-in error occurred.",
        );
      }
    } finally {
      setSubmitting(false);
    }
  };

  const displayedError = formError ?? localizedError(apiError, locale);
  const sessionResolving = status === "loading" && !submitting;

  return (
    <>
      <title>{storviaDocumentTitle(locale, "login")}</title>
      <main
        dir={direction}
        lang={locale}
        className={cn(
          "min-h-screen bg-background text-foreground",
          locale === "ar" && "font-arabic",
        )}
      >
        <div className="grid min-h-screen lg:grid-cols-[minmax(0,0.92fr)_minmax(0,1.08fr)]">
          <section className="relative flex min-h-screen flex-col bg-background px-5 py-5 sm:px-8 lg:px-12 xl:px-16">
            <header className="flex items-center justify-between gap-4">
              <div className="flex items-center gap-2.5">
                <div className="flex size-10 items-center justify-center rounded-xl bg-brand-navy text-white shadow-sm dark:bg-brand-blue/18 dark:text-brand-blue">
                  <StorviaMark className="size-6" />
                </div>
                <div>
                  <p className="text-base font-bold tracking-tight">STORVIA</p>
                  <p className="text-[11px] text-muted-foreground">
                    Self-Hosted Cloud
                  </p>
                </div>
              </div>

              <div className="flex items-center gap-1.5 rounded-xl border border-border/80 bg-card p-1 shadow-xs">
                <Button
                  type="button"
                  size="sm"
                  variant={locale === "en" ? "secondary" : "ghost"}
                  className="h-8 px-2.5"
                  onClick={() => switchLocale("en")}
                  aria-pressed={locale === "en"}
                >
                  EN
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant={locale === "ar" ? "secondary" : "ghost"}
                  className="h-8 px-2.5 font-arabic"
                  onClick={() => switchLocale("ar")}
                  aria-pressed={locale === "ar"}
                >
                  عربي
                </Button>
                <div className="mx-0.5 h-5 w-px bg-border" aria-hidden="true" />
                <Button
                  type="button"
                  size="icon-sm"
                  variant="ghost"
                  aria-label={isDark ? copy.themeLight : copy.themeDark}
                  title={isDark ? copy.themeLight : copy.themeDark}
                  onClick={() => setTheme(isDark ? "light" : "dark")}
                >
                  {isDark ? <Sun /> : <Moon />}
                </Button>
              </div>
            </header>

            <div className="mx-auto flex w-full max-w-[440px] flex-1 items-center py-12 lg:py-16">
              <div className="w-full">
                <div className="mb-8">
                  <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-brand-blue/15 bg-brand-ice/65 px-3 py-1.5 text-xs font-semibold text-brand-navy dark:bg-brand-blue/10 dark:text-brand-blue">
                    <LockKeyhole className="size-3.5" />
                    {copy.eyebrow}
                  </div>
                  <h1 className="text-3xl font-bold tracking-tight sm:text-[2.15rem]">
                    {copy.title}
                  </h1>
                  <p className="mt-2.5 max-w-md text-sm leading-6 text-muted-foreground">
                    {copy.description}
                  </p>
                </div>

                <form className="space-y-5" onSubmit={handleSubmit} noValidate>
                  <div className="space-y-2">
                    <label
                      htmlFor="login-identifier"
                      className="text-sm font-semibold text-foreground"
                    >
                      {copy.identifierLabel}
                    </label>
                    <div className="relative">
                      <Languages className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                      <Input
                        id="login-identifier"
                        name="username"
                        autoComplete="username"
                        inputMode="email"
                        value={identifier}
                        onChange={(event) => setIdentifier(event.target.value)}
                        placeholder={copy.identifierPlaceholder}
                        disabled={submitting}
                        aria-invalid={Boolean(displayedError)}
                        className="h-11 ps-10"
                      />
                    </div>
                  </div>

                  <div className="space-y-2">
                    <label
                      htmlFor="login-password"
                      className="text-sm font-semibold text-foreground"
                    >
                      {copy.passwordLabel}
                    </label>
                    <div className="relative">
                      <LockKeyhole className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                      <Input
                        key={showPassword ? "visible-password" : "masked-password"}
                        id="login-password"
                        name="password"
                        type={showPassword ? "text" : "password"}
                        autoComplete="current-password"
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                        placeholder={copy.passwordPlaceholder}
                        disabled={submitting}
                        aria-invalid={Boolean(displayedError)}
                        className="h-11 ps-10 pe-11"
                      />
                      <button
                        type="button"
                        className="absolute end-1.5 top-1/2 z-10 inline-flex size-8 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/30 disabled:pointer-events-none disabled:opacity-50"
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => setShowPassword((value) => !value)}
                        aria-label={showPassword ? copy.hidePassword : copy.showPassword}
                        aria-pressed={showPassword}
                        title={showPassword ? copy.hidePassword : copy.showPassword}
                        disabled={submitting}
                      >
                        {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                      </button>
                    </div>
                  </div>

                  {displayedError ? (
                    <div
                      role="alert"
                      className="rounded-lg border border-destructive/25 bg-destructive/7 px-3.5 py-3 text-sm leading-5 text-destructive"
                    >
                      {displayedError}
                    </div>
                  ) : null}

                  <Button
                    type="submit"
                    size="lg"
                    className="h-11 w-full justify-between px-4"
                    disabled={submitting || sessionResolving}
                    aria-busy={submitting || sessionResolving}
                  >
                    <span className="flex items-center gap-2">
                      {submitting || sessionResolving ? (
                        <LoaderCircle className="animate-spin" />
                      ) : (
                        <ShieldCheck />
                      )}
                      {submitting ? copy.signingIn : copy.signIn}
                    </span>
                    <DirectionArrow />
                  </Button>
                </form>

                <div className="mt-7 border-t border-border/70 pt-5">
                  <p className="text-xs leading-5 text-muted-foreground">
                    {copy.managedAccess} {copy.noRegistration}
                  </p>
                </div>
              </div>
            </div>

            <footer className="flex items-center justify-between gap-4 text-[11px] text-muted-foreground">
              <span>© 2026 STORVIA</span>
              <span className="hidden sm:inline">{copy.privateByDesign}</span>
            </footer>
          </section>

          <aside className="relative hidden min-h-screen overflow-hidden bg-brand-navy text-white lg:flex lg:flex-col">
            <div
              className="absolute inset-0 opacity-50"
              aria-hidden="true"
              style={{
                backgroundImage:
                  "radial-gradient(circle at 75% 15%, rgba(71, 186, 255, 0.20), transparent 32%), radial-gradient(circle at 20% 82%, rgba(48, 213, 200, 0.12), transparent 30%)",
              }}
            />
            <div
              className="absolute inset-0 opacity-[0.08]"
              aria-hidden="true"
              style={{
                backgroundImage:
                  "linear-gradient(rgba(255,255,255,.7) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.7) 1px, transparent 1px)",
                backgroundSize: "56px 56px",
              }}
            />

            <div className="relative z-10 flex h-full flex-1 flex-col px-10 py-10 xl:px-14 xl:py-12">
              <div className="flex items-center gap-3 text-white/70">
                <Cloud className="size-5 text-brand-cyan" />
                <span className="text-xs font-semibold uppercase tracking-[0.18em]">
                  {copy.visualEyebrow}
                </span>
              </div>

              <div className="mx-auto flex w-full max-w-[720px] flex-1 flex-col justify-center py-8">
                <div className="max-w-[580px]">
                  <h2 className="text-4xl font-semibold leading-[1.12] tracking-tight text-white xl:text-5xl">
                    {copy.visualTitle}
                  </h2>
                  <p className="mt-5 max-w-[530px] text-sm leading-7 text-white/62 xl:text-base">
                    {copy.visualDescription}
                  </p>
                </div>

                <div className="relative mt-10 min-h-[390px] xl:min-h-[430px]">
                  <div className="absolute inset-x-[7%] top-2 rounded-[2rem] border border-white/10 bg-white/[0.055] p-5 shadow-2xl shadow-black/15 backdrop-blur-sm">
                    <StorviaFlowVisual className="mx-auto max-w-[620px] [&_.stroke-border]:stroke-white/12 [&_.fill-card]:fill-white/8 [&_.fill-background]:fill-white/8" />
                  </div>

                  <FilePreviewCard
                    icon={<FolderOpen className="size-5" />}
                    name={locale === "ar" ? "ملفات المشاريع" : "Project files"}
                    meta={copy.privateStorage}
                    className="absolute start-0 top-20 w-[220px]"
                  />
                  <FilePreviewCard
                    icon={<FileText className="size-5" />}
                    name="Q4-تقرير-final.xlsx"
                    meta={copy.secureSharing}
                    className="absolute end-0 top-[46%] w-[236px]"
                  />
                  <FilePreviewCard
                    icon={<HardDrive className="size-5" />}
                    name="backup_2026.zip"
                    meta={copy.controlledAccess}
                    className="absolute bottom-0 start-[21%] w-[228px]"
                  />
                </div>
              </div>

              <div className="grid grid-cols-3 gap-3 border-t border-white/10 pt-5 text-xs text-white/55">
                <div className="flex items-center gap-2">
                  <HardDrive className="size-4 text-brand-cyan" />
                  {copy.privateStorage}
                </div>
                <div className="flex items-center gap-2">
                  <Share2 className="size-4 text-brand-cyan" />
                  {copy.secureSharing}
                </div>
                <div className="flex items-center gap-2">
                  <ShieldCheck className="size-4 text-brand-cyan" />
                  {copy.controlledAccess}
                </div>
              </div>
            </div>
          </aside>
        </div>
      </main>
    </>
  );
}
