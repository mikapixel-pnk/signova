import {
  Boxes,
  Database,
  ReceiptText,
  Settings,
  Sparkles,
  WalletCards,
} from "lucide-react";

import type {
  NavigationGroupDefinition,
} from "./module-types";

export const navigationGroups:
  NavigationGroupDefinition[] = [
  {
    key: "master-data",
    label: "Master Data",
    description:
      "Data utama yang digunakan berulang dalam transaksi.",
    tone: "violet",
    icon: Database,
    order: 10,
    defaultOpen: true,
  },
  {
    key: "penjualan",
    label: "Penjualan",
    description:
      "Kelola proses penjualan dari penawaran hingga pembayaran.",
    tone: "blue",
    icon: ReceiptText,
    order: 20,
    defaultOpen: true,
  },
  {
    key: "operasional",
    label: "Operasional",
    description:
      "Kelola pembelian, penerimaan, stok, dan gudang usaha.",
    tone: "amber",
    icon: Boxes,
    order: 25,
    defaultOpen: true,
  },
  {
    key: "keuangan",
    label: "Keuangan",
    description:
      "Pantau arus uang dan kondisi keuangan usaha.",
    tone: "green",
    icon: WalletCards,
    order: 30,
  },
  {
    key: "pengaturan",
    label: "Pengaturan",
    description:
      "Atur bisnis, akun, keuangan, tim, dan integrasi.",
    tone: "violet",
    icon: Settings,
    order: 40,
  },
  {
    key: "fitur-lanjutan",
    label: "Fitur Lanjutan",
    description:
      "Kemampuan tambahan untuk bisnis yang berkembang.",
    tone: "rose",
    icon: Sparkles,
    order: 50,
  },
];
