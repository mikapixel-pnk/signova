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
  category: string | null;
  inventory_type: MaterialInventoryType;
  status: InventoryStatus;
  status_label: string;
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

export type WarehouseListResponse = {
  success: true;
  data: Warehouse[];
  meta: PaginationMeta;
};

export type MaterialListResponse = {
  success: true;
  data: Material[];
  meta: PaginationMeta;
};
