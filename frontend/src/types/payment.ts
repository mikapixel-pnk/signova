import type {
  Invoice,
} from "@/types/invoice";

export type PaymentMethod =
  | "BANK_TRANSFER"
  | "STATIC_QR";

export type InvoicePaymentHistoryItem = {
  allocation_id: string;
  payment_id: string;

  allocated_amount:
    | number
    | string;

  payment_amount:
    | number
    | string;

  status: string;
  method: PaymentMethod;

  paid_at:
    | string
    | null;

  reference:
    | string
    | null;

  has_evidence: boolean;

  created_at:
    | string
    | null;
};

export type InvoicePaymentsResponse = {
  success: true;

  data:
    InvoicePaymentHistoryItem[];

  meta:
    Record<
      string,
      unknown
    >;
};

export type PaymentCashAccount = {
  id: string;
  name: string;

  type:
    | string
    | null;

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
  status: string;
  is_default: boolean;

  balance:
    | number
    | string;
};

export type PaymentCashAccountsResponse = {
  success: true;

  data:
    PaymentCashAccount[];

  meta:
    Record<
      string,
      unknown
    >;
};

export type RecordInvoicePaymentPayload = {
  cash_account_id: string;

  amount:
    | number
    | string;

  paid_at: string;
  method: PaymentMethod;

  reference?:
    | string
    | null;
};

export type RecordedPayment = {
  id: string;
  customer_id: string;
  cash_account_id: string;

  amount:
    | number
    | string;

  currency: string;
  paid_at: string;
  method: PaymentMethod;
  status: string;

  reference:
    | string
    | null;

  has_evidence: boolean;
};

export type PaymentEvidenceResponse = {
  success: true;

  data: {
    id: string;
    status: string;
    has_evidence: boolean;
  };

  meta:
    Record<
      string,
      unknown
    >;

  message?: string;
};

export type RecordInvoicePaymentResponse = {
  success: true;

  data: {
    allocation: {
      id: string;
      payment_id: string;
      invoice_id: string;

      allocated_amount:
        | number
        | string;
    };

    payment:
      RecordedPayment;

    payment_allocated_amount:
      | number
      | string;

    payment_unallocated_amount:
      | number
      | string;

    invoice:
      Invoice;
  };

  meta:
    Record<
      string,
      unknown
    >;

  message?: string;
};
