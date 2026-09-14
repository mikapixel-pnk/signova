import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  CustomerCreateForm,
} from "@/components/customer/customer-create-form";

export default function Page() {
  return (
    <TenantShell>
      <CustomerCreateForm />
    </TenantShell>
  );
}
