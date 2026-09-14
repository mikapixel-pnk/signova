import type {
  Metadata,
} from "next";

import "./globals.css";

import {
  ServiceWorkerRegistration,
} from "@/components/system/service-worker-registration";

export const metadata: Metadata = {
  title: {
    default: "SIGNOVA",
    template: "%s • SIGNOVA",
  },

  description:
    "Platform operasional bisnis yang sederhana, terhubung, dan mobile-first.",

  applicationName: "SIGNOVA",
  manifest: "/manifest.webmanifest",
};

const themeBootstrap = `
(function () {
  try {
    var key = "signova.theme.v1";
    var preference =
      localStorage.getItem(key) || "system";

    var resolved =
      preference === "system"
        ? (
            window.matchMedia(
              "(prefers-color-scheme: dark)"
            ).matches
              ? "dark"
              : "light"
          )
        : preference;

    document.documentElement.dataset.theme =
      resolved;

    document.documentElement.dataset.themePreference =
      preference;
  } catch (_) {}
})();
`;

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="id"
      suppressHydrationWarning
    >
      <head>
        <script
          dangerouslySetInnerHTML={{
            __html: themeBootstrap,
          }}
        />
      </head>

      <body>
        <ServiceWorkerRegistration />
        {children}
      </body>
    </html>
  );
}
