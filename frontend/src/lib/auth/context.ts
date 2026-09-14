import {
  apiRequest,
} from "@/lib/api/client";

import type {
  AccessContext,
  AuthUser,
  PlatformAccess,
  TenantAccess,
} from "@/types/auth";

export type AuthContextData = {
  user: AuthUser;

  access: {
    platform: PlatformAccess;
    tenants: TenantAccess[];
  };

  default_context:
    | AccessContext
    | null;

  requires_context_selection:
    boolean;

  has_access: boolean;
};

export type AuthContextResponse = {
  data: AuthContextData;
};

let cachedContext:
  | AuthContextResponse
  | null = null;

let contextRequest:
  | Promise<AuthContextResponse>
  | null = null;

export async function getAuthContext(
  force = false,
): Promise<AuthContextResponse> {
  if (
    !force &&
    cachedContext
  ) {
    return cachedContext;
  }

  if (
    !force &&
    contextRequest
  ) {
    return contextRequest;
  }

  contextRequest =
    apiRequest<AuthContextResponse>(
      "/auth/context",
      {
        method: "GET",
        cache: "no-store",
      },
    )
      .then((response) => {
        cachedContext =
          response;

        return response;
      })
      .finally(() => {
        contextRequest =
          null;
      });

  return contextRequest;
}

export function clearAuthContextCache(): void {
  cachedContext =
    null;

  contextRequest =
    null;
}
