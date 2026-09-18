import type {
  IncomeRegisterGroup,
} from "@/types/income-register";


export const INCOME_GROUP_LABELS:
Record<
  IncomeRegisterGroup,
  string
> = {
  CUSTOMER_PAYMENT:
    "Tagihan",

  MANUAL:
    "Lainnya",

  POS:
    "POS",

  MARKETPLACE:
    "Marketplace",

  OTHER:
    "Sumber Lain",
};


export function incomeGroupLabel(
  group: IncomeRegisterGroup,
): string {
  return INCOME_GROUP_LABELS[
    group
  ];
}
