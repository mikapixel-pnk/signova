import type {
  ApiMeta,
} from "@/lib/api/types";

export type CustomerStatus =
  | "ACTIVE"
  | "INACTIVE";

export type CustomerType =
  | "COMPANY"
  | "INDIVIDUAL";

export type Customer = {
  id: string;
  type: CustomerType;
  code: string | null;
  name: string;
  phone: string | null;
  email: string | null;
  tax_id: string | null;
  payment_terms_days: number | null;
  notes: string | null;
  status: CustomerStatus;
  created_at: string | null;
  updated_at: string | null;
};

export type CustomerListResponse = {
  success: true;
  data: Customer[];
  meta: ApiMeta & {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
};

export type CustomerResponse = {
  success: true;
  data: Customer;
  meta: ApiMeta;
  message?: string;
};

export type CustomerPayload = {
  type?: CustomerType;
  code?: string | null;
  name: string;
  phone?: string | null;
  email?: string | null;
  tax_id?: string | null;
  payment_terms_days?: number;
  notes?: string | null;
  status?: CustomerStatus;
};
