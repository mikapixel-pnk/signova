export type AuthUser = {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  auth_status: string;
};

export type TenantAccess = {
  id: string;
  name: string;
  slug: string;
  lifecycle_status: string;
  timezone: string;
  locale: string;
};

export type PlatformAccess = {
  available: boolean;
  capabilities: string[];
};

export type AccessContext =
  | {
      type: "PLATFORM";
      tenant_id: null;
    }
  | {
      type: "TENANT";
      tenant_id: string;
    };

export type AuthContextData = {
  user: AuthUser;

  access: {
    platform: PlatformAccess;
    tenants: TenantAccess[];
  };

  default_context:
    | AccessContext
    | null;

  requires_context_selection: boolean;
  has_access: boolean;
};
