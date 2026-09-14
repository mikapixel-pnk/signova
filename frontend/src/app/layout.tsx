import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: {
    default: "SIGNOVA",
    template: "%s • SIGNOVA",
  },

  description:
    "Platform operasional bisnis yang sederhana, terhubung, dan mobile-first.",

  applicationName: "SIGNOVA",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="id">
      <body>{children}</body>
    </html>
  );
}
