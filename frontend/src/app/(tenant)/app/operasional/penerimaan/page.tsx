import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  ModulePage,
} from "@/components/module/module-page";

export default function GoodsReceiptsPage() {
  return (
    <TenantShell>
      <ModulePage moduleKey="goods-receipts" />
    </TenantShell>
  );
}
