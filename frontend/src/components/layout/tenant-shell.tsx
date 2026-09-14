"use client";

import {
  Bell,
  BookOpenText,
  Building2,
  ChevronDown,
  CircleDollarSign,
  FileText,
  HandCoins,
  Home,
  LogOut,
  Package,
  ReceiptText,
  Settings,
  ShieldCheck,
  Store,
  UserRound,
  Users,
  WalletCards,
  Wrench,
} from "lucide-react";

import Link from "next/link";

import {
  usePathname,
  useRouter,
} from "next/navigation";

import {
  useEffect,
  useState,
} from "react";

import {
  TenantSessionGate,
} from "@/components/auth/tenant-session-gate";

import {
  OfflineRouteWarmup,
} from "@/components/system/offline-route-warmup";

import {
  SyncStatus,
} from "@/components/system/sync-status";

import {
  Brand,
} from "@/components/ui/brand";

import {
  ThemeQuickToggle,
} from "@/components/theme/theme-quick-toggle";

import {
  getAuthContext,
} from "@/lib/auth/context";

import {
  logout,
} from "@/lib/auth/logout";

import styles from "./tenant-shell.module.css";

type Tone =
  | "blue"
  | "cyan"
  | "teal"
  | "green"
  | "violet"
  | "rose";

type NavigationItem = {
  label: string;
  href: string;
  tone: Tone;

  badge?:
    | "Business"
    | "Pro";

  icon: React.ComponentType<{
    size?: number;
    strokeWidth?: number;
  }>;
};

type NavigationGroup = {
  id: string;
  label: string;
  icon: React.ComponentType<{
    size?: number;
    strokeWidth?: number;
  }>;
  tone: Tone;
  items: NavigationItem[];
};

type TenantShellProps = {
  children: React.ReactNode;
};

const navigationGroups: NavigationGroup[] = [
  {
    id: "master-data",
    label: "Master Data",
    icon: Package,
    tone: "violet",
    items: [
      {
        label: "Pelanggan",
        href: "/app/pelanggan",
        tone: "violet",
        icon: Users,
      },
      {
        label: "Barang & Jasa",
        href: "/app/barang-jasa",
        tone: "cyan",
        icon: Package,
      },
      {
        label: "Kategori",
        href: "/app/barang-jasa?bagian=kategori",
        tone: "blue",
        icon: Store,
      },
      {
        label: "Satuan",
        href: "/app/barang-jasa?bagian=satuan",
        tone: "teal",
        icon: Wrench,
      },
    ],
  },
  {
    id: "penjualan",
    label: "Penjualan",
    icon: ReceiptText,
    tone: "blue",
    items: [
      {
        label: "Penawaran",
        href: "/app/penawaran",
        tone: "violet",
        icon: BookOpenText,
      },
      {
        label: "Tagihan",
        href: "/app/tagihan",
        tone: "blue",
        icon: FileText,
      },
      {
        label: "Pembayaran",
        href: "/app/pembayaran",
        tone: "teal",
        icon: ReceiptText,
      },
    ],
  },
  {
    id: "keuangan",
    label: "Keuangan",
    icon: WalletCards,
    tone: "green",
    items: [
      {
        label: "Ringkasan",
        href: "/app/keuangan",
        tone: "teal",
        icon: WalletCards,
      },
      {
        label: "Kas & Bank",
        href: "/app/keuangan/kas-bank",
        tone: "green",
        icon: Building2,
      },
      {
        label: "Pemasukan",
        href: "/app/keuangan/pemasukan",
        tone: "cyan",
        icon: HandCoins,
      },
      {
        label: "Pengeluaran",
        href: "/app/keuangan/pengeluaran",
        tone: "rose",
        icon: CircleDollarSign,
      },
      {
        label: "Piutang",
        href: "/app/keuangan/piutang",
        tone: "blue",
        icon: ReceiptText,
      },
    ],
  },
  {
    id: "pengaturan",
    label: "Pengaturan",
    icon: Settings,
    tone: "violet",
    items: [
      {
        label: "Pengaturan Bisnis",
        href: "/app/pengaturan?bagian=bisnis",
        tone: "cyan",
        icon: Building2,
      },
      {
        label: "Profil Saya",
        href: "/app/pengaturan?bagian=profil",
        tone: "violet",
        icon: UserRound,
      },
      {
        label: "Pengaturan Keuangan",
        href: "/app/pengaturan?bagian=keuangan",
        tone: "green",
        icon: WalletCards,
      },
      {
        label: "Tim & Hak Akses",
        href: "/app/pengaturan?bagian=tim",
        tone: "blue",
        icon: ShieldCheck,
      },
      {
        label: "Paket & Langganan",
        href: "/app/pengaturan?bagian=paket",
        tone: "violet",
        icon: Package,
      },
      {
        label: "Integrasi",
        href: "/app/pengaturan?bagian=integrasi",
        tone: "teal",
        icon: Wrench,
      },
    ],
  },
  {
    id: "fitur-lanjutan",
    label: "Fitur Lanjutan",
    icon: Wrench,
    tone: "rose",
    items: [
      {
        label: "Proyek & Survei",
        href:
          "/app/upgrade?fitur=proyek&paket=business",
        tone: "violet",
        icon: Building2,
        badge: "Business",
      },
      {
        label: "Produksi & QC",
        href:
          "/app/upgrade?fitur=produksi&paket=business",
        tone: "blue",
        icon: Wrench,
        badge: "Business",
      },
      {
        label: "Saluran Penjualan",
        href:
          "/app/upgrade?fitur=saluran-penjualan&paket=business",
        tone: "cyan",
        icon: Store,
        badge: "Business",
      },
      {
        label: "Pembelian & Gudang",
        href:
          "/app/upgrade?fitur=operasional&paket=pro",
        tone: "green",
        icon: Package,
        badge: "Pro",
      },
    ],
  },
];

