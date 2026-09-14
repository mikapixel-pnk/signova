"use client";

import {
  BookOpenText,
  Building2,
  CircleDollarSign,
  FileText,
  HandCoins,
  LogOut,
  Package,
  ReceiptText,
  Settings,
  Users,
  WalletCards,
} from "lucide-react";

import Link from "next/link";

import {
  useRouter,
} from "next/navigation";

import {
  useState,
} from "react";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  ThemeSelector,
} from "@/components/theme/theme-selector";

import {
  logout,
} from "@/lib/auth/logout";

import styles from "./page.module.css";

const menuItems = [
  {
    label: "Pelanggan",
    href: "/app/pelanggan",
    icon: Users,
    tone: "violet",
  },
  {
    label: "Barang & Jasa",
    href: "/app/barang-jasa",
    icon: Package,
    tone: "cyan",
  },
  {
    label: "Penawaran",
    href: "/app/penawaran",
    icon: BookOpenText,
    tone: "violet",
  },
  {
    label: "Pembayaran",
    href: "/app/pembayaran",
    icon: ReceiptText,
    tone: "blue",
  },
  {
    label: "Ringkasan Keuangan",
    href: "/app/keuangan",
    icon: WalletCards,
    tone: "teal",
  },
  {
    label: "Kas & Bank",
    href: "/app/keuangan/kas-bank",
    icon: Building2,
    tone: "green",
  },
  {
    label: "Pemasukan",
    href: "/app/keuangan/pemasukan",
    icon: HandCoins,
    tone: "cyan",
  },
  {
    label: "Pengeluaran",
    href: "/app/keuangan/pengeluaran",
    icon: CircleDollarSign,
    tone: "rose",
  },
  {
    label: "Piutang",
    href: "/app/keuangan/piutang",
    icon: FileText,
    tone: "blue",
  },
  {
    label: "Pengaturan",
    href: "/app/pengaturan",
    icon: Settings,
    tone: "violet",
  },
] as const;

export default function MoreMenuPage() {
  const router =
    useRouter();

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
      router.replace(
        "/login",
      );
    }
  }

  return (
    <TenantShell>
      <section
        className={
          styles.page
        }
      >
        <header>
          <p
            className={
              styles.eyebrow
            }
          >
            SIGNOVA
          </p>

          <h1
            className={
              styles.title
            }
          >
            Menu Lainnya
          </h1>

          <p
            className={
              styles.description
            }
          >
            Semua fitur Starter dalam satu tempat.
          </p>
        </header>

        <div
          className={
            styles.menuGrid
          }
        >
          {menuItems.map(
            (item) => {
              const Icon =
                item.icon;

              return (
                <Link
                  key={
                    item.href
                  }
                  href={
                    item.href
                  }
                  data-tone={
                    item.tone
                  }
                  className={
                    styles.menuCard
                  }
                >
                  <span
                    className={
                      styles.iconBox
                    }
                  >
                    <Icon
                      size={21}
                      strokeWidth={1.9}
                    />
                  </span>

                  <strong>
                    {item.label}
                  </strong>
                </Link>
              );
            },
          )}
        </div>

        <section
          className={
            styles.themeSection
          }
        >
          <div>
            <h2
              className={
                styles.sectionTitle
              }
            >
              Tampilan
            </h2>

            <p
              className={
                styles.sectionNote
              }
            >
              Pilih tema SIGNOVA di perangkat ini.
            </p>
          </div>

          <ThemeSelector />
        </section>

        <button
          type="button"
          className={
            styles.logout
          }
          onClick={
            () =>
              void handleLogout()
          }
          disabled={
            loggingOut
          }
        >
          <LogOut size={19} />

          {loggingOut
            ? "Sedang keluar..."
            : "Keluar dari SIGNOVA"}
        </button>
      </section>
    </TenantShell>
  );
}
