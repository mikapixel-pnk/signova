import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  QuotationDetail,
} from "@/components/quotation/quotation-detail";

export default function Page() {
  return (
    <TenantShell>
      <QuotationDetail />
    </TenantShell>
  );
}
