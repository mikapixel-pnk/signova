"use client";

import {
  useParams,
} from "next/navigation";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  PurchaseOrderForm,
} from "@/components/purchasing/purchase-order-form";


export default function EditPurchaseOrderPage() {
  const params =
    useParams<{
      id: string;
    }>();

  return (
    <TenantShell>
      <PurchaseOrderForm
        purchaseOrderId={
          params.id
        }
      />
    </TenantShell>
  );
}
