import {
  authenticatedApiRequest,
} from "@/lib/api/client";

export type ActiveCapabilitiesResponse = {
  data: {
    tenant_id: string;
    business_id: string;
    capability_codes: string[];
  };
};

export async function getActiveCapabilities():
Promise<ActiveCapabilitiesResponse> {
  return authenticatedApiRequest<
    ActiveCapabilitiesResponse
  >(
    "/auth/capabilities",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}
