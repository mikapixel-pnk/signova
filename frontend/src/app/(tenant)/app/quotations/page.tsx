import { ModulePlaceholder } from "@/components/layout/module-placeholder";
import { TenantShell } from "@/components/layout/tenant-shell";

export default function Page() {
  return (
    <TenantShell>
      <ModulePlaceholder
        title="Penawaran"
        description="Buat dan pantau penawaran untuk pelanggan."
      />
    </TenantShell>
  );
}
