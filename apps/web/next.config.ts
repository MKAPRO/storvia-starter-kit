import type { NextConfig } from "next";

const configuredApiOrigin = process.env.NEXT_PUBLIC_STORVIA_API_URL?.trim();

if (process.env.NODE_ENV === "production" && !configuredApiOrigin) {
  throw new Error(
    "NEXT_PUBLIC_STORVIA_API_URL is required for STORVIA production builds.",
  );
}

function envFlag(name: string): boolean {
  return process.env[name]?.trim().toLowerCase() === "true";
}

function hstsHeader(): { key: string; value: string } | null {
  if (process.env.NODE_ENV !== "production" || !envFlag("STORVIA_HSTS_ENABLED")) {
    return null;
  }

  const rawMaxAge = process.env.STORVIA_HSTS_MAX_AGE?.trim() || "31536000";
  const maxAge = Number(rawMaxAge);

  if (!Number.isSafeInteger(maxAge) || maxAge <= 0) {
    throw new Error(
      "STORVIA_HSTS_MAX_AGE must be a positive integer when HSTS is enabled.",
    );
  }

  let value = `max-age=${maxAge}`;

  if (envFlag("STORVIA_HSTS_INCLUDE_SUBDOMAINS")) {
    value += "; includeSubDomains";
  }

  return { key: "Strict-Transport-Security", value };
}

const browserSecurityHeaders = [
  { key: "Referrer-Policy", value: "no-referrer" },
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "X-Frame-Options", value: "DENY" },
  { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=()" },
  {
    key: "Content-Security-Policy",
    value: "frame-ancestors 'none'; base-uri 'self'; object-src 'none'",
  },
];

const configuredHstsHeader = hstsHeader();

if (configuredHstsHeader) {
  browserSecurityHeaders.push(configuredHstsHeader);
}

const nextConfig: NextConfig = {
  async headers() {
    return [
      {
        source: "/:path*",
        headers: browserSecurityHeaders,
      },
    ];
  },
};

export default nextConfig;
