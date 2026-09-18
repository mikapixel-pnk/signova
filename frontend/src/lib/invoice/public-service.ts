import type {
  PublicInvoicePaymentResponse,
  PublicInvoiceResponse,
} from "@/types/public-invoice";

const PUBLIC_API_BASE =
  "/api/public/v1";

type ApiErrorPayload = {
  message?: string;

  error?: {
    code?: string;
    message?: string;
  };
};

export class PublicInvoiceApiError
  extends Error {
  status:
    number;

  code:
    string | null;

  constructor(
    message: string,
    status: number,
    code: string | null,
  ) {
    super(message);

    this.name =
      "PublicInvoiceApiError";

    this.status =
      status;

    this.code =
      code;
  }
}

async function publicRequest<T>(
  path: string,
): Promise<T> {
  const response =
    await fetch(
      `${PUBLIC_API_BASE}${path}`,
      {
        method: "GET",

        headers: {
          Accept:
            "application/json",
        },

        cache:
          "no-store",
      },
    );

  const body =
    (await response
      .json()
      .catch(
        () => null,
      )) as
      | ApiErrorPayload
      | T
      | null;

  if (!response.ok) {
    const error =
      body as
        | ApiErrorPayload
        | null;

    throw new PublicInvoiceApiError(
      error?.error?.message ??
        error?.message ??
        "Tagihan tidak dapat dimuat.",
      response.status,
      error?.error?.code ??
        null,
    );
  }

  return body as T;
}

export async function getPublicInvoice(
  token: string,
): Promise<PublicInvoiceResponse> {
  return publicRequest<
    PublicInvoiceResponse
  >(
    `/invoices/${encodeURIComponent(
      token,
    )}`,
  );
}


export type SubmitPublicInvoicePaymentInput = {
  paymentAccountToken: string;

  amount: string;

  paidAt: string;

  reference?: string;

  evidence: File;
};


export async function submitPublicInvoicePayment(
  token: string,
  input: SubmitPublicInvoicePaymentInput,
): Promise<PublicInvoicePaymentResponse> {
  const formData =
    new FormData();

  formData.append(
    "payment_account_token",
    input.paymentAccountToken,
  );

  formData.append(
    "amount",
    input.amount,
  );

  formData.append(
    "paid_at",
    input.paidAt,
  );

  const reference =
    input.reference?.trim();

  if (reference) {
    formData.append(
      "reference",
      reference,
    );
  }

  formData.append(
    "evidence",
    input.evidence,
  );

  const response =
    await fetch(
      `${PUBLIC_API_BASE}/invoices/${encodeURIComponent(
        token,
      )}/payments`,
      {
        method: "POST",

        headers: {
          Accept:
            "application/json",
        },

        body:
          formData,

        cache:
          "no-store",
      },
    );

  const body =
    (await response
      .json()
      .catch(
        () => null,
      )) as
      | ApiErrorPayload
      | PublicInvoicePaymentResponse
      | null;

  if (!response.ok) {
    const error =
      body as
        | ApiErrorPayload
        | null;

    throw new PublicInvoiceApiError(
      error?.error?.message ??
        error?.message ??
        "Konfirmasi pembayaran belum berhasil dikirim.",
      response.status,
      error?.error?.code ??
        null,
    );
  }

  return body as
    PublicInvoicePaymentResponse;
}
