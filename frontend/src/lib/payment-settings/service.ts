import {
  authenticatedApiBlobRequest,
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  PaymentSettingsPayload,
  PaymentSettingsResponse,
} from "@/types/payment-settings";

export async function getPaymentSettings():
Promise<PaymentSettingsResponse> {
  return authenticatedApiRequest<
    PaymentSettingsResponse
  >(
    "/settings/payment",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function updatePaymentSettings(
  payload:
    PaymentSettingsPayload,
): Promise<PaymentSettingsResponse> {
  return authenticatedApiRequest<
    PaymentSettingsResponse
  >(
    "/settings/payment",
    {
      method: "PATCH",
      body: payload,
    },
  );
}

export async function uploadPaymentQr(
  file: File,
): Promise<PaymentSettingsResponse> {
  const body =
    new FormData();

  body.append(
    "qr",
    file,
  );

  return authenticatedApiRequest<
    PaymentSettingsResponse
  >(
    "/settings/payment/static-qr",
    {
      method: "POST",
      body,
    },
  );
}

export async function deletePaymentQr():
Promise<PaymentSettingsResponse> {
  return authenticatedApiRequest<
    PaymentSettingsResponse
  >(
    "/settings/payment/static-qr",
    {
      method: "DELETE",
    },
  );
}

export async function getPaymentQr():
Promise<Blob> {
  return authenticatedApiBlobRequest(
    "/settings/payment/static-qr",
  );
}
