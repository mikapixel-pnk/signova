import { ModulePlaceholder } from "@/components/layout/module-placeholder";
import { TenantShell } from "@/components/layout/tenant-shell";

export default function Page() {
  return (
    <TenantShell>
      <ModulePlaceholder
        title="Tagihan"
        description="Buat, pantau, dan kelola tagihan pelanggan."
      />
    </TenantShell>
  );
}
