export const PLATFORM_ADMIN_BASE_PATH =
  "/signova/admin";

export type PlatformAdminIconKey =
  | "dashboard"
  | "tenant"
  | "plan"
  | "subscription"
  | "order"
  | "usage"
  | "integration"
  | "notification"
  | "audit"
  | "settings";

export type PlatformAdminNavigationItem = {
  section: string | null;
  label: string;
  shortLabel: string;
  description: string;
  href: string;
  capability: string;
  icon: PlatformAdminIconKey;
};

export const platformAdminNavigation:
  readonly PlatformAdminNavigationItem[] = [
    {
      section: null,
      label: "Beranda",
      shortLabel: "Beranda",
      description:
        "Ringkasan pengelolaan platform SIGNOVA.",
      href: PLATFORM_ADMIN_BASE_PATH,
      capability:
        "platform.dashboard.view",
      icon: "dashboard",
    },
    {
      section: "tenant",
      label: "Tenant",
      shortLabel: "Tenant",
      description:
        "Kelola akun dan lifecycle tenant SIGNOVA.",
      href:
        `${PLATFORM_ADMIN_BASE_PATH}/tenant`,
      capability:
        "platform.tenant.view",
      icon: "tenant",
    },
    {
      section: "paket",
      label: "Paket & Harga",
      shortLabel: "Paket",
      description:
        "Kelola katalog paket dan harga SaaS.",
      href:
        `${PLATFORM_ADMIN_BASE_PATH}/paket`,
      capability:
        "platform.plan.view",
      icon: "plan",
    },
    {
      section: "langganan",
      label: "Langganan",
      shortLabel: "Langganan",
      description:
        "Pantau lifecycle langganan tenant.",
      href:
        `${PLATFORM_ADMIN_BASE_PATH}/langganan`,
      capability:
        "platform.subscription.view",
      icon: "subscription",
    },
    {
      section: "pesanan-paket",
      label: "Pesanan Paket",
      shortLabel: "Pesanan",
      description:
        "Kelola pesanan dan aktivasi paket.",
      href:
        `${PLATFORM_ADMIN_BASE_PATH}/pesanan-paket`,
      capability:
        "platform.order.view",
      icon: "order",
    },
    {
      section: "kuota",
      label: "Kuota & Pemakaian",
      shortLabel: "Kuota",
      description:
        "Pantau penggunaan dan batas paket.",
      href:
        `${PLATFORM_ADMIN_BASE_PATH}/kuota`,
      capability:
        "platform.usage.view",
      icon: "usage",
    },
    {
      section: "integrasi",
      label: "Integrasi",
      shortLabel: "Integrasi",
      description:
        "Pantau status integrasi platform.",
      href:
        `${PLATFORM_ADMIN_BASE_PATH}/integrasi`,
      capability:
        "platform.integration.view",
      icon: "integration",
    },
    {
      section: "notifikasi",
      label: "Notifikasi",
      shortLabel: "Notifikasi",
      description:
        "Pantau notifikasi platform.",
      href:
        `${PLATFORM_ADMIN_BASE_PATH}/notifikasi`,
      capability:
        "platform.notification.view",
      icon: "notification",
    },
    {
      section: "audit",
      label: "Audit",
      shortLabel: "Audit",
      description:
        "Tinjau jejak aktivitas platform.",
      href:
        `${PLATFORM_ADMIN_BASE_PATH}/audit`,
      capability:
        "platform.audit.view",
      icon: "audit",
    },
    {
      section: "pengaturan",
      label: "Pengaturan",
      shortLabel: "Pengaturan",
      description:
        "Kelola konfigurasi SaaS SIGNOVA.",
      href:
        `${PLATFORM_ADMIN_BASE_PATH}/pengaturan`,
      capability:
        "platform.settings.view",
      icon: "settings",
    },
  ];

export function platformNavigationItemForSection(
  section: string,
): PlatformAdminNavigationItem | null {
  return (
    platformAdminNavigation.find(
      (item) =>
        item.section === section,
    ) ?? null
  );
}

export function platformNavigationItemForPath(
  pathname: string,
): PlatformAdminNavigationItem | null {
  if (
    pathname ===
    PLATFORM_ADMIN_BASE_PATH
  ) {
    return (
      platformAdminNavigation[0] ??
      null
    );
  }

  return (
    platformAdminNavigation.find(
      (item) =>
        item.section !== null &&
        (
          pathname === item.href ||
          pathname.startsWith(
            `${item.href}/`,
          )
        ),
    ) ?? null
  );
}
