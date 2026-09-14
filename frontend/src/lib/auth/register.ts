import {
  apiRequest,
} from "@/lib/api/client";

import type {
  RegisterPayload,
  RegisterResponse,
} from "@/types/register";

export async function register(
  payload: RegisterPayload,
): Promise<RegisterResponse> {
  return apiRequest<RegisterResponse>(
    "/auth/register",
    {
      method: "POST",
      body: payload,
      cache: "no-store",
    },
  );
}
