import type { ReactNode } from "react";
import { notFound } from "next/navigation";

export default function DesignSystemLayout({
  children,
}: Readonly<{
  children: ReactNode;
}>) {
  if (process.env.NODE_ENV === "production") {
    notFound();
  }

  return children;
}
