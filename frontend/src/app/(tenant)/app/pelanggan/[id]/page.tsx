import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  CustomerDetail,
} from "@/components/customer/customer-detail";

export default function Page() {
  return (
    <TenantShell>
      <CustomerDetail />
    </TenantShell>
  );
}
