import type {
  ComponentType,
} from "react";

export type ModuleTone =
  | "blue"
  | "cyan"
  | "teal"
  | "green"
  | "violet"
  | "rose"
  | "amber";

export type ModulePlan =
  | "starter"
  | "business"
  | "pro";

export type ModuleGroupKey =
  | "master-data"
  | "penjualan"
  | "keuangan"
  | "pengaturan"
  | "fitur-lanjutan";

export type ModuleKey =
  | "customers"
  | "catalog"
  | "categories"
  | "units"
  | "quotations"
  | "invoices"
  | "payments"
  | "finance"
  | "cash-bank"
  | "income"
  | "expense"
  | "receivables"
  | "business-settings"
  | "profile-settings"
  | "finance-settings"
  | "team-access"
  | "subscription"
  | "integrations"
  | "projects"
  | "production"
  | "sales-channels"
  | "operations";

export type ModuleIcon =
  ComponentType<{
    size?: number;
    strokeWidth?: number;
  }>;

export type ModuleDefinition = {
  key: ModuleKey;

  group:
    ModuleGroupKey;

  label: string;
  shortLabel?: string;
  eyebrow?: string;

  href: string;

  tone:
    ModuleTone;

  icon:
    ModuleIcon;

  plan:
    ModulePlan;

  capability?: string;

  description: string;

  insight: {
    title: string;
    description: string;
  };

  footer?: {
    title: string;
    description: string;
    actionLabel?: string;
    href?: string;
  };

  highlights?: string[];

  navigation?: {
    desktop?: boolean;
    mobile?: boolean;
    order: number;
  };
};

export type NavigationGroupDefinition = {
  key:
    ModuleGroupKey;

  label: string;

  description: string;

  tone:
    ModuleTone;

  icon:
    ModuleIcon;

  order: number;

  defaultOpen?: boolean;
};
