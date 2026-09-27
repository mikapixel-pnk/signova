import type {
  ApiMeta,
} from "@/lib/api/types";

import type {
  ProcurementType,
} from "@/types/purchasing";

export type InventoryStatus =
  | "ACTIVE"
  | "INACTIVE";

export type MaterialInventoryType =
  | "RAW_MATERIAL"
  | "COMPONENT"
  | "CONSUMABLE"
  | "RESALE"
  | "FINISHED_GOOD";

export type InventoryStockTracking =
  | "TRACKED"
  | "NOT_TRACKED";

export type GoodsReceiptStatus =
  | "DRAFT"
  | "POSTED"
  | "REVERSED";

export type GoodsReceiptItem = {
  id: string;
  purchase_order_item_id: string;
  material_id: string | null;
  material_name: string | null;
  procurement_type: ProcurementType | null;
  item_type: "PRODUCT" | "SERVICE" | null;
  code: string | null;
  name: string | null;
  ordered_quantity: string | null;
  quantity_received: string;
  unit_symbol: string | null;
};

export type GoodsReceipt = {
  id: string;
  receipt_number: string;
  purchase_order_id: string;
  warehouse_id: string;
  warehouse_name: string | null;
  status: GoodsReceiptStatus;
  status_label: string;
  received_at: string | null;
  notes: string | null;
  reversal_reason: string | null;
  items?: GoodsReceiptItem[];

  workflow: {
    next_action:
      | "POST"
      | "REVERSE"
      | null;
  };
};

export type InventoryCategorySummary = {
  id: string;
  code: string;
  name: string;
  status: InventoryStatus;
};

export type InventoryCategory = {
  id: string;
  code: string;
  name: string;
  description: string | null;
  status: InventoryStatus;
  status_label: string;
};

export type Warehouse = {
  id: string;
  name: string;
  location: string | null;
  status: InventoryStatus;
  status_label: string;
};

export type Material = {
  id: string;
  code: string | null;
  name: string;
  unit_id: string | null;

  category_id: string | null;

  /*
   * Compatibility label dari API v1.
   * Source of truth baru tetap category_id.
   */
  category: string | null;

  inventory_category:
    | InventoryCategorySummary
    | null;

  inventory_type:
    MaterialInventoryType;

  stock_tracking:
    InventoryStockTracking;

  minimum_stock:
    | string
    | null;

  reorder_point:
    | string
    | null;

  maximum_stock:
    | string
    | null;

  description:
    | string
    | null;

  status:
    InventoryStatus;

  status_label:
    string;
};

type PaginationMeta =
  ApiMeta & {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };

export type GoodsReceiptListResponse = {
  success: true;
  data: GoodsReceipt[];
  meta: PaginationMeta;
};

export type GoodsReceiptResponse = {
  success: true;
  data: GoodsReceipt;
  meta: ApiMeta;
  message?: string;
};

export type InventoryCategoryListResponse = {
  success: true;
  data: InventoryCategory[];
  meta: ApiMeta;
};

export type InventoryCategoryResponse = {
  success: true;
  data: InventoryCategory;
  meta: ApiMeta;
  message?: string;
};

export type WarehouseListResponse = {
  success: true;
  data: Warehouse[];
  meta: ApiMeta;
};

export type WarehouseResponse = {
  success: true;
  data: Warehouse;
  meta: ApiMeta;
  message?: string;
};

export type MaterialListResponse = {
  success: true;
  data: Material[];
  meta: ApiMeta;
};

export type MaterialResponse = {
  success: true;
  data: Material;
  meta: ApiMeta;
  message?: string;
};

export type InventoryCategoryCreatePayload = {
  code: string;
  name: string;
  description?: string | null;
};

export type InventoryCategoryUpdatePayload =
  Partial<
    InventoryCategoryCreatePayload
  > & {
    status?: InventoryStatus;
  };

export type WarehouseCreatePayload = {
  name: string;
  location?: string | null;
};

export type WarehouseUpdatePayload =
  Partial<
    WarehouseCreatePayload
  > & {
    status?: InventoryStatus;
  };

export type MaterialCreatePayload = {
  code: string;
  name: string;

  unit_id?:
    | string
    | null;

  category_id?:
    | string
    | null;

  inventory_type?:
    MaterialInventoryType;

  stock_tracking?:
    InventoryStockTracking;

  minimum_stock?:
    | string
    | null;

  reorder_point?:
    | string
    | null;

  maximum_stock?:
    | string
    | null;

  description?:
    | string
    | null;
};

export type MaterialUpdatePayload =
  Partial<
    MaterialCreatePayload
  > & {
    status?: InventoryStatus;
  };


export type StockBalance = {
  material_id: string;

  code:
    | string
    | null;

  name: string;

  unit_id:
    | string
    | null;

  category_id:
    | string
    | null;

  category:
    | string
    | null;

  inventory_type:
    MaterialInventoryType;

  status:
    InventoryStatus;

  minimum_stock:
    | string
    | null;

  reorder_point:
    | string
    | null;

  maximum_stock:
    | string
    | null;

  on_hand:
    string;
};


export type StockBalanceListResponse = {
  success: true;
  data: StockBalance[];
  meta: ApiMeta;
};
