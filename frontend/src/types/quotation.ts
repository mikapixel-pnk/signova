import type {
  ApiMeta,
} from "@/lib/api/types";

import type {
  CatalogItemType,
  CatalogPricingMethod,
} from "@/types/catalog";

export type QuotationStatus =
  | "DRAFT"
  | "SENT"
  | "VIEWED"
  | "APPROVED"
  | "REJECTED"
  | "EXPIRED"
  | "CANCELLED";

export type QuotationCustomer = {
  id: string;
  code: string | null;
  name: string;
};

export type QuotationItem = {
  id: string;

  catalog_item_id:
    | string
    | null;

  unit_id:
    | string
    | null;

  item_type:
    CatalogItemType;

  item_type_label:
    string;

  code:
    | string
    | null;

  name:
    string;

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

  pricing_method:
    CatalogPricingMethod;

  pricing_method_label:
    string;

  pricing_config:
    Record<
      string,
      unknown
    > | null;

  unit_price:
    number | string;

  discount_amount:
    number | string;

  tax_amount:
    number | string;

  amount:
    number | string;

  sort_order:
    number;
};

export type QuotationVersion = {
  id: string;

  revision_no:
    number;

  subtotal:
    number | string;

  discount_total:
    number | string;

  tax_total:
    number | string;

  total:
    number | string;

  currency:
    string;

  terms:
    | string
    | null;

  notes:
    | string
    | null;

  items:
    QuotationItem[];

  created_at:
    | string
    | null;
};

export type Quotation = {
  id: string;

  quotation_number:
    string;

  customer_id:
    string;

  customer?:
    QuotationCustomer;

  status:
    QuotationStatus;

  status_label:
    string;

  valid_until:
    | string
    | null;

  owner_user_id:
    | string
    | null;

  source:
    | string
    | null;

  current_version?:
    QuotationVersion;

  created_at:
    | string
    | null;

  updated_at:
    | string
    | null;
};

export type QuotationListResponse = {
  success: true;

  data:
    Quotation[];

  meta:
    ApiMeta & {
      current_page:
        number;

      per_page:
        number;

      total:
        number;

      last_page:
        number;
    };
};

export type QuotationResponse = {
  success: true;

  data:
    Quotation;

  meta:
    ApiMeta;

  message?:
    string;
};

export type QuotationItemPayload = {
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
    Record<
      string,
      number
    > | null;

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

export type QuotationCreatePayload = {
  customer_id:
    string;

  valid_until?:
    | string
    | null;

  currency?:
    string;

  terms?:
    | string
    | null;

  notes?:
    | string
    | null;

  items:
    QuotationItemPayload[];
};

export type QuotationPublicLink = {
  quotation_id: string;
  quotation_version_id: string;
  public_url: string;
  expires_at: string | null;
};

export type QuotationPublicLinkResponse = {
  success: true;
  data: QuotationPublicLink;
  meta: ApiMeta;
  message?: string;
};

export type QuotationManualDecisionMethod =
  | "SIGNATURE"
  | "WHATSAPP"
  | "EMAIL"
  | "PHONE"
  | "MEETING"
  | "OTHER";

export type QuotationManualDecisionPayload = {
  decision:
    | "APPROVE"
    | "REJECT";

  method:
    QuotationManualDecisionMethod;

  reason?:
    string | null;

  note?:
    string | null;

  decided_at?:
    string | null;
};

export type QuotationInvoiceResponse = {
  success: true;
  data: {
    id: string;
    [key: string]: unknown;
  };
  meta: ApiMeta;
  message?: string;
};

export type QuotationHeaderUpdatePayload = {
  customer_id?:
    string;

  valid_until?:
    | string
    | null;
};

export type QuotationRevisionPayload = {
  currency?:
    string;

  terms?:
    | string
    | null;

  notes?:
    | string
    | null;

  items:
    QuotationItemPayload[];
};
