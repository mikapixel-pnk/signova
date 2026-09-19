import type {
  Metadata,
} from "next";

import type {
  ReactNode,
} from "react";

import {
  PlatformAdminShell,
} from "@/components/platform-admin/platform-admin-shell";

import {
  PlatformSessionGate,
} from "@/components/platform-admin/platform-session-gate";

export const metadata: Metadata = {
  title: "Super Admin",

  robots: {
    index: false,
    follow: false,
    nocache: true,

    googleBot: {
      index: false,
      follow: false,
      noimageindex: true,
    },
  },
};

export default function PlatformAdminLayout({
  children,
}: {
  children: ReactNode;
}) {
  return (
    <PlatformSessionGate>
      <PlatformAdminShell>
        {children}
      </PlatformAdminShell>
    </PlatformSessionGate>
  );
}
