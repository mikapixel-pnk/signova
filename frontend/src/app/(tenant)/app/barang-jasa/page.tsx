import { ModulePlaceholder } from "@/components/layout/module-placeholder";
import { TenantShell } from "@/components/layout/tenant-shell";

export default function Page() {
  return (
    <TenantShell>
      <ModulePlaceholder
        title="Barang & Jasa"
        description="Kelola barang, jasa, kategori, satuan, dan harga usaha."
      />
    </TenantShell>
  );
}
