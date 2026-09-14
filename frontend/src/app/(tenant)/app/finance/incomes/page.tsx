import { ModulePlaceholder } from "@/components/layout/module-placeholder";
import { TenantShell } from "@/components/layout/tenant-shell";

export default function Page() {
  return (
    <TenantShell>
      <ModulePlaceholder
        title="Pemasukan"
        description="Catat dan pantau pemasukan usaha."
      />
    </TenantShell>
  );
}
