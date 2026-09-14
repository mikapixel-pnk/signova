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
  logout,
} from "@/lib/auth/logout";

const menuItems = [
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
    label: "Pembayaran",
    href: "/app/payments",
    icon: ReceiptText,
  },
  {
    label: "Ringkasan Keuangan",
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
    icon: FileText,
  },
  {
    label: "Pengaturan",
    href: "/app/settings",
    icon: Settings,
  },
];

export default function MoreMenuPage() {
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
    <TenantShell>
      <section
        style={{
          display: "grid",
          gap: 22,
        }}
      >
        <header>
          <p
            style={{
              margin: "0 0 6px",
              color:
                "var(--color-primary)",
              fontSize: 12,
              fontWeight: 700,
            }}
          >
            SIGNOVA
          </p>

          <h1
            style={{
              margin: 0,
              fontSize:
                "clamp(26px, 6vw, 36px)",
            }}
          >
            Menu Lainnya
          </h1>

          <p
            style={{
              margin: "7px 0 0",
              color:
                "var(--color-muted-foreground)",
            }}
          >
            Semua fitur Starter dalam satu tempat.
          </p>
        </header>

        <div
          style={{
            display: "grid",
            gridTemplateColumns:
              "repeat(2, minmax(0, 1fr))",
            gap: 10,
          }}
        >
          {menuItems.map((item) => {
            const Icon = item.icon;

            return (
              <Link
                key={item.href}
                href={item.href}
                style={{
                  display: "flex",
                  minHeight: 92,
                  flexDirection: "column",
                  justifyContent:
                    "space-between",
                  gap: 12,
                  border:
                    "1px solid var(--color-border)",
                  borderRadius: 18,
                  background:
                    "var(--color-card)",
                  padding: 15,
                  color:
                    "var(--color-foreground)",
                  textDecoration: "none",
                }}
              >
                <Icon
                  size={22}
                  strokeWidth={1.8}
                />

                <strong
                  style={{
                    fontSize: 13,
                  }}
                >
                  {item.label}
                </strong>
              </Link>
            );
          })}
        </div>

        <button
          type="button"
          onClick={
            () => void handleLogout()
          }
          disabled={loggingOut}
          style={{
            display: "flex",
            width: "100%",
            alignItems: "center",
            justifyContent: "center",
            gap: 9,
            border:
              "1px solid #e4575740",
            borderRadius: 15,
            background: "#e457570d",
            padding: 14,
            color: "#df5353",
            font: "inherit",
            fontWeight: 700,
          }}
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
