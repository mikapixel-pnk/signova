import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  PurchaseRequestForm,
} from "@/components/purchasing/purchase-request-form";

export default function NewPurchaseRequestPage() {
  return (
    <TenantShell>
      <PurchaseRequestForm />
    </TenantShell>
  );
}
