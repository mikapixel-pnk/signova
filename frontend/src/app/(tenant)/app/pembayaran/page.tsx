import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  ModulePage,
} from "@/components/module/module-page";

export default function Page() {
  return (
    <TenantShell>
      <ModulePage
        moduleKey="payments"
      />
    </TenantShell>
  );
}
