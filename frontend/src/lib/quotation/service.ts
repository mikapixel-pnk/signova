import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import {
  appConfig,
} from "@/lib/config/app";

import type {
  QuotationCreatePayload,
  QuotationHeaderUpdatePayload,
  QuotationInvoiceResponse,
  QuotationListResponse,
  QuotationManualDecisionPayload,
  QuotationPublicLinkResponse,
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


export async function getQuotation(
  id: string,
): Promise<QuotationResponse> {
  return authenticatedApiRequest<
    QuotationResponse
  >(
    `/quotations/${id}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export function quotationPdfUrl(
  id: string,
): string {
  return (
    `${appConfig.apiBaseUrl}` +
    `/quotations/${id}/pdf`
  );
}


export async function sendQuotation(
  id: string,
): Promise<QuotationResponse> {
  return authenticatedApiRequest<
    QuotationResponse
  >(
    `/quotations/${id}/actions/send`,
    {
      method: "POST",
    },
  );
}

export async function issueQuotationPublicLink(
  id: string,
): Promise<QuotationPublicLinkResponse> {
  return authenticatedApiRequest<
    QuotationPublicLinkResponse
  >(
    `/quotations/${id}/actions/issue-public-link`,
    {
      method: "POST",
    },
  );
}

export async function recordQuotationManualDecision(
  id: string,
  payload: QuotationManualDecisionPayload,
): Promise<QuotationResponse> {
  return authenticatedApiRequest<
    QuotationResponse
  >(
    `/quotations/${id}/actions/manual-decision`,
    {
      method: "POST",
      body: payload,
    },
  );
}

export async function createInvoiceFromQuotation(
  id: string,
  dueAt?: string | null,
): Promise<QuotationInvoiceResponse> {
  return authenticatedApiRequest<
    QuotationInvoiceResponse
  >(
    `/quotations/${id}/actions/create-invoice`,
    {
      method: "POST",
      body: {
        due_at:
          dueAt ?? null,
      },
    },
  );
}


export async function updateQuotationHeader(
  id: string,
  payload:
    QuotationHeaderUpdatePayload,
): Promise<QuotationResponse> {
  return authenticatedApiRequest<
    QuotationResponse
  >(
    `/quotations/${id}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
}
