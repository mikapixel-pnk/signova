"use client";

import {
  Bell,
  BookOpenText,
  Building2,
  CircleDollarSign,
  FileText,
  HandCoins,
  Home,
  LogOut,
  Package,
  ReceiptText,
  Settings,
  Users,
  WalletCards,
} from "lucide-react";

import Link from "next/link";
import {
  usePathname,
  useRouter,
} from "next/navigation";
import {
  useState,
} from "react";

import {
  TenantSessionGate,
} from "@/components/auth/tenant-session-gate";
import {
  SyncStatus,
} from "@/components/system/sync-status";
import {
  OfflineRouteWarmup,
} from "@/components/system/offline-route-warmup";
import {
  Brand,
} from "@/components/ui/brand";
import {
  logout,
} from "@/lib/auth/logout";

import styles from "./tenant-shell.module.css";

type TenantShellProps = {
  children: React.ReactNode;
};

type NavigationItem = {
  label: string;
  href: string;
  tone:
    | "blue"
    | "cyan"
    | "teal"
    | "green"
    | "violet"
    | "rose";
  icon: React.ComponentType<{
    size?: number;
    strokeWidth?: number;
  }>;
};

const salesNavigation: NavigationItem[] = [
  {
    label: "Pelanggan",
    tone: "violet",
    href: "/app/pelanggan",
    icon: Users,
  },
  {
    label: "Barang & Jasa",
    tone: "cyan",
    href: "/app/barang-jasa",
    icon: Package,
  },
  {
    label: "Penawaran",
    tone: "violet",
    href: "/app/penawaran",
    icon: BookOpenText,
  },
  {
    label: "Tagihan",
    tone: "blue",
    href: "/app/tagihan",
    icon: FileText,
  },
  {
    label: "Pembayaran",
    tone: "teal",
    href: "/app/pembayaran",
    icon: ReceiptText,
  },
];

const financeNavigation: NavigationItem[] = [
  {
    label: "Ringkasan",
    tone: "teal",
    href: "/app/keuangan",
    icon: WalletCards,
  },
  {
    label: "Kas & Bank",
    tone: "green",
    href: "/app/keuangan/kas-bank",
    icon: Building2,
  },
  {
    label: "Pemasukan",
    tone: "cyan",
    href: "/app/keuangan/pemasukan",
    icon: HandCoins,
  },
  {
    label: "Pengeluaran",
    tone: "rose",
    href: "/app/keuangan/pengeluaran",
    icon: CircleDollarSign,
  },
  {
    label: "Piutang",
    tone: "blue",
    href: "/app/keuangan/piutang",
    icon: ReceiptText,
  },
];

function isActiveRoute(
  pathname: string,
  href: string,
): boolean {
  if (href === "/app") {
    return pathname === "/app";
  }

  return (
    pathname === href ||
    pathname.startsWith(`${href}/`)
  );
}

function NavigationLink({
  item,
  pathname,
}: {
  item: NavigationItem;
  pathname: string;
}) {
  const Icon = item.icon;
  const active =
    isActiveRoute(pathname, item.href);

  return (
    <Link
      href={item.href}
      data-tone={item.tone}
      className={
        active
          ? styles.sidebarLinkActive
          : styles.sidebarLink
      }
    >
      <Icon
        size={19}
        strokeWidth={1.9}
      />
      <span>{item.label}</span>
    </Link>
  );
}

