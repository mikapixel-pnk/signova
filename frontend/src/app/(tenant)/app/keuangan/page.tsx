import {
  FinanceHub,
} from "@/components/finance/finance-hub";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";

export default function Page() {
  return (
    <TenantShell>
      <FinanceHub />
    </TenantShell>
  );
}
