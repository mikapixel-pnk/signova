import type {
  ApiMeta,
} from "@/lib/api/types";

export type BusinessProfile = {
  id: string;
  name: string;
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
  has_logo: boolean;
  logo_file_id: string | null;
  is_default: boolean;
  status: "ACTIVE" | "INACTIVE";
  created_at: string | null;
  updated_at: string | null;
};

export type BusinessProfilePayload = {
  name: string;
  legal_name?: string | null;
  address?: string | null;
  city?: string | null;
  province?: string | null;
  postal_code?: string | null;
  phone?: string | null;
  whatsapp?: string | null;
  email?: string | null;
  website?: string | null;
  tax_id?: string | null;
};

export type BusinessProfileResponse = {
  success: true;
  data: BusinessProfile;
  meta: ApiMeta;
  message?: string;
};