function cleanHref(
  href: string,
): string {
  return href.split("?")[0];
}

function isActiveRoute(
  pathname: string,
  href: string,
): boolean {
  const clean =
    cleanHref(href);

  if (clean === "/app") {
    return pathname === "/app";
  }

  return (
    pathname === clean ||
    pathname.startsWith(
      `${clean}/`,
    )
  );
}

function groupIsActive(
  pathname: string,
  group: NavigationGroup,
): boolean {
  return group.items.some(
    (item) =>
      isActiveRoute(
        pathname,
        item.href,
      ),
  );
}

function NavigationLink({
  item,
  pathname,
}: {
  item: NavigationItem;
  pathname: string;
}) {
  const Icon =
    item.icon;

  const active =
    isActiveRoute(
      pathname,
      item.href,
    );

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
        size={18}
        strokeWidth={1.9}
      />

      <span
        className={
          styles.sidebarLinkLabel
        }
      >
        {item.label}
      </span>

      {item.badge ? (
        <span
          className={
            styles.planBadge
          }
        >
          {item.badge}
        </span>
      ) : null}
    </Link>
  );
}

function AccordionGroup({
  group,
  pathname,
}: {
  group: NavigationGroup;
  pathname: string;
}) {
  const initiallyOpen =
    groupIsActive(
      pathname,
      group,
    );

  const [
    open,
    setOpen,
  ] = useState(
    initiallyOpen ||
      group.id === "master-data" ||
      group.id === "penjualan",
  );

  const Icon =
    group.icon;

  return (
    <section
      className={
        styles.accordionGroup
      }
      data-tone={
        group.tone
      }
    >
      <button
        type="button"
        className={
          open
            ? styles.accordionTriggerOpen
            : styles.accordionTrigger
        }
        aria-expanded={open}
        onClick={() =>
          setOpen(
            (current) =>
              !current,
          )
        }
      >
        <span
          className={
            styles.accordionIcon
          }
        >
          <Icon
            size={18}
            strokeWidth={1.9}
          />
        </span>

        <span>
          {group.label}
        </span>

        <ChevronDown
          size={16}
          className={
            open
              ? styles.chevronOpen
              : styles.chevron
          }
        />
      </button>

      {open ? (
        <div
          className={
            styles.accordionContent
          }
        >
          {group.items.map(
            (item) => (
              <NavigationLink
                key={
                  `${group.id}-${item.label}`
                }
                item={item}
                pathname={
                  pathname
                }
              />
            ),
          )}
        </div>
      ) : null}
    </section>
  );
}

