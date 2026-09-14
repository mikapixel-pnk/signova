import {
  apiRequest,
} from "@/lib/api/client";

import {
  clearPendingAccessSelection,
} from "@/lib/auth/access-selection";

import {
  clearSelectedContext,
} from "@/lib/auth/active-context";

import {
  clearAccessToken,
} from "@/lib/auth/session";

import {
  clearAuthContextCache,
} from "@/lib/auth/context";

import {
  clearPrivateAppCache,
} from "@/lib/pwa/private-cache";

export async function logout():
  Promise<void> {
  try {
    await apiRequest<{
      message: string;
    }>(
      "/auth/logout",
      {
        method: "POST",
        cache: "no-store",
      },
    );
  } finally {
    clearAccessToken();

    clearAuthContextCache();

    clearSelectedContext();

    clearPendingAccessSelection();

    await clearPrivateAppCache();
  }
}
