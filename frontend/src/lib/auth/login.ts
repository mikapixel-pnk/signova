import {
  apiRequest,
} from "@/lib/api/client";
import type {
  LoginPayload,
  LoginResponse,
} from "@/types/login";

export async function login(
  payload: LoginPayload,
): Promise<LoginResponse> {
  return apiRequest<LoginResponse>(
    "/auth/login",
    {
      method: "POST",
      body: payload,
      cache: "no-store",
    },
  );
}
