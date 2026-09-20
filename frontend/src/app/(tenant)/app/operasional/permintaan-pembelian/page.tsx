import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  PurchaseRequestList,
} from "@/components/purchasing/purchase-request-list";

export default function PurchaseRequestsPage() {
  return (
    <TenantShell>
      <PurchaseRequestList />
    </TenantShell>
  );
}
