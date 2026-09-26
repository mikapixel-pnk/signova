import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  PurchaseOrderListResponse,
  PurchaseOrderPayload,
  PurchaseOrderResponse,
  UpdatePurchaseOrderPayload,
  PurchaseRequestListResponse,
  PurchaseRequestPayload,
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

export async function createPurchaseRequest(
  payload: PurchaseRequestPayload,
): Promise<PurchaseRequestResponse> {
  return authenticatedApiRequest<
    PurchaseRequestResponse
  >(
    "/purchasing/requests",
    {
      method: "POST",
      body: payload,
    },
  );
}

export async function updatePurchaseRequest(
  id: string,
  payload: PurchaseRequestPayload,
): Promise<PurchaseRequestResponse> {
  return authenticatedApiRequest<
    PurchaseRequestResponse
  >(
    `/purchasing/requests/${id}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
}

export async function submitPurchaseRequest(
  id: string,
): Promise<PurchaseRequestResponse> {
  return authenticatedApiRequest<
    PurchaseRequestResponse
  >(
    `/purchasing/requests/${id}/actions/submit`,
    {
      method: "POST",
    },
  );
}

export async function approvePurchaseRequest(
  id: string,
): Promise<PurchaseRequestResponse> {
  return authenticatedApiRequest<
    PurchaseRequestResponse
  >(
    `/purchasing/requests/${id}/actions/approve`,
    {
      method: "POST",
    },
  );
}

export async function rejectPurchaseRequest(
  id: string,
  reason: string,
): Promise<PurchaseRequestResponse> {
  return authenticatedApiRequest<
    PurchaseRequestResponse
  >(
    `/purchasing/requests/${id}/actions/reject`,
    {
      method: "POST",
      body: {
        reason,
      },
    },
  );
}

export async function revisePurchaseRequest(
  id: string,
): Promise<PurchaseRequestResponse> {
  return authenticatedApiRequest<
    PurchaseRequestResponse
  >(
    `/purchasing/requests/${id}/actions/revise`,
    {
      method: "POST",
    },
  );
}

export async function cancelPurchaseRequest(
  id: string,
  reason: string,
): Promise<PurchaseRequestResponse> {
  return authenticatedApiRequest<
    PurchaseRequestResponse
  >(
    `/purchasing/requests/${id}/actions/cancel`,
    {
      method: "POST",
      body: {
        reason,
      },
    },
  );
}

export async function createPurchaseOrder(
  payload: PurchaseOrderPayload,
): Promise<PurchaseOrderResponse> {
  return authenticatedApiRequest<
    PurchaseOrderResponse
  >(
    "/purchasing/orders",
    {
      method: "POST",
      body: payload,
    },
  );
}

export async function updatePurchaseOrder(
  id: string,
  payload: UpdatePurchaseOrderPayload,
): Promise<PurchaseOrderResponse> {
  return authenticatedApiRequest<
    PurchaseOrderResponse
  >(
    `/purchasing/orders/${id}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
}

export async function issuePurchaseOrder(
  id: string,
): Promise<PurchaseOrderResponse> {
  return authenticatedApiRequest<
    PurchaseOrderResponse
  >(
    `/purchasing/orders/${id}/actions/issue`,
    {
      method: "POST",
    },
  );
}

export async function cancelPurchaseOrder(
  id: string,
  reason: string,
): Promise<PurchaseOrderResponse> {
  return authenticatedApiRequest<
    PurchaseOrderResponse
  >(
    `/purchasing/orders/${id}/actions/cancel`,
    {
      method: "POST",
      body: {
        reason,
      },
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
