import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  SupplierForm,
} from "@/components/supplier/supplier-form";

export default function Page() {
  return (
    <TenantShell>
      <SupplierForm />
    </TenantShell>
  );
}
