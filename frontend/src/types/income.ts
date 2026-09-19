export type RecordIncomePayload = {
  cash_account_id: string;
  amount: string;
  occurred_at: string;

  category?:
    | string
    | null;

  description:
    string;

  reference?:
    | string
    | null;
};

export type RecordedIncome = {
  id: string;
  status: string;
  amount: string;

  [key: string]:
    unknown;
};

export type RecordIncomeResponse = {
  success: true;
  data: RecordedIncome;

  meta?:
    Record<
      string,
      unknown
    >;

  message?: string;
};
