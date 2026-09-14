import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  CustomerListResponse,
  CustomerPayload,
  CustomerResponse,
  CustomerStatus,
} from "@/types/customer";

type ListCustomersParams = {
  search?: string;
  status?: CustomerStatus;
  page?: number;
};

function queryString(
  params: ListCustomersParams,
): string {
  const query =
    new URLSearchParams();

  if (params.search?.trim()) {
    query.set(
      "search",
      params.search.trim(),
    );
  }

  if (params.status) {
    query.set(
      "status",
      params.status,
    );
  }

  if (params.page) {
    query.set(
      "page",
      String(params.page),
    );
  }

  const value =
    query.toString();

  return value
    ? `?${value}`
    : "";
}

export async function listCustomers(
  params:
    ListCustomersParams = {},
): Promise<CustomerListResponse> {
  return authenticatedApiRequest<
    CustomerListResponse
  >(
    `/customers${queryString(params)}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function getCustomer(
  id: string,
): Promise<CustomerResponse> {
  return authenticatedApiRequest<
    CustomerResponse
  >(
    `/customers/${id}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function createCustomer(
  payload: CustomerPayload,
): Promise<CustomerResponse> {
  return authenticatedApiRequest<
    CustomerResponse
  >(
    "/customers",
    {
      method: "POST",
      body: payload,
    },
  );
}

export async function updateCustomer(
  id: string,
  payload: CustomerPayload,
): Promise<CustomerResponse> {
  return authenticatedApiRequest<
    CustomerResponse
  >(
    `/customers/${id}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
}