export function TenantShell({
  children,
}: TenantShellProps) {
  const pathname = usePathname();
  const router = useRouter();

  const [
    loggingOut,
    setLoggingOut,
  ] = useState(false);

  const [
    activeTenantName,
    setActiveTenantName,
  ] = useState(
    "Memuat usaha...",
  );

  async function handleLogout() {
    if (loggingOut) {
      return;
    }

    setLoggingOut(true);

    try {
      await logout();
    } finally {
      router.replace("/login");
    }
  }

  return (
    <div className={styles.shell}>
      <OfflineRouteWarmup />

      <aside className={styles.sidebar}>
        <div className={styles.sidebarBrand}>
          <Brand />
        </div>

        <nav
          className={styles.sidebarNavigation}
          aria-label="Menu SIGNOVA"
        >
          <NavigationLink
            pathname={pathname}
            item={{
              label: "Beranda",
              href: "/app",
              tone: "cyan",
              icon: Home,
            }}
          />

          <div className={styles.navSection}>
            <span className={styles.navSectionLabel}>
              Penjualan
            </span>

            {salesNavigation.map((item) => (
              <NavigationLink
                key={item.href}
                item={item}
                pathname={pathname}
              />
            ))}
          </div>

          <div className={styles.navSection}>
            <span className={styles.navSectionLabel}>
              Keuangan
            </span>

            {financeNavigation.map((item) => (
              <NavigationLink
                key={item.href}
                item={item}
                pathname={pathname}
              />
            ))}
          </div>

          <div className={styles.navSection}>
            <span className={styles.navSectionLabel}>
              Pengaturan
            </span>

            <NavigationLink
              pathname={pathname}
              item={{
                label: "Pengaturan",
                href: "/app/pengaturan",
                tone: "violet",
                icon: Settings,
              }}
            />
          </div>
        </nav>

        <div className={styles.sidebarFooter}>
          <div className={styles.sidebarSync}>
            <SyncStatus />
          </div>

          <button
            className={styles.sidebarLogout}
            type="button"
            disabled={loggingOut}
            onClick={
              () => void handleLogout()
            }
          >
            <LogOut
              size={18}
              strokeWidth={1.9}
            />

            <span>
              {loggingOut
                ? "Sedang keluar..."
                : "Keluar"}
            </span>
          </button>
        </div>
      </aside>

      <div className={styles.content}>
        <header className={styles.header}>
          <div className={styles.mobileBrand}>
            <Brand />
          </div>

          <div className={styles.headerContext}>
            <span className={styles.headerLabel}>
              Usaha aktif
            </span>

            <strong
              className={styles.headerBusiness}
            >
              {activeTenantName}
            </strong>
          </div>

          <div className={styles.headerActions}>
            <SyncStatus />

            <button
              className={styles.iconButton}
              type="button"
              aria-label="Notifikasi"
            >
              <Bell
                size={20}
                strokeWidth={1.9}
              />

              <span
                className={
                  styles.notificationDot
                }
              />
            </button>
          </div>
        </header>

        <main className={styles.main}>
          <TenantSessionGate
            onTenantResolved={
              setActiveTenantName
            }
          >
            {children}
          </TenantSessionGate>
        </main>

        <nav
          className={styles.bottomNavigation}
          aria-label="Navigasi utama"
        >
          <Link
            href="/app"
            data-tone="cyan"
            className={
              isActiveRoute(
                pathname,
                "/app",
              )
                ? styles.bottomLinkActive
                : styles.bottomLink
            }
          >
            <Home
              size={21}
              strokeWidth={1.9}
            />
            <span>Beranda</span>
          </Link>

          <Link
            href="/app/tagihan"
            data-tone="blue"
            className={
              isActiveRoute(
                pathname,
                "/app/tagihan",
              )
                ? styles.bottomLinkActive
                : styles.bottomLink
            }
          >
            <FileText
              size={21}
              strokeWidth={1.9}
            />
            <span>Tagihan</span>
          </Link>

          <Link
            href="/app/aksi"
            data-tone="primary"
            className={styles.bottomPrimary}
            aria-label="Tambah"
          >
            <span>+</span>
          </Link>

          <Link
            href="/app/keuangan"
            data-tone="green"
            className={
              isActiveRoute(
                pathname,
                "/app/keuangan",
              )
                ? styles.bottomLinkActive
                : styles.bottomLink
            }
          >
            <WalletCards
              size={21}
              strokeWidth={1.9}
            />
            <span>Keuangan</span>
          </Link>

          <Link
            href="/app/menu"
            data-tone="violet"
            className={
              isActiveRoute(
                pathname,
                "/app/menu",
              )
                ? styles.bottomLinkActive
                : styles.bottomLink
            }
          >
            <Settings
              size={21}
              strokeWidth={1.9}
            />
            <span>Lainnya</span>
          </Link>
        </nav>
      </div>
    </div>
  );
}