export function TenantShell({
  children,
}: TenantShellProps) {
  const pathname =
    usePathname();

  const router =
    useRouter();

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

  const [
    userName,
    setUserName,
  ] = useState(
    "Pengguna",
  );

  useEffect(() => {
    let cancelled =
      false;

    window.queueMicrotask(
      () => {
        void getAuthContext()
          .then(
            (response) => {
              if (
                cancelled
              ) {
                return;
              }

              const name =
                response.data.user
                  .name?.trim();

              if (name) {
                setUserName(
                  name,
                );
              }
            },
          )
          .catch(() => {
            // Session gate menangani auth.
          });
      },
    );

    return () => {
      cancelled = true;
    };
  }, []);

  async function handleLogout() {
    if (loggingOut) {
      return;
    }

    setLoggingOut(true);

    try {
      await logout();
    } finally {
      router.replace(
        "/login",
      );
    }
  }

  const initial =
    userName
      .trim()
      .charAt(0)
      .toUpperCase() ||
    "U";

  return (
    <div className={styles.shell}>
      <OfflineRouteWarmup />

      <aside className={styles.sidebar}>
        <div
          className={
            styles.sidebarBrand
          }
        >
          <Brand />
        </div>

        <nav
          className={
            styles.sidebarNavigation
          }
          aria-label="Menu SIGNOVA"
        >
          <NavigationLink
            pathname={
              pathname
            }
            item={{
              label: "Beranda",
              href: "/app",
              tone: "cyan",
              icon: Home,
            }}
          />

          {navigationGroups.map(
            (group) => (
              <AccordionGroup
                key={
                  group.id
                }
                group={
                  group
                }
                pathname={
                  pathname
                }
              />
            ),
          )}
        </nav>

        <div
          className={
            styles.sidebarFooter
          }
        >
          <div
            className={
              styles.sidebarSync
            }
          >
            <SyncStatus />
          </div>

          <button
            className={
              styles.sidebarLogout
            }
            type="button"
            disabled={
              loggingOut
            }
            onClick={
              () =>
                void handleLogout()
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
        <header
          className={
            styles.header
          }
        >
          <div
            className={
              styles.mobileBrand
            }
          >
            <Brand />
          </div>

          <div
            className={
              styles.headerContext
            }
          >
            <span
              className={
                styles.headerLabel
              }
            >
              Usaha aktif
            </span>

            <strong
              className={
                styles.headerBusiness
              }
            >
              {activeTenantName}
            </strong>
          </div>

          <div
            className={
              styles.headerActions
            }
          >
            <SyncStatus />

            <ThemeQuickToggle />

            <button
              className={
                styles.iconButton
              }
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

            <Link
              href="/app/pengaturan?bagian=profil"
              className={
                styles.userMenu
              }
              aria-label="Buka profil saya"
            >
              <span
                className={
                  styles.userAvatar
                }
              >
                {initial}
              </span>

              <span
                className={
                  styles.userCopy
                }
              >
                <small>
                  Pengguna
                </small>

                <strong>
                  {userName}
                </strong>
              </span>
            </Link>
          </div>
        </header>

        <main
          className={
            styles.main
          }
        >
          <TenantSessionGate
            onTenantResolved={
              setActiveTenantName
            }
          >
            {children}
          </TenantSessionGate>
        </main>

        <nav
          className={
            styles.bottomNavigation
          }
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
            className={
              styles.bottomPrimary
            }
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
