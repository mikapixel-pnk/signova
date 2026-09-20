"use client";

import {
  useParams,
} from "next/navigation";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  PurchaseRequestDetail,
} from "@/components/purchasing/purchase-request-detail";

export default function PurchaseRequestDetailPage() {
  const params =
    useParams<{
      id: string;
    }>();

  return (
    <TenantShell>
      <PurchaseRequestDetail
        purchaseRequestId={
          params.id
        }
      />
    </TenantShell>
  );
}
