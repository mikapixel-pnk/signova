import {
  InventoryWorkspace,
} from "@/components/inventory/inventory-workspace";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";


export default function InventoryMaterialsPage() {
  return (
    <TenantShell>
      <InventoryWorkspace
        section="MATERIALS"
      />
    </TenantShell>
  );
}
