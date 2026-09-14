import type {
  AuthUser,
  TenantAccess,
} from "@/types/auth";

export type RegisterPayload = {
  name: string;
  email: string;
  phone?: string | null;

  password: string;
  password_confirmation: string;

  tenant_name: string;

  timezone?: string | null;
  locale?: string | null;
};

export type RegisterData = {
  user: AuthUser;

  tenant:
    | TenantAccess
    | null;

  token: string;
  token_type: "Bearer";
};

export type RegisterResponse = {
  message: string;
  data: RegisterData;
};
