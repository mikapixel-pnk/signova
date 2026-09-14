import {
  ApiClientError,
  type ApiErrorPayload,
} from "@/lib/api/types";

import {
  getSelectedContext,
} from "@/lib/auth/active-context";

import {
  getAccessToken,
} from "@/lib/auth/session";

import {
  appConfig,
} from "@/lib/config/app";

type ApiRequestOptions =
  Omit<
    RequestInit,
    "body"
  > & {
    body?: unknown;

    accessToken?:
      | string
      | null;

    tenantId?:
      | string
      | null;
  };

function buildUrl(
  path: string,
): string {
  const normalizedPath =
    path.startsWith("/")
      ? path
      : `/${path}`;

  return (
    `${appConfig.apiBaseUrl}` +
    normalizedPath
  );
}

function firstValidationMessage(
  errors:
    | Record<
        string,
        string[]
      >
    | undefined,
): string | null {
  if (!errors) {
    return null;
  }

  for (
    const messages
    of Object.values(
      errors,
    )
  ) {
    if (
      Array.isArray(
        messages,
      ) &&
      messages.length > 0
    ) {
      return (
        messages[0] ??
        null
      );
    }
  }

  return null;
}

async function parseApiError(
  response: Response,
): Promise<ApiClientError> {
  let payload:
    ApiErrorPayload = {};

  try {
    payload =
      (await response.json()) as
        ApiErrorPayload;
  } catch {
    // Response may not
    // contain JSON.
  }

  const fieldErrors =
    payload.errors ?? {};

  const validationMessage =
    firstValidationMessage(
      fieldErrors,
    );

  const message =
    payload.error
      ?.message ??
    validationMessage ??
    payload.message ??
    "Permintaan belum berhasil diproses.";

  return new ApiClientError(
    message,
    response.status,
    {
      code:
        payload.error
          ?.code ??
        null,

      details:
        payload.error
          ?.details ??
        {},

      requestId:
        payload.meta
          ?.request_id ??
        response.headers.get(
          "X-Request-ID",
        ),

      fieldErrors,
    },
  );
}

export async function apiRequest<
  T,
>(
  path: string,
  options:
    ApiRequestOptions = {},
): Promise<T> {
  const {
    accessToken,
    tenantId,
    body,
    headers,
    ...requestOptions
  } = options;

  const requestHeaders =
    new Headers(
      headers,
    );

  requestHeaders.set(
    "Accept",
    "application/json",
  );

  if (
    body !== undefined
  ) {
    requestHeaders.set(
      "Content-Type",
      "application/json",
    );
  }

  if (accessToken) {
    requestHeaders.set(
      "Authorization",
      `Bearer ${accessToken}`,
    );
  }

  if (tenantId) {
    requestHeaders.set(
      "X-Signova-Tenant",
      tenantId,
    );
  }

  const response =
    await fetch(
      buildUrl(path),
      {
        ...requestOptions,

        headers:
          requestHeaders,

        body:
          body ===
          undefined
            ? undefined
            : JSON.stringify(
                body,
              ),
      },
    );

  if (!response.ok) {
    throw await parseApiError(
      response,
    );
  }

  if (
    response.status ===
    204
  ) {
    return undefined as T;
  }

  return (
    await response.json()
  ) as T;
}

export async function authenticatedApiRequest<
  T,
>(
  path: string,
  options:
    Omit<
      ApiRequestOptions,
      | "accessToken"
      | "tenantId"
    > = {},
): Promise<T> {
  const accessToken =
    getAccessToken();

  if (!accessToken) {
    throw new ApiClientError(
      "Sesi masuk tidak tersedia. Silakan masuk kembali.",
      401,
      {
        code:
          "AUTH_REQUIRED",
      },
    );
  }

  const context =
    getSelectedContext();

  const tenantId =
    context?.type ===
    "TENANT"
      ? context.tenantId
      : null;

  return apiRequest<T>(
    path,
    {
      ...options,
      accessToken,
      tenantId,
    },
  );
}
