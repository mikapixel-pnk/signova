import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  PurchaseOrderForm,
} from "@/components/purchasing/purchase-order-form";


type NewPurchaseOrderPageProps = {
  searchParams:
    Promise<{
      source_request?:
        | string
        | string[];
    }>;
};


export default async function NewPurchaseOrderPage({
  searchParams,
}: NewPurchaseOrderPageProps) {
  const params =
    await searchParams;

  const rawSource =
    params.source_request;

  const sourcePurchaseRequestId =
    typeof rawSource === "string"
      ? rawSource
      : undefined;

  return (
    <TenantShell>
      <PurchaseOrderForm
        sourcePurchaseRequestId={
          sourcePurchaseRequestId
        }
      />
    </TenantShell>
  );
}
