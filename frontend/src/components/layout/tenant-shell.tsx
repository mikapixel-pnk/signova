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
  icon: React.ComponentType<{
    size?: number;
    strokeWidth?: number;
  }>;
};

const salesNavigation: NavigationItem[] = [
  {
    label: "Pelanggan",
    href: "/app/customers",
    icon: Users,
  },
  {
    label: "Barang & Jasa",
    href: "/app/catalog",
    icon: Package,
  },
  {
    label: "Penawaran",
    href: "/app/quotations",
    icon: BookOpenText,
  },
  {
    label: "Tagihan",
    href: "/app/invoices",
    icon: FileText,
  },
  {
    label: "Pembayaran",
    href: "/app/payments",
    icon: ReceiptText,
  },
];

const financeNavigation: NavigationItem[] = [
  {
    label: "Ringkasan",
    href: "/app/finance",
    icon: WalletCards,
  },
  {
    label: "Kas & Bank",
    href: "/app/finance/cash-bank",
    icon: Building2,
  },
  {
    label: "Pemasukan",
    href: "/app/finance/incomes",
    icon: HandCoins,
  },
  {
    label: "Pengeluaran",
    href: "/app/finance/expenses",
    icon: CircleDollarSign,
  },
  {
    label: "Piutang",
    href: "/app/finance/receivables",
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
                href: "/app/settings",
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
              SIGNOVA
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
          {children}
        </main>

        <nav
          className={styles.bottomNavigation}
          aria-label="Navigasi utama"
        >
          <Link
            href="/app"
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
            href="/app/invoices"
            className={
              isActiveRoute(
                pathname,
                "/app/invoices",
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
            href="/app/actions"
            className={styles.bottomPrimary}
            aria-label="Tambah"
          >
            <span>+</span>
          </Link>

          <Link
            href="/app/finance"
            className={
              isActiveRoute(
                pathname,
                "/app/finance",
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
