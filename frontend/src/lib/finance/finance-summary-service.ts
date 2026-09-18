import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  FinanceSummaryResponse,
} from "@/types/finance-summary";


export type FinanceSummaryParams = {
  from?: string;
  to?: string;
};


export async function getFinanceSummary(
  params:
    FinanceSummaryParams = {},
): Promise<FinanceSummaryResponse> {
  const query =
    new URLSearchParams();

  if (params.from) {
    query.set(
      "from",
      params.from,
    );
  }

  if (params.to) {
    query.set(
      "to",
      params.to,
    );
  }

  const suffix =
    query.toString();

  return authenticatedApiRequest<
    FinanceSummaryResponse
  >(
    `/finance/summary${
      suffix
        ? `?${suffix}`
        : ""
    }`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}
