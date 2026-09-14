export const offlinePolicy = {
  safeBootstrapFields: [
    "user_display_name",
    "context_type",
    "tenant_id",
    "tenant_name",
    "capability_codes",
    "bootstrap_synced_at",
    "app_schema_version",
  ],

  neverPersist: [
    "password",
    "otp",
    "access_token",
    "ana_access_token",
    "ana_refresh_token",
    "provider_secret",
  ],

  serverConfirmedActions: [
    "invoice.issue",
    "invoice.void",
    "payment.verify",
    "payment.reverse",
    "finance.finalize",
    "platform.privileged_action",
  ],
} as const;
