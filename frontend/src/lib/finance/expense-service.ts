import {
  authenticatedApiBlobRequest,
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  CreateExpensePayload,
  ExpenseListResponse,
  ExpenseResponse,
  ExpenseStatus,
  RecordExpensePayload,
  UpdateExpensePayload,
} from "@/types/expense";

export type ListExpensesParams = {
  search?: string;
  status?: ExpenseStatus;
  from?: string;
  to?: string;
  page?: number;
  per_page?: number;
};

export async function listExpenses(
  params:
    ListExpensesParams = {},
): Promise<ExpenseListResponse> {
  const query =
    new URLSearchParams();

  if (params.search) {
    query.set(
      "search",
      params.search,
    );
  }

  if (params.status) {
    query.set(
      "status",
      params.status,
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
    ExpenseListResponse
  >(
    `/finance/expenses${
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

export async function recordExpense(
  payload: RecordExpensePayload,
): Promise<ExpenseResponse> {
  return authenticatedApiRequest<
    ExpenseResponse
  >(
    "/finance/expenses/actions/record",
    {
      method: "POST",
      body: payload,
    },
  );
}


export async function createExpense(
  payload: CreateExpensePayload,
): Promise<ExpenseResponse> {
  return authenticatedApiRequest<
    ExpenseResponse
  >(
    "/finance/expenses",
    {
      method: "POST",
      body: payload,
    },
  );
}

export async function updateExpense(
  expenseId: string,
  payload: UpdateExpensePayload,
): Promise<ExpenseResponse> {
  return authenticatedApiRequest<
    ExpenseResponse
  >(
    `/finance/expenses/${expenseId}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
}

export async function submitExpense(
  expenseId: string,
): Promise<ExpenseResponse> {
  return authenticatedApiRequest<
    ExpenseResponse
  >(
    `/finance/expenses/${expenseId}/actions/submit`,
    {
      method: "POST",
      body: {},
    },
  );
}

export async function approveExpense(
  expenseId: string,
): Promise<ExpenseResponse> {
  return authenticatedApiRequest<
    ExpenseResponse
  >(
    `/finance/expenses/${expenseId}/actions/approve`,
    {
      method: "POST",
      body: {},
    },
  );
}

export async function rejectExpense(
  expenseId: string,
  reason: string,
): Promise<ExpenseResponse> {
  return authenticatedApiRequest<
    ExpenseResponse
  >(
    `/finance/expenses/${expenseId}/actions/reject`,
    {
      method: "POST",
      body: {
        reason,
      },
    },
  );
}

export async function reviseExpense(
  expenseId: string,
): Promise<ExpenseResponse> {
  return authenticatedApiRequest<
    ExpenseResponse
  >(
    `/finance/expenses/${expenseId}/actions/revise`,
    {
      method: "POST",
      body: {},
    },
  );
}

export async function postExpense(
  expenseId: string,
): Promise<ExpenseResponse> {
  return authenticatedApiRequest<
    ExpenseResponse
  >(
    `/finance/expenses/${expenseId}/actions/post`,
    {
      method: "POST",
      body: {},
    },
  );
}

export async function voidExpense(
  expenseId: string,
  reason: string,
): Promise<ExpenseResponse> {
  return authenticatedApiRequest<
    ExpenseResponse
  >(
    `/finance/expenses/${expenseId}/actions/void`,
    {
      method: "POST",
      body: {
        reason,
      },
    },
  );
}

export async function uploadExpenseEvidence(
  expenseId: string,
  file: File,
): Promise<ExpenseResponse> {
  const body =
    new FormData();

  body.append(
    "evidence",
    file,
  );

  return authenticatedApiRequest<
    ExpenseResponse
  >(
    `/finance/expenses/${expenseId}/evidence`,
    {
      method: "POST",
      body,
    },
  );
}

export async function getExpenseEvidence(
  expenseId: string,
): Promise<Blob> {
  return authenticatedApiBlobRequest(
    `/finance/expenses/${expenseId}/evidence`,
  );
}

export async function removeExpenseEvidence(
  expenseId: string,
): Promise<ExpenseResponse> {
  return authenticatedApiRequest<
    ExpenseResponse
  >(
    `/finance/expenses/${expenseId}/evidence`,
    {
      method: "DELETE",
    },
  );
}
