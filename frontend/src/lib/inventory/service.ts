import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  GoodsReceiptListResponse,
  GoodsReceiptResponse,
  InventoryCategoryCreatePayload,
  InventoryCategoryListResponse,
  InventoryCategoryResponse,
  InventoryCategoryUpdatePayload,
  MaterialCreatePayload,
  MaterialListResponse,
  MaterialResponse,
  MaterialUpdatePayload,
  StockBalanceListResponse,
  WarehouseCreatePayload,
  WarehouseListResponse,
  WarehouseResponse,
  WarehouseUpdatePayload,
} from "@/types/inventory";

export async function listGoodsReceipts():
  Promise<GoodsReceiptListResponse> {
  return authenticatedApiRequest<
    GoodsReceiptListResponse
  >(
    "/inventory/receipts",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function getGoodsReceipt(
  id: string,
): Promise<GoodsReceiptResponse> {
  return authenticatedApiRequest<
    GoodsReceiptResponse
  >(
    `/inventory/receipts/${id}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function listInventoryCategories():
  Promise<InventoryCategoryListResponse> {
  return authenticatedApiRequest<
    InventoryCategoryListResponse
  >(
    "/inventory/categories",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function createInventoryCategory(
  payload:
    InventoryCategoryCreatePayload,
): Promise<InventoryCategoryResponse> {
  return authenticatedApiRequest<
    InventoryCategoryResponse
  >(
    "/inventory/categories",
    {
      method: "POST",
      body: payload,
    },
  );
}

export async function updateInventoryCategory(
  id: string,
  payload:
    InventoryCategoryUpdatePayload,
): Promise<InventoryCategoryResponse> {
  return authenticatedApiRequest<
    InventoryCategoryResponse
  >(
    `/inventory/categories/${id}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
}

export async function listWarehouses():
  Promise<WarehouseListResponse> {
  return authenticatedApiRequest<
    WarehouseListResponse
  >(
    "/inventory/warehouses",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function createWarehouse(
  payload:
    WarehouseCreatePayload,
): Promise<WarehouseResponse> {
  return authenticatedApiRequest<
    WarehouseResponse
  >(
    "/inventory/warehouses",
    {
      method: "POST",
      body: payload,
    },
  );
}

export async function updateWarehouse(
  id: string,
  payload:
    WarehouseUpdatePayload,
): Promise<WarehouseResponse> {
  return authenticatedApiRequest<
    WarehouseResponse
  >(
    `/inventory/warehouses/${id}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
}

export async function listMaterials():
  Promise<MaterialListResponse> {
  return authenticatedApiRequest<
    MaterialListResponse
  >(
    "/inventory/materials",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function createMaterial(
  payload:
    MaterialCreatePayload,
): Promise<MaterialResponse> {
  return authenticatedApiRequest<
    MaterialResponse
  >(
    "/inventory/materials",
    {
      method: "POST",
      body: payload,
    },
  );
}

export async function updateMaterial(
  id: string,
  payload:
    MaterialUpdatePayload,
): Promise<MaterialResponse> {
  return authenticatedApiRequest<
    MaterialResponse
  >(
    `/inventory/materials/${id}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
}


export async function listStockBalances():
  Promise<StockBalanceListResponse> {
  return authenticatedApiRequest<
    StockBalanceListResponse
  >(
    "/inventory/stock-balances",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}
