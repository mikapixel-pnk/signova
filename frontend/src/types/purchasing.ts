import type {
  ApiMeta,
} from "@/lib/api/types";

export type PurchaseRequestStatus =
  | "DRAFT"
  | "SUBMITTED"
  | "APPROVED"
  | "REJECTED"
  | "CANCELLED";

export type PurchaseRequestItem = {
  id: string;
  catalog_item_id: string | null;
  unit_id: string | null;
  item_type: "PRODUCT" | "SERVICE";
  code: string | null;
  name: string;
  description: string | null;
  quantity: string;
  unit_code: string | null;
  unit_name: string | null;
  unit_symbol: string | null;
  estimated_unit_price: string;
  amount: string;
  sort_order: number;
};

export type PurchaseRequest = {
  id: string;
  request_number: string;
  status: PurchaseRequestStatus;
  needed_at: string | null;
  currency: "IDR";
  estimated_total: string;
  notes: string | null;
  submitted_at: string | null;
  approved_at: string | null;
  rejected_at: string | null;
  rejection_reason: string | null;
  cancelled_at: string | null;
  cancellation_reason: string | null;
  items?: PurchaseRequestItem[];
};

export type PurchaseRequestItemPayload = {
  catalog_item_id?: string | null;
  unit_id?: string | null;
  item_type?:
    | "PRODUCT"
    | "SERVICE";
  code?: string | null;
  name?: string | null;
  description?: string | null;
  quantity:
    | string
    | number;
  estimated_unit_price?:
    | string
    | number;
  sort_order?: number;
};

export type PurchaseRequestPayload = {
  needed_at?: string | null;
  currency?: string;
  notes?: string | null;
  items: PurchaseRequestItemPayload[];
};

export type PurchaseOrderStatus =
  | "DRAFT"
  | "ISSUED"
  | "PARTIALLY_RECEIVED"
  | "RECEIVED"
  | "CANCELLED";

export type PurchaseOrderItem = {
  id: string;
  source_purchase_request_item_id: string | null;
  catalog_item_id: string | null;
  unit_id: string | null;
  item_type: "PRODUCT" | "SERVICE";
  code: string | null;
  name: string;
  description: string | null;
  quantity: string;
  unit_code: string | null;
  unit_name: string | null;
  unit_symbol: string | null;
  unit_price: string;
  discount_amount: string;
  tax_amount: string;
  amount: string;
  sort_order: number;
};

export type PurchaseOrder = {
  id: string;
  order_number: string;
  supplier_id: string;

  supplier?: {
    id: string;
    code: string | null;
    name: string;
  };

  source_purchase_request_id: string | null;

  source_purchase_request?: {
    id: string;
    request_number: string;
    status: PurchaseRequestStatus;
  } | null;

  status: PurchaseOrderStatus;
  currency: "IDR";
  expected_at: string | null;
  subtotal: string;
  discount_total: string;
  tax_total: string;
  total: string;
  notes: string | null;
  issued_at: string | null;
  cancelled_at: string | null;
  cancellation_reason: string | null;
  items?: PurchaseOrderItem[];
};

type PaginationMeta =
  ApiMeta & {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };

export type PurchaseRequestListResponse = {
  success: true;
  data: PurchaseRequest[];
  meta: PaginationMeta;
};

export type PurchaseRequestResponse = {
  success: true;
  data: PurchaseRequest;
  meta: ApiMeta;
  message?: string;
};

export type PurchaseOrderListResponse = {
  success: true;
  data: PurchaseOrder[];
  meta: PaginationMeta;
};

export type PurchaseOrderResponse = {
  success: true;
  data: PurchaseOrder;
  meta: ApiMeta;
  message?: string;
};
