export type IncomeRegisterGroup =
  | "CUSTOMER_PAYMENT"
  | "MANUAL"
  | "POS"
  | "MARKETPLACE"
  | "OTHER";


export type IncomeRegisterSource = {
  group: IncomeRegisterGroup;
  type: string;
  label: string;
  category: string | null;
  automatic: boolean;
  channel: string | null;
  status: string | null;
};


export type IncomeRegisterCashAccount = {
  id: string;
  name: string;
  type: string;
  bank_name: string | null;
};


export type IncomeRegisterParty = {
  id: string;
  name: string;
};


export type IncomeRegisterDocument = {
  type: string;
  id: string;
  number: string;
};


export type IncomeRegisterItem = {
  id: string;
  occurred_at: string | null;
  amount: string;
  currency: string;

  source: IncomeRegisterSource;

  cash_account:
    IncomeRegisterCashAccount;

  party:
    IncomeRegisterParty | null;

  document:
    IncomeRegisterDocument | null;

  reference: string | null;
  description: string | null;
  reversed: boolean;
};


export type IncomeRegisterMeta = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
};


export type IncomeRegisterResponse = {
  data: IncomeRegisterItem[];
  meta: IncomeRegisterMeta;
};
