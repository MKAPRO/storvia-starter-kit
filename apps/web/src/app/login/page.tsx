import type { Metadata } from "next";

import { LoginScreen } from "@/app/login/_components/login-screen";

export const metadata: Metadata = {
  description: "Secure access to your self-hosted STORVIA cloud.",
};

export default function LoginPage() {
  return <LoginScreen />;
}
