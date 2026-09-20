import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  ModulePage,
} from "@/components/module/module-page";

export default function PayablesPage() {
  return (
    <TenantShell>
      <ModulePage moduleKey="payables" />
    </TenantShell>
  );
}
