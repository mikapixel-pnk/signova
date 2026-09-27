import {
  InventoryWorkspace,
} from "@/components/inventory/inventory-workspace";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";


export default function InventoryWarehousesPage() {
  return (
    <TenantShell>
      <InventoryWorkspace
        section="WAREHOUSES"
      />
    </TenantShell>
  );
}
