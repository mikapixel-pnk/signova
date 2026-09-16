import {
  authenticatedApiBlobRequest,
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  InvoiceCreatePayload,
  InvoiceListResponse,
  InvoiceResponse,
  InvoiceStatus,
  InvoiceVoidPayload,
} from "@/types/invoice";

export type ListInvoicesParams = {
  search?: string;

  status?:
    InvoiceStatus;

  customer_id?: string;
  page?: number;
  per_page?: number;
};

function queryString(
  params:
    ListInvoicesParams,
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

export async function listInvoices(
  params:
    ListInvoicesParams = {},
): Promise<InvoiceListResponse> {
  return authenticatedApiRequest<
    InvoiceListResponse
  >(
    `/invoices${queryString(
      params,
    )}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function createInvoice(
  payload:
    InvoiceCreatePayload,
): Promise<InvoiceResponse> {
  return authenticatedApiRequest<
    InvoiceResponse
  >(
    "/invoices",
    {
      method: "POST",
      body: payload,
    },
  );
}

export async function getInvoice(
  id: string,
): Promise<InvoiceResponse> {
  return authenticatedApiRequest<
    InvoiceResponse
  >(
    `/invoices/${id}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function issueInvoice(
  id: string,
): Promise<InvoiceResponse> {
  return authenticatedApiRequest<
    InvoiceResponse
  >(
    `/invoices/${id}/actions/issue`,
    {
      method: "POST",
    },
  );
}

export async function voidInvoice(
  id: string,
  payload:
    InvoiceVoidPayload,
): Promise<InvoiceResponse> {
  return authenticatedApiRequest<
    InvoiceResponse
  >(
    `/invoices/${id}/actions/void`,
    {
      method: "POST",
      body: payload,
    },
  );
}

export async function getInvoicePdf(
  id: string,
): Promise<Blob> {
  return authenticatedApiBlobRequest(
    `/invoices/${id}/pdf`,
  );
}
