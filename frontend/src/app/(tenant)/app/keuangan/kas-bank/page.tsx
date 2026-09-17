import {
  CashBank,
} from "@/components/finance/cash-bank";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";

export default function Page() {
  return (
    <TenantShell>
      <CashBank />
    </TenantShell>
  );
}
