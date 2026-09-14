import type { Metadata } from "next";
import "./globals.css";
import { ServiceWorkerRegistration } from "@/components/system/service-worker-registration";

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

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="id">
      <body>
        <ServiceWorkerRegistration />
        {children}
      </body>
    </html>
  );
}
