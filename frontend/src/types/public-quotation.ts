export type PublicQuotationStatus =
  | "SENT"
  | "VIEWED"
  | "APPROVED"
  | "REJECTED"
  | "CANCELLED"
  | "EXPIRED";

export type PublicQuotationItem = {
  item_type:
    | "PRODUCT"
    | "SERVICE"
    | null;

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
};

export type PublicQuotationVersion = {
  revision_no:
    number;

  currency:
    string;

  subtotal:
    number | string;

  discount_total:
    number | string;

  tax_total:
    number | string;

  total:
    number | string;

  terms:
    | string
    | null;

  notes:
    | string
    | null;

  items:
    PublicQuotationItem[];
};

export type PublicQuotation = {
  quotation_number:
    string;

  status:
    PublicQuotationStatus;

  valid_until:
    | string
    | null;

  customer: {
    name:
      string;
  };

  version:
    PublicQuotationVersion;
};

export type PublicQuotationResponse = {
  success:
    true;

  data:
    PublicQuotation;

  message?:
    string;

  meta?:
    unknown;
};
