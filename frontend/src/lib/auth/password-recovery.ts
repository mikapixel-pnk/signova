import {
  apiRequest,
} from "@/lib/api/client";

import type {
  ForgotPasswordPayload,
  ForgotPasswordResponse,
  ResetPasswordPayload,
  ResetPasswordResponse,
  VerifyPasswordResetPayload,
  VerifyPasswordResetResponse,
} from "@/types/password-recovery";

export async function requestPasswordReset(
  payload: ForgotPasswordPayload,
): Promise<ForgotPasswordResponse> {
  return apiRequest<ForgotPasswordResponse>(
    "/auth/password/forgot",
    {
      method: "POST",
      body: payload,
      cache: "no-store",
    },
  );
}

export async function verifyPasswordResetOtp(
  payload: VerifyPasswordResetPayload,
): Promise<VerifyPasswordResetResponse> {
  return apiRequest<VerifyPasswordResetResponse>(
    "/auth/password/verify",
    {
      method: "POST",
      body: payload,
      cache: "no-store",
    },
  );
}

export async function resetPassword(
  payload: ResetPasswordPayload,
): Promise<ResetPasswordResponse> {
  return apiRequest<ResetPasswordResponse>(
    "/auth/password/reset",
    {
      method: "POST",
      body: payload,
      cache: "no-store",
    },
  );
}
