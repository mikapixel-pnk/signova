import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  SupplierEditScreen,
} from "@/components/supplier/supplier-edit-screen";

export default function Page() {
  return (
    <TenantShell>
      <SupplierEditScreen />
    </TenantShell>
  );
}
