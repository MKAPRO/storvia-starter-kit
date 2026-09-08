import type { StorviaLocale } from "@/lib/i18n";

type WorkspaceFooterProps = {
  locale: StorviaLocale;
};

const FOOTER_COPY = {
  en: "All rights reserved",
  ar: "جميع الحقوق محفوظة",
} as const;

export function WorkspaceFooter({ locale }: WorkspaceFooterProps) {
  return (
    <footer className="relative min-h-14 shrink-0 border-t border-workspace-content-border bg-workspace-panel/95 px-3 text-[10px] text-muted-foreground backdrop-blur-sm sm:min-h-10 sm:text-[10.5px]">
      <span
        className="absolute inset-x-3 top-2 truncate text-center font-semibold tracking-[0.025em] text-foreground sm:inset-x-[26%] sm:top-1/2 sm:-translate-y-1/2"
        dir={locale === "ar" ? "rtl" : "ltr"}
      >
        © 2026 MKAPRO — {FOOTER_COPY[locale]}
      </span>

      <span
        className="absolute bottom-2 left-3 max-w-[calc(100%_-_1.5rem)] truncate text-left sm:bottom-auto sm:top-1/2 sm:max-w-[24%] sm:-translate-y-1/2"
        dir="ltr"
      >
        Powered By{" "}
        <a
          href="https://github.com/MKAPRO/"
          target="_blank"
          rel="noopener noreferrer"
          className="font-semibold text-foreground underline-offset-2 transition-colors hover:text-primary hover:underline focus-visible:rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/60"
        >
          Ansi-team
        </a>
      </span>
    </footer>
  );
}
