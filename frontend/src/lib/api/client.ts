import { appConfig } from "@/lib/config/app";
import {
  ApiClientError,
  type ApiErrorPayload,
} from "@/lib/api/types";

type ApiRequestOptions = Omit<
  RequestInit,
  "body"
> & {
  body?: unknown;
  accessToken?: string | null;
};

function buildUrl(path: string): string {
  const normalizedPath = path.startsWith("/")
    ? path
    : `/${path}`;

  return `${appConfig.apiBaseUrl}${normalizedPath}`;
}

export async function apiRequest<T>(
  path: string,
  options: ApiRequestOptions = {},
): Promise<T> {
  const {
    accessToken,
    body,
    headers,
    ...requestOptions
  } = options;

  const requestHeaders = new Headers(headers);

  requestHeaders.set(
    "Accept",
    "application/json",
  );

  if (body !== undefined) {
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

  const response = await fetch(
    buildUrl(path),
    {
      ...requestOptions,
      headers: requestHeaders,
      body:
        body === undefined
          ? undefined
          : JSON.stringify(body),
    },
  );

  if (! response.ok) {
    let payload: ApiErrorPayload = {};

    try {
      payload =
        (await response.json()) as ApiErrorPayload;
    } catch {
      // Response may not contain JSON.
    }

    throw new ApiClientError(
      payload.error?.message ??
        "Permintaan tidak dapat diproses.",
      response.status,
      payload.error?.code ?? null,
      payload.error?.meta ?? {},
    );
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return (await response.json()) as T;
}
