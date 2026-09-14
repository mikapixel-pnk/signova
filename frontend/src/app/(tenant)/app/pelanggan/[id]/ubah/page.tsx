import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  CustomerEditScreen,
} from "@/components/customer/customer-edit-screen";

export default function Page() {
  return (
    <TenantShell>
      <CustomerEditScreen />
    </TenantShell>
  );
}
