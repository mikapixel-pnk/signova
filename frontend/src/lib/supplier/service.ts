import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  SupplierListResponse,
  SupplierPayload,
  SupplierResponse,
  SupplierStatus,
} from "@/types/supplier";

type ListSuppliersParams = {
  search?: string;
  status?: SupplierStatus;
  page?: number;
};

function queryString(
  params: ListSuppliersParams,
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

export async function listSuppliers(
  params:
    ListSuppliersParams = {},
): Promise<SupplierListResponse> {
  return authenticatedApiRequest<
    SupplierListResponse
  >(
    `/suppliers${queryString(params)}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function getSupplier(
  id: string,
): Promise<SupplierResponse> {
  return authenticatedApiRequest<
    SupplierResponse
  >(
    `/suppliers/${id}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function createSupplier(
  payload: SupplierPayload,
): Promise<SupplierResponse> {
  return authenticatedApiRequest<
    SupplierResponse
  >(
    "/suppliers",
    {
      method: "POST",
      body: payload,
    },
  );
}

export async function updateSupplier(
  id: string,
  payload: SupplierPayload,
): Promise<SupplierResponse> {
  return authenticatedApiRequest<
    SupplierResponse
  >(
    `/suppliers/${id}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
}
