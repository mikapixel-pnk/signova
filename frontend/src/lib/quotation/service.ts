import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  QuotationCreatePayload,
  QuotationListResponse,
  QuotationResponse,
  QuotationStatus,
} from "@/types/quotation";

export type ListQuotationsParams = {
  search?: string;
  status?: QuotationStatus;
  customer_id?: string;
  page?: number;
  per_page?: number;
};

function queryString(
  params:
    ListQuotationsParams,
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

  if (params.customer_id) {
    query.set(
      "customer_id",
      params.customer_id,
    );
  }

  if (params.page) {
    query.set(
      "page",
      String(params.page),
    );
  }

  if (params.per_page) {
    query.set(
      "per_page",
      String(params.per_page),
    );
  }

  const value =
    query.toString();

  return value
    ? `?${value}`
    : "";
}

export async function listQuotations(
  params:
    ListQuotationsParams = {},
): Promise<QuotationListResponse> {
  return authenticatedApiRequest<
    QuotationListResponse
  >(
    `/quotations${queryString(
      params,
    )}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function createQuotation(
  payload:
    QuotationCreatePayload,
): Promise<QuotationResponse> {
  return authenticatedApiRequest<
    QuotationResponse
  >(
    "/quotations",
    {
      method: "POST",
      body: payload,
    },
  );
}
