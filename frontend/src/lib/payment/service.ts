import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  InvoicePaymentsResponse,
  PaymentCashAccountsResponse,
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
