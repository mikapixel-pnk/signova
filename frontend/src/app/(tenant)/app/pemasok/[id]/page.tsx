import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  SupplierDetail,
} from "@/components/supplier/supplier-detail";

export default function Page() {
  return (
    <TenantShell>
      <SupplierDetail />
    </TenantShell>
  );
}
