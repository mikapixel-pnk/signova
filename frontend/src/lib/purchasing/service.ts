import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  PurchaseOrderListResponse,
  PurchaseOrderResponse,
  PurchaseRequestListResponse,
  PurchaseRequestResponse,
  PurchaseRequestStatus,
} from "@/types/purchasing";

export type ListPurchaseRequestsParams = {
  search?: string;
  status?: PurchaseRequestStatus;
  page?: number;
  perPage?: number;
};

function purchaseRequestQueryString(
  params: ListPurchaseRequestsParams,
): string {
  const query =
    new URLSearchParams();

  if (params.search?.trim()) {
    query.set(
      "search",
      params.search.trim(),
    );
  }

  if (params.status) {
    query.set(
      "status",
      params.status,
    );
  }

  if (params.page) {
    query.set(
      "page",
      String(params.page),
    );
  }

  if (params.perPage) {
    query.set(
      "per_page",
      String(params.perPage),
    );
  }

  const value =
    query.toString();

  return value
    ? `?${value}`
    : "";
}

export async function listPurchaseRequests(
  params:
    ListPurchaseRequestsParams = {},
): Promise<PurchaseRequestListResponse> {
  return authenticatedApiRequest<
    PurchaseRequestListResponse
  >(
    `/purchasing/requests${purchaseRequestQueryString(params)}`,
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
