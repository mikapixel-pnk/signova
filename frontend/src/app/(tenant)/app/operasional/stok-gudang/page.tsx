import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  ModulePage,
} from "@/components/module/module-page";

export default function InventoryPage() {
  return (
    <TenantShell>
      <ModulePage moduleKey="inventory" />
    </TenantShell>
  );
}
