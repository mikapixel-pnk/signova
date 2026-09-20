import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  PurchaseOrderListResponse,
  PurchaseOrderResponse,
  PurchaseRequestListResponse,
  PurchaseRequestResponse,
} from "@/types/purchasing";

export async function listPurchaseRequests():
  Promise<PurchaseRequestListResponse> {
  return authenticatedApiRequest<
    PurchaseRequestListResponse
  >(
    "/purchasing/requests",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function getPurchaseRequest(
  id: string,
): Promise<PurchaseRequestResponse> {
  return authenticatedApiRequest<
    PurchaseRequestResponse
  >(
    `/purchasing/requests/${id}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function listPurchaseOrders():
  Promise<PurchaseOrderListResponse> {
  return authenticatedApiRequest<
    PurchaseOrderListResponse
  >(
    "/purchasing/orders",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function getPurchaseOrder(
  id: string,
): Promise<PurchaseOrderResponse> {
  return authenticatedApiRequest<
    PurchaseOrderResponse
  >(
    `/purchasing/orders/${id}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}
