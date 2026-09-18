export type PublicInvoiceStatus =
  | "DRAFT"
  | "ISSUED"
  | "PARTIALLY_PAID"
  | "PAID"
  | "OVERDUE"
  | "VOID";

export type PublicInvoiceBranding = {
  business_name:
    | string
    | null;

  address:
    | string
    | null;

  phone:
    | string
    | null;

  email:
    | string
    | null;

  tax_id:
    | string
    | null;

  invoice_footnote:
    | string
    | null;

  logo_data_uri:
    | string
    | null;
};

export type PublicInvoiceItem = {
  name: string;

  description:
    | string
    | null;

  quantity:
    | string
    | number;

  unit_price:
    | string
    | number;

  discount_amount:
    | string
    | number;

  tax_amount:
    | string
    | number;

  amount:
    | string
    | number;
};

export type PublicInvoiceBankAccount = {
  payment_account_token: string;

  name: string;

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

  is_default: boolean;
};

export type PublicInvoice = {
  invoice_number: string;

  status:
    PublicInvoiceStatus;

  issued_at:
    | string
    | null;

  due_at:
    | string
    | null;

  currency: string;

  subtotal:
    | string
    | number;

  discount_total:
    | string
    | number;

  tax_total:
    | string
    | number;

  total:
    | string
    | number;

  paid_amount:
    | string
    | number;

  outstanding_amount:
    | string
    | number;

  notes:
    | string
    | null;

  payment_allowed: boolean;

  branding:
    PublicInvoiceBranding;

  customer: {
    name: string;
  };

  items:
    PublicInvoiceItem[];

  payment_options: {
    bank_transfer_enabled:
      boolean;

    bank_accounts:
      PublicInvoiceBankAccount[];

    static_qr_enabled:
      boolean;

    static_qr_data_uri:
      | string
      | null;

    partial_payment_enabled:
      boolean;
  };
};

export type PublicInvoiceResponse = {
  success: true;

  data:
    PublicInvoice;

  message?:
    string;

  meta?:
    unknown;
};


export type PublicInvoicePaymentResult = {
  status: "PENDING";

  amount:
    | string
    | number;

  currency: string;

  paid_at: string;

  reference:
    | string
    | null;
};


export type PublicInvoicePaymentResponse = {
  success: true;

  data:
    PublicInvoicePaymentResult;

  message?:
    string;

  meta?:
    unknown;
};
