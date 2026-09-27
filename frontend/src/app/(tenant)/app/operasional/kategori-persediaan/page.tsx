import {
  InventoryWorkspace,
} from "@/components/inventory/inventory-workspace";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";


export default function InventoryCategoriesPage() {
  return (
    <TenantShell>
      <InventoryWorkspace
        section="CATEGORIES"
      />
    </TenantShell>
  );
}
