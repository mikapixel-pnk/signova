import {
  IncomeRegister,
} from "@/components/finance/income-register";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";


export default function Page() {
  return (
    <TenantShell>
      <IncomeRegister />
    </TenantShell>
  );
}
