import { ModulePlaceholder } from "@/components/layout/module-placeholder";
import { TenantShell } from "@/components/layout/tenant-shell";

export default function Page() {
  return (
    <TenantShell>
      <ModulePlaceholder
        title="Pembayaran"
        description="Kelola pembayaran pelanggan dan status verifikasinya."
      />
    </TenantShell>
  );
}
