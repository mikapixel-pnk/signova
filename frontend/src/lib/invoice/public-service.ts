import type {
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
