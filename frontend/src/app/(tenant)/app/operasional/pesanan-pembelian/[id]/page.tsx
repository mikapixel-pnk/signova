"use client";

import {
  useParams,
} from "next/navigation";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  PurchaseOrderDetail,
} from "@/components/purchasing/purchase-order-detail";


export default function PurchaseOrderDetailPage() {
  const params =
    useParams<{
      id: string;
    }>();

  return (
    <TenantShell>
      <PurchaseOrderDetail
        purchaseOrderId={
          params.id
        }
      />
    </TenantShell>
  );
}
