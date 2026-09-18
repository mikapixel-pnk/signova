import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  IncomeRegisterGroup,
  IncomeRegisterResponse,
} from "@/types/income-register";


export type IncomeRegisterParams = {
  search?: string;

  group?:
    IncomeRegisterGroup;

  from?: string;

  to?: string;

  cash_account_id?: string;

  page?: number;

  per_page?: number;
};


export async function listIncomeRegister(
  params:
    IncomeRegisterParams = {},
): Promise<IncomeRegisterResponse> {
  const query =
    new URLSearchParams();

  if (params.search?.trim()) {
    query.set(
      "search",
      params.search.trim(),
    );
  }

  if (params.group) {
    query.set(
      "group",
      params.group,
    );
  }

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

  if (params.cash_account_id) {
    query.set(
      "cash_account_id",
      params.cash_account_id,
    );
  }

  if (params.page) {
    query.set(
      "page",
      String(params.page),
    );
  }

  if (params.per_page) {
    query.set(
      "per_page",
      String(params.per_page),
    );
  }

  const suffix =
    query.toString();

  return authenticatedApiRequest<
    IncomeRegisterResponse
  >(
    `/finance/income-register${
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
