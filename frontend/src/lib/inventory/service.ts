import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  GoodsReceiptListResponse,
  GoodsReceiptResponse,
  MaterialListResponse,
  WarehouseListResponse,
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
