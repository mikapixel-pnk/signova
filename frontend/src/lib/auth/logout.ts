import {
  clearSelectedContext,
} from "@/lib/auth/active-context";

import {
  clearAccessToken,
  getAccessToken,
} from "@/lib/auth/session";

export async function logout(): Promise<void> {
  const token =
    getAccessToken();

  try {
    if (token) {
      await fetch(
        "/api/v1/auth/logout",
        {
          method: "POST",
          headers: {
            Accept: "application/json",
            Authorization:
              `Bearer ${token}`,
          },
        },
      );
    }
  } finally {
    clearAccessToken();
    clearSelectedContext();
  }
}
