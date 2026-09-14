import { ModulePlaceholder } from "@/components/layout/module-placeholder";
import { TenantShell } from "@/components/layout/tenant-shell";

export default function CustomersPage() {
  return (
    <TenantShell>
      <ModulePlaceholder
        title="Pelanggan"
        description="Kelola data pelanggan dan riwayat transaksi usaha Anda."
      />
    </TenantShell>
  );
}
