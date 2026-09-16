import {
  authenticatedApiBlobRequest,
  authenticatedApiRequest,
  authenticatedApiTextRequest,
} from "@/lib/api/client";

import type {
  DocumentSettingsPayload,
  DocumentSettingsResponse,
  InvoiceTemplateCatalogResponse,
  InvoiceTemplatePayload,
} from "@/types/document-settings";

export async function getDocumentSettings():
Promise<DocumentSettingsResponse> {
  return authenticatedApiRequest<
    DocumentSettingsResponse
  >(
    "/settings/document",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function updateDocumentSettings(
  payload:
    DocumentSettingsPayload,
): Promise<DocumentSettingsResponse> {
  return authenticatedApiRequest<
    DocumentSettingsResponse
  >(
    "/settings/document",
    {
      method: "PATCH",
      body: payload,
    },
  );
}

export async function uploadDocumentSignature(
  file: File,
): Promise<DocumentSettingsResponse> {
  const body =
    new FormData();

  body.append(
    "signature",
    file,
  );

  return authenticatedApiRequest<
    DocumentSettingsResponse
  >(
    "/settings/document/signature",
    {
      method: "POST",
      body,
    },
  );
}

export async function deleteDocumentSignature():
Promise<DocumentSettingsResponse> {
  return authenticatedApiRequest<
    DocumentSettingsResponse
  >(
    "/settings/document/signature",
    {
      method: "DELETE",
    },
  );
}

export async function getDocumentSignature():
Promise<Blob> {
  return authenticatedApiBlobRequest(
    "/settings/document/signature",
  );
}

export async function getInvoiceTemplateCatalog():
Promise<InvoiceTemplateCatalogResponse> {
  return authenticatedApiRequest<
    InvoiceTemplateCatalogResponse
  >(
    "/settings/invoice-templates",
    {
      method: "GET",
      cache: "no-store",
    },
  );
}

export async function updateInvoiceTemplate(
  payload:
    InvoiceTemplatePayload,
): Promise<InvoiceTemplateCatalogResponse> {
  return authenticatedApiRequest<
    InvoiceTemplateCatalogResponse
  >(
    "/settings/invoice-templates",
    {
      method: "PATCH",
      body: payload,
    },
  );
}

export async function getInvoiceTemplatePreview(
  templateKey: string,
  paletteKey:
    string | null,
): Promise<string> {
  const query =
    paletteKey
      ? `?palette=${encodeURIComponent(
          paletteKey,
        )}`
      : "";

  return authenticatedApiTextRequest(
    `/settings/invoice-templates/${encodeURIComponent(
      templateKey,
    )}/preview${query}`,
  );
}
