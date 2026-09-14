import { ModulePlaceholder } from "@/components/layout/module-placeholder";
import { TenantShell } from "@/components/layout/tenant-shell";

export default function Page() {
  return (
    <TenantShell>
      <ModulePlaceholder
        title="Pengaturan"
        description="Atur profil usaha dan preferensi SIGNOVA."
      />
    </TenantShell>
  );
}
