import type {
  ApiMeta,
} from "@/lib/api/types";

export type ProcurementType =
  | "INVENTORY_ITEM"
  | "NON_STOCK_GOOD"
  | "SERVICE";

export type PurchaseRequestStatus =
  | "DRAFT"
  | "SUBMITTED"
  | "APPROVED"
  | "REJECTED"
  | "CANCELLED";

export type PurchaseRequestItem = {
  id: string;
  catalog_item_id: string | null;
  material_id: string | null;
  unit_id: string | null;
  procurement_type: ProcurementType;
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
  material_id?: string | null;
  unit_id?: string | null;
  procurement_type?: ProcurementType | null;
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
  material_id: string | null;
  unit_id: string | null;
  procurement_type: ProcurementType;
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

export type PurchaseOrderItemPayload = {
  source_purchase_request_item_id?: string | null;
  catalog_item_id?: string | null;
  material_id?: string | null;
  unit_id?: string | null;
  procurement_type?: ProcurementType | null;
  item_type?:
    | "PRODUCT"
    | "SERVICE";
  code?: string | null;
  name?: string | null;
  description?: string | null;
  quantity:
    | string
    | number;
  unit_price?:
    | string
    | number;
  discount_amount?:
    | string
    | number;
  tax_amount?:
    | string
    | number;
  sort_order?: number;
};

export type PurchaseOrderPayload = {
  supplier_id: string;
  source_purchase_request_id?: string | null;
  expected_at?: string | null;
  currency?: string;
  notes?: string | null;
  items?: PurchaseOrderItemPayload[];
};

export type UpdatePurchaseOrderPayload = {
  supplier_id?: string;
  expected_at?: string | null;
  currency?: string;
  notes?: string | null;
  items?: PurchaseOrderItemPayload[];
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
