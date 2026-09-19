import type {
  ApiMeta,
} from "@/lib/api/types";

export type SupplierStatus =
  | "ACTIVE"
  | "INACTIVE";

export type Supplier = {
  id: string;
  code: string | null;
  name: string;
  contact_name: string | null;
  phone: string | null;
  email: string | null;
  tax_id: string | null;
  address: string | null;
  city: string | null;
  province: string | null;
  payment_terms_days: number | null;
  notes: string | null;
  status: SupplierStatus;
  created_at: string | null;
  updated_at: string | null;
};

export type SupplierListResponse = {
  success: true;
  data: Supplier[];
  meta: ApiMeta & {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
};

export type SupplierResponse = {
  success: true;
  data: Supplier;
  meta: ApiMeta;
  message?: string;
};

export type SupplierPayload = {
  code?: string | null;
  name: string;
  contact_name?: string | null;
  phone?: string | null;
  email?: string | null;
  tax_id?: string | null;
  address?: string | null;
  city?: string | null;
  province?: string | null;
  payment_terms_days?: number;
  notes?: string | null;
  status?: SupplierStatus;
};
