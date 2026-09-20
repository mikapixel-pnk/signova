import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  ModulePage,
} from "@/components/module/module-page";

export default function PurchaseRequestsPage() {
  return (
    <TenantShell>
      <ModulePage moduleKey="purchase-requests" />
    </TenantShell>
  );
}
