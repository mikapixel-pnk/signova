import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  BusinessProfilePayload,
  BusinessProfileResponse,
} from "@/types/business-profile";

export async function getBusinessProfile():
Promise<BusinessProfileResponse> {
  return authenticatedApiRequest<
    BusinessProfileResponse
  >(
    "/settings/business-profile",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function updateBusinessProfile(
  payload: BusinessProfilePayload,
): Promise<BusinessProfileResponse> {
  return authenticatedApiRequest<
    BusinessProfileResponse
  >(
    "/settings/business-profile",
    {
      method: "PATCH",
      body: payload,
    },
  );
}
