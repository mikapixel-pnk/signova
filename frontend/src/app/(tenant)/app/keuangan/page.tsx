import { ModulePlaceholder } from "@/components/layout/module-placeholder";
import { TenantShell } from "@/components/layout/tenant-shell";

export default function Page() {
  return (
    <TenantShell>
      <ModulePlaceholder
        title="Keuangan"
        description="Pantau kas, pemasukan, pengeluaran, dan piutang usaha."
      />
    </TenantShell>
  );
}
