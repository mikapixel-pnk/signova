export type DocumentSignatureImage = {
  id: string;
  mime_type: string;
  size_bytes: number;
  original_name: string | null;
};

export type DocumentSettings = {
  business_name: string | null;
  legal_name: string | null;
  address: string | null;
  city: string | null;
  province: string | null;
  postal_code: string | null;
  phone: string | null;
  whatsapp: string | null;
  email: string | null;
  website: string | null;
  tax_id: string | null;

  quotation_opening_text:
    string | null;

  quotation_closing_text:
    string | null;

  quotation_default_terms:
    string | null;

  quotation_default_validity_days:
    number | null;

  invoice_footnote:
    string | null;

  signature_name:
    string | null;

  signature_title:
    string | null;

  has_signature_image:
    boolean;

  signature_image:
    DocumentSignatureImage | null;
};

export type DocumentSettingsPayload = {
  quotation_opening_text:
    string | null;

  quotation_closing_text:
    string | null;

  quotation_default_terms:
    string | null;

  quotation_default_validity_days:
    number | null;

  invoice_footnote:
    string | null;

  signature_name:
    string | null;

  signature_title:
    string | null;
};

export type DocumentSettingsResponse = {
  success: boolean;
  data: DocumentSettings;
  message?: string;
};

export type InvoiceTemplateOption = {
  key: string;
  name: string;
  tier: string;
  layout: string;
  version: number;
  default_palette: string;
  palettes: string[];
  is_available: boolean;
  is_selected: boolean;
};

export type InvoiceTemplateCatalog = {
  templates:
    InvoiceTemplateOption[];

  selected: {
    template_key: string;
    palette_key: string;
  };
};

export type InvoiceTemplateCatalogResponse = {
  success: boolean;
  data: InvoiceTemplateCatalog;
  message?: string;
};

export type InvoiceTemplatePayload = {
  invoice_template_key:
    string;

  invoice_palette_key:
    string | null;
};
