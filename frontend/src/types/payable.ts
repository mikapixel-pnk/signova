import type {
  ApiMeta,
} from "@/lib/api/types";

export type SupplierBillStatus =
  | "DRAFT"
  | "POSTED"
  | "PARTIALLY_PAID"
  | "PAID"
  | "CANCELLED";

export type SupplierBill = {
  id: string;
  bill_number: string;
  supplier_invoice_number: string | null;

  supplier: {
    id: string;
    name: string | null;
  };

  purchase_order: {
    id: string | null;
    order_number: string | null;
  };

  goods_receipt: {
    id: string | null;
    receipt_number: string | null;
  };

  status: SupplierBillStatus;
  status_label: string;
  currency: "IDR";
  bill_date: string;
  due_date: string | null;
  subtotal: string;
  discount_total: string;
  tax_total: string;
  total: string;
  paid_amount: string;
  outstanding_amount: string;
  notes: string | null;
  cancellation_reason: string | null;

  workflow: {
    next_action:
      | "POST"
      | "PAY"
      | null;
  };
};

export type SupplierPaymentStatus =
  | "DRAFT"
  | "POSTED"
  | "REVERSED";

export type SupplierPaymentAllocation = {
  id: string;
  supplier_bill_id: string;
  bill_number: string | null;
  bill_status: SupplierBillStatus | null;
  amount: string;
};

export type SupplierPayment = {
  id: string;
  payment_number: string;

  supplier: {
    id: string;
    name: string | null;
  };

  cash_account: {
    id: string;
    name: string | null;
    type: "CASH" | "BANK" | null;
  };

  status: SupplierPaymentStatus;
  status_label: string;
  currency: "IDR";
  amount: string;
  paid_at: string | null;
  reference: string | null;
  notes: string | null;
  cash_transaction_id: string | null;
  reversal_cash_transaction_id: string | null;
  reversal_reason: string | null;
  allocations: SupplierPaymentAllocation[];

  workflow: {
    next_action:
      | "POST"
      | "REVERSE"
      | null;
  };
};

type PaginationMeta =
  ApiMeta & {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };

export type SupplierBillListResponse = {
  success: true;
  data: SupplierBill[];
  meta: PaginationMeta;
};

export type SupplierBillResponse = {
  success: true;
  data: SupplierBill;
  meta: ApiMeta;
  message?: string;
};

export type SupplierPaymentListResponse = {
  success: true;
  data: SupplierPayment[];
  meta: PaginationMeta;
};

export type SupplierPaymentResponse = {
  success: true;
  data: SupplierPayment;
  meta: ApiMeta;
  message?: string;
};
