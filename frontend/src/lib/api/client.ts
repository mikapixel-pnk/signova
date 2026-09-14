import {
  ApiClientError,
  type ApiErrorPayload,
} from "@/lib/api/types";

import {
  getSelectedContext,
} from "@/lib/auth/active-context";

import {
  appConfig,
} from "@/lib/config/app";

type ApiRequestOptions =
  Omit<
    RequestInit,
    "body"
  > & {
    body?: unknown;

    /*
     * Dipertahankan sementara untuk client
     * non-Web / backward compatibility.
     * SIGNOVA Web/PWA tidak bergantung
     * pada bearer token lagi.
     */
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

function readCookie(
  name: string,
): string | null {
  if (
    typeof document ===
    "undefined"
  ) {
    return null;
  }

  const prefix =
    `${name}=`;

  for (
    const part of
    document.cookie.split(";")
  ) {
    const value =
      part.trim();

    if (
      value.startsWith(
        prefix,
      )
    ) {
      return decodeURIComponent(
        value.slice(
          prefix.length,
        ),
      );
    }
  }

  return null;
}

function isUnsafeMethod(
  method:
    | string
    | undefined,
): boolean {
  const normalized =
    (
      method ??
      "GET"
    ).toUpperCase();

  return ![
    "GET",
    "HEAD",
    "OPTIONS",
  ].includes(normalized);
}

let csrfRequest:
  | Promise<void>
  | null = null;

export async function ensureCsrfCookie(
  force = false,
): Promise<void> {
  if (
    typeof window ===
    "undefined"
  ) {
    return;
  }

  if (
    !force &&
    readCookie(
      "XSRF-TOKEN",
    )
  ) {
    return;
  }

  if (
    csrfRequest &&
    !force
  ) {
    return csrfRequest;
  }

  csrfRequest =
    fetch(
      appConfig.csrfPath,
      {
        method: "GET",
        credentials:
          "include",
        headers: {
          Accept:
            "application/json",
        },
        cache:
          "no-store",
      },
    ).then(
      (response) => {
        if (!response.ok) {
          throw new ApiClientError(
            "Sesi keamanan belum dapat disiapkan. Silakan coba lagi.",
            response.status,
            {
              code:
                "CSRF_BOOTSTRAP_FAILED",
            },
          );
        }
      },
    ).finally(
      () => {
        csrfRequest =
          null;
      },
    );

  return csrfRequest;
}

function normalizeFieldErrors(
  value: unknown,
): Record<
  string,
  string[]
> {
  if (
    !value ||
    typeof value !== "object" ||
    Array.isArray(value)
  ) {
    return {};
  }

  const normalized:
    Record<
      string,
      string[]
    > = {};

  for (
    const [
      field,
      messages,
    ] of Object.entries(
      value,
    )
  ) {
    if (
      Array.isArray(
        messages,
      )
    ) {
      const valid =
        messages.filter(
          (
            message,
          ): message is string =>
            typeof message ===
            "string",
        );

      if (
        valid.length > 0
      ) {
        normalized[field] =
          valid;
      }
    }
  }

  return normalized;
}

function firstValidationMessage(
  errors:
    Record<
      string,
      string[]
    >,
): string | null {
  for (
    const messages
    of Object.values(
      errors,
    )
  ) {
    if (
      messages.length >
      0
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
    // Response may not contain JSON.
  }

  /*
   * SIGNOVA canonical error:
   * error.details.fields
   *
   * payload.errors tetap dibaca sementara
   * untuk compatibility endpoint legacy.
   */
  const detailFields =
    normalizeFieldErrors(
      payload.error
        ?.details
        ?.fields,
    );

  const legacyFields =
    normalizeFieldErrors(
      payload.errors,
    );

  const fieldErrors =
    Object.keys(
      detailFields,
    ).length > 0
      ? detailFields
      : legacyFields;

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

async function performRequest<
  T,
>(
  path: string,
  options:
    ApiRequestOptions,
  retryCsrf: boolean,
): Promise<T> {
  const {
    accessToken,
    tenantId,
    body,
    headers,
    ...requestOptions
  } = options;

  const method =
    (
      requestOptions.method ??
      "GET"
    ).toUpperCase();

  if (
    isUnsafeMethod(
      method,
    )
  ) {
    await ensureCsrfCookie();
  }

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

  if (
    isUnsafeMethod(
      method,
    )
  ) {
    const xsrf =
      readCookie(
        "XSRF-TOKEN",
      );

    if (xsrf) {
      requestHeaders.set(
        "X-XSRF-TOKEN",
        xsrf,
      );
    }
  }

  const response =
    await fetch(
      buildUrl(path),
      {
        ...requestOptions,

        method,

        credentials:
          "include",

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

  /*
   * Session atau CSRF dapat berganti
   * setelah login/logout. Refresh token
   * CSRF sekali lalu retry aman.
   */
  if (
    response.status ===
      419 &&
    retryCsrf &&
    isUnsafeMethod(
      method,
    )
  ) {
    await ensureCsrfCookie(
      true,
    );

    return performRequest<T>(
      path,
      options,
      false,
    );
  }

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

export async function apiRequest<
  T,
>(
  path: string,
  options:
    ApiRequestOptions = {},
): Promise<T> {
  return performRequest<T>(
    path,
    options,
    true,
  );
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
  const context =
    getSelectedContext();

  const tenantId =
    context?.type ===
    "TENANT"
      ? context.tenantId
      : null;

  /*
   * Authentication berasal dari
   * HttpOnly Laravel session cookie.
   */
  return apiRequest<T>(
    path,
    {
      ...options,
      tenantId,
    },
  );
}
