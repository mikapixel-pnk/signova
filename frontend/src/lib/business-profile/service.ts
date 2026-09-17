import {
  authenticatedApiBlobRequest,
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

export async function uploadBusinessLogo(
  file: File,
): Promise<BusinessProfileResponse> {
  const body =
    new FormData();

  body.append(
    "logo",
    file,
  );

  return authenticatedApiRequest<
    BusinessProfileResponse
  >(
    "/settings/business-profile/logo",
    {
      method: "POST",
      body,
    },
  );
}

export async function deleteBusinessLogo():
Promise<BusinessProfileResponse> {
  return authenticatedApiRequest<
    BusinessProfileResponse
  >(
    "/settings/business-profile/logo",
    {
      method: "DELETE",
    },
  );
}

export async function getBusinessLogo():
Promise<Blob> {
  return authenticatedApiBlobRequest(
    "/settings/business-profile/logo",
  );
}
