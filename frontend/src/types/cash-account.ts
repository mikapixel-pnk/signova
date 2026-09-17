export type CashAccountType =
  | "CASH"
  | "BANK";

export type CashAccountStatus =
  | "ACTIVE"
  | "INACTIVE";

export type CashAccount = {
  id: string;
  name: string;
  type: CashAccountType;

  bank_name:
    | string
    | null;

  account_number:
    | string
    | null;

  account_name:
    | string
    | null;

  currency: string;
  status: CashAccountStatus;

  is_default: boolean;
  accepts_payments: boolean;

  balance:
    | number
    | string;

  created_at:
    | string
    | null;

  updated_at:
    | string
    | null;
};

export type CashAccountListResponse = {
  success: true;

  data:
    CashAccount[];

  meta: {
    current_page?: number;
    per_page?: number;
    total?: number;
    last_page?: number;

    [key: string]:
      unknown;
  };

  message?: string;
};

export type CashAccountResponse = {
  success: true;

  data:
    CashAccount;

  meta:
    Record<
      string,
      unknown
    >;

  message?: string;
};

export type CashAccountPayload = {
  name: string;
  type: CashAccountType;

  bank_name?:
    | string
    | null;

  account_number?:
    | string
    | null;

  account_name?:
    | string
    | null;

  accepts_payments?: boolean;
};
