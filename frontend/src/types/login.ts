import type {
  AccessContext,
  AuthUser,
  PlatformAccess,
  TenantAccess,
} from "@/types/auth";

export type LoginPayload = {
  email: string;
  password: string;
  device_name?: string;
};

export type LoginData = {
  user: AuthUser;

  tenant:
    | TenantAccess
    | null;

  tenants: TenantAccess[];

  requires_tenant_selection: boolean;

  access: {
    platform: PlatformAccess;
    tenants: TenantAccess[];
  };

  default_context:
    | AccessContext
    | null;

  requires_context_selection: boolean;

  token: string;
  token_type: "Bearer";
};

export type LoginResponse = {
  message: string;
  data: LoginData;
};
