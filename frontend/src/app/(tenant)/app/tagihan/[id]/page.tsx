import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  InvoiceDetail,
} from "@/components/invoice/invoice-detail";

export default function Page() {
  return (
    <TenantShell>
      <InvoiceDetail />
    </TenantShell>
  );
}
