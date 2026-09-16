import type {
  ApiMeta,
} from "@/lib/api/types";

import type {
  CatalogItemType,
  CatalogPricingMethod,
} from "@/types/catalog";

export type InvoiceStatus =
  | "DRAFT"
  | "ISSUED"
  | "PARTIALLY_PAID"
  | "PAID"
  | "VOID";

export type InvoiceCustomer = {
  id: string;
  code: string | null;
  name: string;
};

export type InvoiceItem = {
  id: string;

  catalog_item_id:
    | string
    | null;

  item_type:
    CatalogItemType;

  code:
    | string
    | null;

  name: string;

  description:
    | string
    | null;

  quantity:
    number | string;

  unit_code:
    | string
    | null;

  unit_name:
    | string
    | null;

  unit_symbol:
    | string
    | null;

  unit_price:
    number | string;

  discount_amount:
    number | string;

  tax_amount:
    number | string;

  amount:
    number | string;

  sort_order: number;
};

export type InvoiceStatusHistory = {
  id: string;

  from_state:
    | string
    | null;

  to_state: string;

  reason:
    | string
    | null;

  source:
    | string
    | null;

  occurred_at:
    | string
    | null;
};

export type Invoice = {
  id: string;
  invoice_number: string;
  customer_id: string;

  customer?:
    InvoiceCustomer;

  project_id:
    | string
    | null;

  source_quotation_id:
    | string
    | null;

  source_quotation_version_id:
    | string
    | null;

  status: InvoiceStatus | string;
  status_label: string;

  issued_at:
    | string
    | null;

  due_at:
    | string
    | null;

  currency: string;

  subtotal:
    number | string;

  discount_total:
    number | string;

  tax_total:
    number | string;

  total:
    number | string;

  paid_amount:
    number | string;

  outstanding_amount:
    number | string;

  notes:
    | string
    | null;

  items?:
    InvoiceItem[];

  status_history?:
    InvoiceStatusHistory[];

  created_at:
    | string
    | null;

  updated_at:
    | string
    | null;
};

export type InvoiceListResponse = {
  success: true;

  data:
    Invoice[];

  meta:
    ApiMeta & {
      current_page: number;
      per_page: number;
      total: number;
      last_page: number;
    };
};

export type InvoiceResponse = {
  success: true;
  data: Invoice;
  meta: ApiMeta;
  message?: string;
};

export type InvoiceItemPayload = {
  catalog_item_id?:
    | string
    | null;

  unit_id?:
    | string
    | null;

  item_type?:
    | CatalogItemType
    | null;

  code?:
    | string
    | null;

  name?:
    | string
    | null;

  description?:
    | string
    | null;

  quantity?:
    number;

  pricing_method?:
    | CatalogPricingMethod
    | null;

  pricing_config?:
    Record<string, number>
    | null;

  unit_price?:
    | number
    | null;

  discount_amount?:
    number;

  tax_rate?:
    number;

  sort_order?:
    number;
};

export type InvoiceCreatePayload = {
  customer_id: string;

  due_at?:
    | string
    | null;

  notes?:
    | string
    | null;

  items:
    InvoiceItemPayload[];
};

export type InvoiceVoidPayload = {
  reason: string;
};
