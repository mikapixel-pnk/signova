import {
  CircleDollarSign,
  FilePlus2,
  HandCoins,
  PackagePlus,
  UserPlus,
} from "lucide-react";

import type {
  ComponentType,
} from "react";

import type {
  ModuleKey,
  ModuleTone,
} from "@/config/module-types";

export type ActionKey =
  | "create-invoice"
  | "create-customer"
  | "create-catalog-item"
  | "record-income"
  | "record-expense";

export type ActionDefinition = {
  key: ActionKey;

  label: string;
  description: string;

  href: string;

  tone: ModuleTone;

  icon:
    ComponentType<{
      size?: number;
      strokeWidth?: number;
    }>;

  moduleKey?:
    ModuleKey;

  order: number;
};

export const actions:
  Record<
    ActionKey,
    ActionDefinition
  > = {
  "create-invoice": {
    key: "create-invoice",
    label: "Buat Tagihan",
    description:
      "Buat tagihan baru untuk pelanggan.",
    href: "/app/tagihan",
    tone: "blue",
    icon: FilePlus2,
    moduleKey: "invoices",
    order: 10,
  },

  "create-customer": {
    key: "create-customer",
    label: "Tambah Pelanggan",
    description:
      "Simpan pelanggan baru.",
    href: "/app/pelanggan/tambah",
    tone: "violet",
    icon: UserPlus,
    moduleKey: "customers",
    order: 20,
  },

  "create-catalog-item": {
    key: "create-catalog-item",
    label: "Tambah Barang & Jasa",
    description:
      "Tambah item katalog usaha.",
    href: "/app/barang-jasa",
    tone: "cyan",
    icon: PackagePlus,
    moduleKey: "catalog",
    order: 30,
  },

  "record-income": {
    key: "record-income",
    label: "Catat Pemasukan",
    description:
      "Catat pemasukan usaha.",
    href:
      "/app/keuangan/pemasukan",
    tone: "green",
    icon: HandCoins,
    moduleKey: "income",
    order: 40,
  },

  "record-expense": {
    key: "record-expense",
    label: "Catat Pengeluaran",
    description:
      "Catat biaya atau pengeluaran usaha.",
    href:
      "/app/keuangan/pengeluaran",
    tone: "rose",
    icon: CircleDollarSign,
    moduleKey: "expense",
    order: 50,
  },
};
