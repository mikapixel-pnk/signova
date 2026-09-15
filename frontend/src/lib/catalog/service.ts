import {
  authenticatedApiRequest,
} from "@/lib/api/client";

import type {
  CatalogCategoryCreatePayload,
  CatalogCategoryListResponse,
  CatalogCategoryResponse,
  CatalogCategoryUpdatePayload,
  CatalogItemCreatePayload,
  CatalogItemListResponse,
  CatalogItemResponse,
  CatalogItemType,
  CatalogItemUpdatePayload,
  CatalogPricingMethod,
  CatalogStatus,
  CatalogUnitCreatePayload,
  CatalogUnitListResponse,
  CatalogUnitResponse,
  CatalogUnitUpdatePayload,
} from "@/types/catalog";

type ListCatalogItemsParams = {
  search?:
    string;

  status?:
    CatalogStatus;

  type?:
    CatalogItemType;

  pricing_method?:
    CatalogPricingMethod;

  page?:
    number;
};

function queryString(
  params:
    ListCatalogItemsParams,
): string {
  const query =
    new URLSearchParams();

  if (
    params.search?.trim()
  ) {
    query.set(
      "search",
      params.search.trim(),
    );
  }

  if (
    params.status
  ) {
    query.set(
      "status",
      params.status,
    );
  }

  if (
    params.type
  ) {
    query.set(
      "type",
      params.type,
    );
  }

  if (
    params.pricing_method
  ) {
    query.set(
      "pricing_method",
      params.pricing_method,
    );
  }

  if (
    params.page
  ) {
    query.set(
      "page",
      String(
        params.page,
      ),
    );
  }

  const value =
    query.toString();

  return value
    ? `?${value}`
    : "";
}

export async function listCatalogItems(
  params:
    ListCatalogItemsParams = {},
): Promise<
  CatalogItemListResponse
> {
  return authenticatedApiRequest<
    CatalogItemListResponse
  >(
    `/catalog/items${queryString(
      params,
    )}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function getCatalogItem(
  id: string,
): Promise<
  CatalogItemResponse
> {
  return authenticatedApiRequest<
    CatalogItemResponse
  >(
    `/catalog/items/${id}`,
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function createCatalogItem(
  payload:
    CatalogItemCreatePayload,
): Promise<
  CatalogItemResponse
> {
  return authenticatedApiRequest<
    CatalogItemResponse
  >(
    "/catalog/items",
    {
      method: "POST",
      body: payload,
    },
  );
}

export async function updateCatalogItem(
  id: string,
  payload:
    CatalogItemUpdatePayload,
): Promise<
  CatalogItemResponse
> {
  return authenticatedApiRequest<
    CatalogItemResponse
  >(
    `/catalog/items/${id}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
}

export async function listCatalogCategories():
  Promise<
    CatalogCategoryListResponse
  > {
  return authenticatedApiRequest<
    CatalogCategoryListResponse
  >(
    "/catalog/categories",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function createCatalogCategory(
  payload:
    CatalogCategoryCreatePayload,
): Promise<
  CatalogCategoryResponse
> {
  return authenticatedApiRequest<
    CatalogCategoryResponse
  >(
    "/catalog/categories",
    {
      method: "POST",
      body: payload,
    },
  );
}

export async function updateCatalogCategory(
  id: string,
  payload:
    CatalogCategoryUpdatePayload,
): Promise<
  CatalogCategoryResponse
> {
  return authenticatedApiRequest<
    CatalogCategoryResponse
  >(
    `/catalog/categories/${id}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
}

export async function listCatalogUnits():
  Promise<
    CatalogUnitListResponse
  > {
  return authenticatedApiRequest<
    CatalogUnitListResponse
  >(
    "/units",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}


export async function createCatalogUnit(
  payload:
    CatalogUnitCreatePayload,
): Promise<
  CatalogUnitResponse
> {
  return authenticatedApiRequest<
    CatalogUnitResponse
  >(
    "/units",
    {
      method: "POST",
      body: payload,
    },
  );
}

export async function updateCatalogUnit(
  id: string,
  payload:
    CatalogUnitUpdatePayload,
): Promise<
  CatalogUnitResponse
> {
  return authenticatedApiRequest<
    CatalogUnitResponse
  >(
    `/units/${id}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
}
