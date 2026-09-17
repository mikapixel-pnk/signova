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
