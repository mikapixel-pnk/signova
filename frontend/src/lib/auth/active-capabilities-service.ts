import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import {
  getSelectedContext,
} from "@/lib/auth/active-context";

export type ActiveCapabilitiesResponse = {
  data: {
    tenant_id: string;
    business_id: string;
    capability_codes: string[];
  };
};

let cachedContextKey:
  | string
  | null = null;

let cachedResponse:
  | ActiveCapabilitiesResponse
  | null = null;

let pendingContextKey:
  | string
  | null = null;

let pendingRequest:
  | Promise<ActiveCapabilitiesResponse>
  | null = null;

function currentContextKey():
  | string
  | null {
  const context =
    getSelectedContext();

  if (
    !context ||
    context.type !== "TENANT"
  ) {
    return null;
  }

  return [
    context.tenantId,
    context.businessId,
  ].join(":");
}

export function clearActiveCapabilitiesCache(): void {
  cachedContextKey = null;
  cachedResponse = null;
  pendingContextKey = null;
  pendingRequest = null;
}

export async function getActiveCapabilities(
  force = false,
): Promise<ActiveCapabilitiesResponse> {
  const contextKey =
    currentContextKey();

  if (
    !force &&
    contextKey &&
    cachedContextKey ===
      contextKey &&
    cachedResponse
  ) {
    return cachedResponse;
  }

  if (
    !force &&
    contextKey &&
    pendingContextKey ===
      contextKey &&
    pendingRequest
  ) {
    return pendingRequest;
  }

  const request =
    authenticatedApiRequest<
      ActiveCapabilitiesResponse
    >(
      "/auth/capabilities",
      {
        method: "GET",
        cache: "no-store",
      },
    );

  pendingContextKey =
    contextKey;

  pendingRequest =
    request;

  try {
    const response =
      await request;

    /*
     * Cache hanya valid untuk context
     * tenant + business yang sama.
     */
    if (
      contextKey &&
      currentContextKey() ===
        contextKey
    ) {
      cachedContextKey =
        contextKey;

      cachedResponse =
        response;
    }

    return response;
  } finally {
    if (
      pendingRequest ===
        request
    ) {
      pendingRequest = null;
      pendingContextKey = null;
    }
  }
}
