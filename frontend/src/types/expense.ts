export type ExpenseStatus =
  | "DRAFT"
  | "PENDING_APPROVAL"
  | "REJECTED"
  | "POSTED"
  | "VOID";

export type ExpenseCashAccount = {
  id: string;
  name: string;
  type: string;
  status: string;
};

export type ExpenseEvidence = {
  id: string;
  original_name: string;
  mime_type: string;
  size_bytes: number;
};

export type Expense = {
  id: string;

  cash_account:
    | ExpenseCashAccount
    | null;

  has_evidence: boolean;

  evidence:
    | ExpenseEvidence
    | null;

  amount: string;
  currency: string;

  incurred_at:
    | string
    | null;

  category:
    | string
    | null;

  description: string;
  status: ExpenseStatus;

  submitted_at:
    | string
    | null;

  approved_at:
    | string
    | null;

  rejected_at:
    | string
    | null;

  rejection_reason:
    | string
    | null;

  posted_at:
    | string
    | null;

  voided_at:
    | string
    | null;

  void_reason:
    | string
    | null;

  created_at:
    | string
    | null;

  updated_at:
    | string
    | null;
};

export type ExpenseListResponse = {
  success: true;

  data: Expense[];

  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;

    [key: string]:
      unknown;
  };

  message?: string;
};

export type ExpenseResponse = {
  success: true;

  data: Expense;

  meta:
    Record<
      string,
      unknown
    >;

  message?: string;
};

export type RecordExpensePayload = {
  cash_account_id: string;
  amount: string;
  incurred_at: string;

  category?:
    | string
    | null;

  description: string;
};


export type CreateExpensePayload =
  RecordExpensePayload;

export type UpdateExpensePayload =
  Partial<RecordExpensePayload>;
