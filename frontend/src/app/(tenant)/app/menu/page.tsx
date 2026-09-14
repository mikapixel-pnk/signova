"use client";

import {
  Banknote,
  BookOpenText,
  Boxes,
  Building2,
  CircleDollarSign,
  CreditCard,
  Database,
  FileText,
  HandCoins,
  LogOut,
  Package,
  ReceiptText,
  Ruler,
  Settings,
  ShieldCheck,
  Sparkles,
  Store,
  Tags,
  UserRound,
  Users,
  WalletCards,
  Wrench,
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

type Tone =
  | "blue"
  | "cyan"
  | "teal"
  | "green"
  | "violet"
  | "rose"
  | "amber";

type MenuItem = {
  label: string;
  href: string;
  tone: Tone;
  badge?: "Business" | "Pro";
  icon: React.ComponentType<{
    size?: number;
    strokeWidth?: number;
  }>;
};

type MenuSection = {
  id: string;
  title: string;
  description: string;
  tone: Tone;
  icon: React.ComponentType<{
    size?: number;
    strokeWidth?: number;
  }>;
  items: MenuItem[];
};

const sections: MenuSection[] = [
  {
    id: "master-data",
    title: "Master Data",
    description:
      "Kelola data utama yang dipakai berulang dalam transaksi.",
    tone: "blue",
    icon: Database,
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
        tone: "blue",
        icon: Package,
      },
      {
        label: "Satuan",
        href:
          "/app/barang-jasa?bagian=satuan",
        tone: "teal",
        icon: Ruler,
      },
      {
        label: "Kategori",
        href:
          "/app/barang-jasa?bagian=kategori",
        tone: "amber",
        icon: Tags,
      },
    ],
  },
  {
    id: "penjualan",
    title: "Penjualan",
    description:
      "Kelola proses dari penawaran sampai pembayaran pelanggan.",
    tone: "teal",
    icon: Store,
    items: [
      {
        label: "Penawaran",
        href: "/app/penawaran",
        tone: "teal",
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
        tone: "rose",
        icon: CreditCard,
      },
    ],
  },
  {
    id: "keuangan",
    title: "Keuangan",
    description:
      "Pantau arus uang dan kondisi keuangan usaha Anda.",
    tone: "cyan",
    icon: WalletCards,
    items: [
      {
        label: "Ringkasan",
        href: "/app/keuangan",
        tone: "violet",
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
        tone: "teal",
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
    title: "Pengaturan",
    description:
      "Sesuaikan SIGNOVA dengan kebutuhan usaha dan tim.",
    tone: "violet",
    icon: Settings,
    items: [
      {
        label: "Pengaturan Bisnis",
        href:
          "/app/pengaturan?bagian=bisnis",
        tone: "blue",
        icon: Store,
      },
      {
        label: "Profil Saya",
        href:
          "/app/pengaturan?bagian=profil",
        tone: "violet",
        icon: UserRound,
      },
      {
        label: "Pengaturan Keuangan",
        href:
          "/app/pengaturan?bagian=keuangan",
        tone: "green",
        icon: Banknote,
      },
      {
        label: "Tim & Hak Akses",
        href:
          "/app/pengaturan?bagian=tim",
        tone: "cyan",
        icon: ShieldCheck,
      },
    ],
  },
];

const advancedItems: MenuItem[] = [
  {
    label: "Proyek & Survei",
    href:
      "/app/pengaturan?bagian=paket&fitur=proyek",
    tone: "violet",
    badge: "Business",
    icon: Building2,
  },
  {
    label: "Produksi & QC",
    href:
      "/app/pengaturan?bagian=paket&fitur=produksi",
    tone: "blue",
    badge: "Business",
    icon: Wrench,
  },
  {
    label: "Saluran Penjualan",
    href:
      "/app/pengaturan?bagian=paket&fitur=saluran-penjualan",
    tone: "cyan",
    badge: "Business",
    icon: Boxes,
  },
  {
    label: "Pembelian & Gudang",
    href:
      "/app/pengaturan?bagian=paket&fitur=operasional",
    tone: "green",
    badge: "Pro",
    icon: Package,
  },
];

function MenuTile({
  item,
}: {
  item: MenuItem;
}) {
  const Icon =
    item.icon;

  return (
    <Link
      href={item.href}
      data-tone={item.tone}
      className={
        styles.menuTile
      }
    >
      <span
        className={
          styles.tileIcon
        }
      >
        <Icon
          size={22}
          strokeWidth={1.9}
        />
      </span>

      <span
        className={
          styles.tileLabel
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
        <header
          className={
            styles.hero
          }
        >
          <div
            className={
              styles.heroCopy
            }
          >
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
              Semua kebutuhan usaha
              dalam satu tempat.
            </p>
          </div>

          <aside
            className={
              styles.heroInsight
            }
          >
            <Sparkles
              size={20}
              strokeWidth={1.8}
            />

            <strong>
              Kelola bisnis lebih mudah
              dengan SIGNOVA
            </strong>

            <p>
              Pilih menu sesuai pekerjaan
              yang ingin Anda selesaikan.
            </p>
          </aside>
        </header>

        <div
          className={
            styles.sections
          }
        >
          {sections.map(
            (section) => {
              const SectionIcon =
                section.icon;

              return (
                <section
                  key={
                    section.id
                  }
                  className={
                    styles.menuSection
                  }
                  data-tone={
                    section.tone
                  }
                >
                  <header
                    className={
                      styles.sectionHeader
                    }
                  >
                    <span
                      className={
                        styles.sectionIcon
                      }
                    >
                      <SectionIcon
                        size={20}
                        strokeWidth={1.9}
                      />
                    </span>

                    <div
                      className={
                        styles.sectionCopy
                      }
                    >
                      <h2>
                        {section.title}
                      </h2>

                      <p>
                        {
                          section.description
                        }
                      </p>
                    </div>

                    <span
                      className={
                        styles.menuCount
                      }
                    >
                      {
                        section.items
                          .length
                      }{" "}
                      menu
                    </span>
                  </header>

                  <div
                    className={
                      styles.tileGrid
                    }
                  >
                    {section.items.map(
                      (item) => (
                        <MenuTile
                          key={
                            `${section.id}-${item.label}`
                          }
                          item={
                            item
                          }
                        />
                      ),
                    )}
                  </div>
                </section>
              );
            },
          )}
        </div>

        <section
          className={
            styles.advancedSection
          }
        >
          <header
            className={
              styles.sectionHeader
            }
          >
            <span
              className={
                styles.advancedIcon
              }
            >
              <Sparkles
                size={20}
                strokeWidth={1.9}
              />
            </span>

            <div
              className={
                styles.sectionCopy
              }
            >
              <h2>
                Fitur Lanjutan
              </h2>

              <p>
                Kembangkan SIGNOVA saat
                kebutuhan usaha Anda
                bertambah.
              </p>
            </div>
          </header>

          <div
            className={
              styles.tileGrid
            }
          >
            {advancedItems.map(
              (item) => (
                <MenuTile
                  key={
                    item.label
                  }
                  item={item}
                />
              ),
            )}
          </div>
        </section>

        <section
          className={
            styles.themeSection
          }
        >
          <div
            className={
              styles.themeHeading
            }
          >
            <span
              className={
                styles.themeIcon
              }
            >
              <Settings
                size={20}
                strokeWidth={1.9}
              />
            </span>

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
                Pilih tema SIGNOVA di
                perangkat ini.
              </p>
            </div>
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
          <LogOut
            size={19}
          />

          {loggingOut
            ? "Sedang keluar..."
            : "Keluar dari SIGNOVA"}
        </button>
      </section>
    </TenantShell>
  );
}
