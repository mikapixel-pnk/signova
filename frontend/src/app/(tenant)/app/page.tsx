import { TenantHome } from "@/components/dashboard/tenant-home";
import { TenantShell } from "@/components/layout/tenant-shell";

export default function TenantAppPage() {
  return (
    <TenantShell>
      <TenantHome />
    </TenantShell>
  );
}
