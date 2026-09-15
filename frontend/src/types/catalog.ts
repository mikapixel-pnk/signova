import type {
  ApiMeta,
} from "@/lib/api/types";

export type CatalogStatus =
  | "ACTIVE"
  | "INACTIVE";

export type CatalogItemType =
  | "PRODUCT"
  | "SERVICE";

export type CatalogPricingMethod =
  | "STANDARD"
  | "AREA"
  | "LENGTH"
  | "VOLUME"
  | "TIME"
  | "PACKAGE"
  | "MANUAL";

export type CatalogItem = {
  id: string;

  category_id:
    | string
    | null;

  unit_id:
    | string
    | null;

  type:
    CatalogItemType;

  type_label:
    string;

  code:
    | string
    | null;

  name:
    string;

  description:
    | string
    | null;

  pricing_method:
    CatalogPricingMethod;

  pricing_method_label:
    string;

  base_price:
    number | string;

  currency:
    string;

  pricing_config:
    Record<
      string,
      unknown
    > | null;

  status:
    CatalogStatus;

  status_label:
    string;

  created_at:
    | string
    | null;

  updated_at:
    | string
    | null;
};

export type CatalogCategory = {
  id: string;

  code:
    | string
    | null;

  name:
    string;

  description:
    | string
    | null;

  status:
    CatalogStatus;

  status_label:
    string;
};

export type CatalogUnit = {
  id: string;

  code:
    string;

  name:
    string;

  symbol:
    | string
    | null;

  unit_type:
    string;

  unit_type_label:
    string;

  decimal_precision:
    number;

  status:
    CatalogStatus;

  status_label:
    string;
};

export type CatalogItemListResponse = {
  success: true;

  data:
    CatalogItem[];

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

export type CatalogItemResponse = {
  success: true;

  data:
    CatalogItem;

  meta:
    ApiMeta;

  message?:
    string;
};

export type CatalogCategoryListResponse = {
  success: true;

  data:
    CatalogCategory[];

  meta:
    ApiMeta;
};

export type CatalogCategoryResponse = {
  success: true;

  data:
    CatalogCategory;

  meta:
    ApiMeta;

  message?:
    string;
};

export type CatalogUnitListResponse = {
  success: true;

  data:
    CatalogUnit[];

  meta:
    ApiMeta;
};

export type CatalogItemCreatePayload = {
  category_id?:
    string | null;

  unit_id?:
    string | null;

  type:
    CatalogItemType;

  code?:
    string | null;

  name:
    string;

  description?:
    string | null;

  pricing_method?:
    CatalogPricingMethod;

  base_price?:
    number;

  currency?:
    string;

  pricing_config?:
    Record<
      string,
      unknown
    > | null;
};

export type CatalogItemUpdatePayload =
  Partial<
    CatalogItemCreatePayload
  > & {
    status?:
      CatalogStatus;
  };

export type CatalogCategoryCreatePayload = {
  code?:
    string | null;

  name:
    string;

  description?:
    string | null;
};

export type CatalogCategoryUpdatePayload =
  Partial<
    CatalogCategoryCreatePayload
  > & {
    status?:
      CatalogStatus;
  };


export type CatalogUnitCreatePayload = {
  code: string;

  name: string;

  symbol?:
    string | null;

  unit_type?:
    CatalogUnitType;

  decimal_precision?:
    number;
};

export type CatalogUnitUpdatePayload =
  Partial<
    CatalogUnitCreatePayload
  > & {
    status?:
      CatalogStatus;
  };

export type CatalogUnitType =
  | "COUNT"
  | "LENGTH"
  | "AREA"
  | "VOLUME"
  | "TIME"
  | "PACKAGE"
  | "OTHER";

export type CatalogUnitResponse = {
  success: true;

  data:
    CatalogUnit;

  meta:
    ApiMeta;

  message?:
    string;
};
