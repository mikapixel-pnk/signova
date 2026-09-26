import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  PurchaseOrderList,
} from "@/components/purchasing/purchase-order-list";

export default function PurchaseOrdersPage() {
  return (
    <TenantShell>
      <PurchaseOrderList />
    </TenantShell>
  );
}
