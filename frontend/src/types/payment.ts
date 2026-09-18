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


export type PaymentStatus =
  | "PENDING"
  | "VERIFIED"
  | "REJECTED"
  | "REVERSED";


export type PaymentCenterInvoice = {
  id: string;
  invoice_number: string;
  status: string;
  currency: string;

  total:
    | number
    | string;

  paid_amount:
    | number
    | string;

  outstanding_amount:
    | number
    | string;
};


export type PaymentCenterCustomer = {
  id: string;
  code: string;
  name: string;
};


export type PaymentCenterCashAccount = {
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
};


export type PaymentCenterEvidence = {
  id: string;
  original_name: string;
  mime_type: string;
  size_bytes: number;
};


export type PaymentCenterItem = {
  id: string;

  customer_id:
    | string
    | null;

  customer:
    | PaymentCenterCustomer
    | null;

  intended_invoice_id:
    | string
    | null;

  intended_invoice:
    | PaymentCenterInvoice
    | null;

  cash_account_id:
    | string
    | null;

  cash_account:
    | PaymentCenterCashAccount
    | null;

  amount:
    | number
    | string;

  currency: string;

  paid_at:
    | string
    | null;

  method:
    PaymentMethod;

  status:
    PaymentStatus;

  reference:
    | string
    | null;

  has_evidence: boolean;

  evidence:
    | PaymentCenterEvidence
    | null;

  verified_at:
    | string
    | null;

  rejected_at:
    | string
    | null;

  rejection_reason:
    | string
    | null;

  created_at:
    | string
    | null;

  updated_at:
    | string
    | null;
};


export type PaymentCenterListResponse = {
  success: true;

  data:
    PaymentCenterItem[];

  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };

  message?: string;
};


export type PaymentCenterDetailResponse = {
  success: true;

  data:
    PaymentCenterItem;

  meta:
    Record<
      string,
      unknown
    >;

  message?: string;
};


export type RejectPaymentResponse =
  PaymentCenterDetailResponse;
