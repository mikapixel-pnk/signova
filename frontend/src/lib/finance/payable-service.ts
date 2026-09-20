import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  SupplierBillListResponse,
  SupplierBillResponse,
  SupplierPaymentListResponse,
  SupplierPaymentResponse,
} from "@/types/payable";

export async function listSupplierBills():
  Promise<SupplierBillListResponse> {
  return authenticatedApiRequest<
    SupplierBillListResponse
  >(
    "/finance/payables/bills",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function getSupplierBill(
  id: string,
): Promise<SupplierBillResponse> {
  return authenticatedApiRequest<
    SupplierBillResponse
  >(
    `/finance/payables/bills/${id}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function listSupplierPayments():
  Promise<SupplierPaymentListResponse> {
  return authenticatedApiRequest<
    SupplierPaymentListResponse
  >(
    "/finance/payables/payments",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function getSupplierPayment(
  id: string,
): Promise<SupplierPaymentResponse> {
  return authenticatedApiRequest<
    SupplierPaymentResponse
  >(
    `/finance/payables/payments/${id}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}
