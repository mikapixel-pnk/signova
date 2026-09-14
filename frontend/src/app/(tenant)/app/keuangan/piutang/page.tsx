import { ModulePlaceholder } from "@/components/layout/module-placeholder";
import { TenantShell } from "@/components/layout/tenant-shell";

export default function Page() {
  return (
    <TenantShell>
      <ModulePlaceholder
        title="Piutang"
        description="Pantau tagihan pelanggan yang belum lunas."
      />
    </TenantShell>
  );
}
