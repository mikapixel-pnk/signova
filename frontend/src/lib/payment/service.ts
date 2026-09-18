import {
  authenticatedApiBlobRequest,
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  InvoicePaymentsResponse,
  PaymentCashAccountsResponse,
  PaymentEvidenceResponse,
  RecordInvoicePaymentPayload,
  RecordInvoicePaymentResponse,
} from "@/types/payment";

export async function listInvoicePayments(
  invoiceId: string,
): Promise<InvoicePaymentsResponse> {
  return authenticatedApiRequest<
    InvoicePaymentsResponse
  >(
    `/invoices/${invoiceId}/payments`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function listPaymentCashAccounts():
Promise<PaymentCashAccountsResponse> {
  return authenticatedApiRequest<
    PaymentCashAccountsResponse
  >(
    "/finance/cash-accounts?status=ACTIVE&per_page=100",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function recordInvoicePayment(
  invoiceId: string,
  payload:
    RecordInvoicePaymentPayload,
): Promise<RecordInvoicePaymentResponse> {
  return authenticatedApiRequest<
    RecordInvoicePaymentResponse
  >(
    `/invoices/${invoiceId}/actions/record-payment`,
    {
      method: "POST",
      body: payload,
    },
  );
}


export async function uploadPaymentEvidence(
  paymentId: string,
  file: File,
): Promise<PaymentEvidenceResponse> {
  const body =
    new FormData();

  body.append(
    "evidence",
    file,
  );

  return authenticatedApiRequest<
    PaymentEvidenceResponse
  >(
    `/payments/${paymentId}/evidence`,
    {
      method: "POST",
      body,
    },
  );
}

export async function getPaymentEvidence(
  paymentId: string,
): Promise<Blob> {
  return authenticatedApiBlobRequest(
    `/payments/${paymentId}/evidence`,
  );
}


export type ListPaymentsParams = {
  search?: string;

  status?:
    | "PENDING"
    | "VERIFIED"
    | "REJECTED"
    | "REVERSED";

  page?: number;
  per_page?: number;
};


function paymentQueryString(
  params:
    ListPaymentsParams,
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
      String(
        params.page,
      ),
    );
  }

  if (params.per_page) {
    query.set(
      "per_page",
      String(
        params.per_page,
      ),
    );
  }

  const value =
    query.toString();

  return value
    ? `?${value}`
    : "";
}


export async function listPayments(
  params:
    ListPaymentsParams = {},
): Promise<
  import("@/types/payment")
    .PaymentCenterListResponse
> {
  return authenticatedApiRequest<
    import("@/types/payment")
      .PaymentCenterListResponse
  >(
    `/payments${paymentQueryString(
      params,
    )}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}


export async function getPayment(
  paymentId: string,
): Promise<
  import("@/types/payment")
    .PaymentCenterDetailResponse
> {
  return authenticatedApiRequest<
    import("@/types/payment")
      .PaymentCenterDetailResponse
  >(
    `/payments/${paymentId}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}


export async function rejectPayment(
  paymentId: string,
  reason: string,
): Promise<
  import("@/types/payment")
    .RejectPaymentResponse
> {
  return authenticatedApiRequest<
    import("@/types/payment")
      .RejectPaymentResponse
  >(
    `/payments/${paymentId}/actions/reject`,
    {
      method: "POST",

      body: {
        reason,
      },
    },
  );
}
