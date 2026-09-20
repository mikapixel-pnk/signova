import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  ModulePage,
} from "@/components/module/module-page";

export default function PurchaseOrdersPage() {
  return (
    <TenantShell>
      <ModulePage moduleKey="purchase-orders" />
    </TenantShell>
  );
}
