import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  RecordIncomePayload,
  RecordIncomeResponse,
} from "@/types/income";

export async function recordIncome(
  payload: RecordIncomePayload,
): Promise<RecordIncomeResponse> {
  return authenticatedApiRequest<
    RecordIncomeResponse
  >(
    "/finance/incomes/actions/record",
    {
      method: "POST",
      body: payload,
    },
  );
}
