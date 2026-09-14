import { ModulePlaceholder } from "@/components/layout/module-placeholder";
import { TenantShell } from "@/components/layout/tenant-shell";

export default function Page() {
  return (
    <TenantShell>
      <ModulePlaceholder
        title="Pengeluaran"
        description="Catat biaya dan pengeluaran usaha."
      />
    </TenantShell>
  );
}
