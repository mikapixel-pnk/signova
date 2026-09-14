import { apiRequest } from "@/lib/api/client";
import type {
  AuthContextData,
} from "@/types/auth";

type AuthContextResponse = {
  data: AuthContextData;
};

export async function fetchAuthContext(
  accessToken: string,
): Promise<AuthContextData> {
  const response =
    await apiRequest<AuthContextResponse>(
      "/auth/context",
      {
        method: "GET",
        accessToken,
        cache: "no-store",
      },
    );

  return response.data;
}
