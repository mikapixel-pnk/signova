import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  CashAccountListResponse,
  CashAccountPayload,
  CashAccountResponse,
} from "@/types/cash-account";

export async function listCashAccounts():
Promise<CashAccountListResponse> {
  return authenticatedApiRequest<
    CashAccountListResponse
  >(
    "/finance/cash-accounts?per_page=100",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function createCashAccount(
  payload: CashAccountPayload,
): Promise<CashAccountResponse> {
  return authenticatedApiRequest<
    CashAccountResponse
  >(
    "/finance/cash-accounts",
    {
      method: "POST",
      body: payload,
    },
  );
}

export async function updateCashAccount(
  cashAccountId: string,
  payload: CashAccountPayload,
): Promise<CashAccountResponse> {
  return authenticatedApiRequest<
    CashAccountResponse
  >(
    `/finance/cash-accounts/${cashAccountId}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
}

export async function activateCashAccount(
  cashAccountId: string,
): Promise<CashAccountResponse> {
  return authenticatedApiRequest<
    CashAccountResponse
  >(
    `/finance/cash-accounts/${cashAccountId}/actions/activate`,
    {
      method: "POST",
    },
  );
}

export async function deactivateCashAccount(
  cashAccountId: string,
): Promise<CashAccountResponse> {
  return authenticatedApiRequest<
    CashAccountResponse
  >(
    `/finance/cash-accounts/${cashAccountId}/actions/deactivate`,
    {
      method: "POST",
    },
  );
}

export async function setDefaultCashAccount(
  cashAccountId: string,
): Promise<CashAccountResponse> {
  return authenticatedApiRequest<
    CashAccountResponse
  >(
    `/finance/cash-accounts/${cashAccountId}/actions/set-default`,
    {
      method: "POST",
    },
  );
}
