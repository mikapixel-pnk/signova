import {
  ExpenseRegister,
} from "@/components/finance/expense-register";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";

export default function Page() {
  return (
    <TenantShell>
      <ExpenseRegister />
    </TenantShell>
  );
}
