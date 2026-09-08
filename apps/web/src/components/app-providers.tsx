"use client";

import * as React from "react";
import { usePathname } from "next/navigation";

import { ThemeProvider } from "@/components/theme-provider";
import { Toaster } from "@/components/ui/toast";
import { AuthProvider } from "@/lib/auth";
import { I18nProvider, PublicI18nProvider } from "@/lib/i18n";

function isPublicSharePath(pathname: string): boolean {
  const segments = pathname.split("/").filter(Boolean);

  return segments.length === 2 && segments[0] === "s" && segments[1] !== "";
}

export function AppProviders({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const publicShare = isPublicSharePath(pathname);

  return (
    <ThemeProvider
      attribute="class"
      defaultTheme="system"
      enableSystem
      disableTransitionOnChange
    >
      {publicShare ? (
        <PublicI18nProvider>
          {children}
          <Toaster />
        </PublicI18nProvider>
      ) : (
        <AuthProvider>
          <I18nProvider>
            {children}
            <Toaster />
          </I18nProvider>
        </AuthProvider>
      )}
    </ThemeProvider>
  );
}
