"use client";

import {
  useParams,
} from "next/navigation";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  PurchaseRequestForm,
} from "@/components/purchasing/purchase-request-form";

export default function EditPurchaseRequestPage() {
  const params =
    useParams<{
      id: string;
    }>();

  return (
    <TenantShell>
      <PurchaseRequestForm
        purchaseRequestId={
          params.id
        }
      />
    </TenantShell>
  );
}
