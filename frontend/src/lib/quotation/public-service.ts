import type {
  PublicQuotationResponse,
} from "@/types/public-quotation";

const PUBLIC_API_BASE =
  "/api/public/v1";

type ApiErrorPayload = {
  message?: string;

  error?: {
    code?: string;
    message?: string;
  };
};

export class PublicQuotationApiError
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
      "PublicQuotationApiError";

    this.status =
      status;

    this.code =
      code;
  }
}

async function publicRequest<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const response =
    await fetch(
      `${PUBLIC_API_BASE}${path}`,
      {
        ...options,

        headers: {
          Accept:
            "application/json",

          ...(options.headers ??
            {}),
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

    throw new PublicQuotationApiError(
      error?.error?.message ??
        error?.message ??
        "Penawaran tidak dapat dimuat.",
      response.status,
      error?.error?.code ??
        null,
    );
  }

  return body as T;
}

export async function getPublicQuotation(
  token: string,
): Promise<PublicQuotationResponse> {
  return publicRequest<
    PublicQuotationResponse
  >(
    `/quotations/${encodeURIComponent(
      token,
    )}`,
  );
}

export async function markPublicQuotationViewed(
  token: string,
): Promise<PublicQuotationResponse> {
  return publicRequest<
    PublicQuotationResponse
  >(
    `/quotations/${encodeURIComponent(
      token,
    )}/actions/view`,
    {
      method:
        "POST",
    },
  );
}

export async function approvePublicQuotation(
  token: string,
): Promise<PublicQuotationResponse> {
  return publicRequest<
    PublicQuotationResponse
  >(
    `/quotations/${encodeURIComponent(
      token,
    )}/approve`,
    {
      method: "POST",
    },
  );
}

export async function rejectPublicQuotation(
  token: string,
  reason: string,
): Promise<PublicQuotationResponse> {
  return publicRequest<
    PublicQuotationResponse
  >(
    `/quotations/${encodeURIComponent(
      token,
    )}/reject`,
    {
      method: "POST",

      headers: {
        "Content-Type":
          "application/json",
      },

      body:
        JSON.stringify({
          reason,
        }),
    },
  );
}
