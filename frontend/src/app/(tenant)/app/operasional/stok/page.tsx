import {
  InventoryStockWorkspace,
} from "@/components/inventory/inventory-stock-workspace";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";


export default function InventoryStockPage() {
  return (
    <TenantShell>
      <InventoryStockWorkspace />
    </TenantShell>
  );
}
