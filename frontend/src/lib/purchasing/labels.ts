import {
  formatIdrDecimal,
} from "@/lib/format/decimal";

import type {
  ProcurementType,
  PurchaseOrderStatus,
  PurchaseRequestStatus,
} from "@/types/purchasing";

export function purchaseRequestStatusLabel(
  status: PurchaseRequestStatus,
): string {
  switch (status) {
    case "DRAFT":
      return "Draf";

    case "SUBMITTED":
      return "Diajukan";

    case "APPROVED":
      return "Disetujui";

    case "REJECTED":
      return "Ditolak";

    case "CANCELLED":
      return "Dibatalkan";
  }
}

export function purchaseRequestStatusDescription(
  status: PurchaseRequestStatus,
): string {
  switch (status) {
    case "DRAFT":
      return "Permintaan masih dapat diubah sebelum diajukan.";

    case "SUBMITTED":
      return "Permintaan sedang menunggu keputusan pihak yang berwenang.";

    case "APPROVED":
      return "Permintaan telah disetujui dan dapat dilanjutkan ke Pesanan Pembelian.";

    case "REJECTED":
      return "Permintaan perlu diperbaiki sebelum diajukan kembali.";

    case "CANCELLED":
      return "Permintaan telah dibatalkan dan tidak dapat dilanjutkan.";
  }
}

export function purchaseOrderStatusLabel(
  status: PurchaseOrderStatus,
): string {
  switch (status) {
    case "DRAFT":
      return "Draf";

    case "ISSUED":
      return "Diterbitkan";

    case "PARTIALLY_RECEIVED":
      return "Diterima Sebagian";

    case "RECEIVED":
      return "Diterima Lengkap";

    case "CANCELLED":
      return "Dibatalkan";
  }
}

export function purchaseOrderStatusDescription(
  status: PurchaseOrderStatus,
): string {
  switch (status) {
    case "DRAFT":
      return "Pesanan masih dapat diperiksa dan diubah sebelum diterbitkan.";

    case "ISSUED":
      return "Pesanan telah diterbitkan dan siap dilanjutkan ke Penerimaan.";

    case "PARTIALLY_RECEIVED":
      return "Sebagian barang atau jasa telah diterima. Catat penerimaan berikutnya.";

    case "RECEIVED":
      return "Seluruh jumlah pada Pesanan Pembelian telah diterima.";

    case "CANCELLED":
      return "Pesanan telah dibatalkan dan tidak dapat dilanjutkan.";
  }
}


export function procurementTypeLabel(
  type: ProcurementType,
): string {
  switch (type) {
    case "INVENTORY_ITEM":
      return "Item Persediaan";

    case "NON_STOCK_GOOD":
      return "Barang Non-Stok";

    case "SERVICE":
      return "Jasa Vendor";
  }
}


export function purchaseItemTypeLabel(
  type:
    | "PRODUCT"
    | "SERVICE",
): string {
  return type === "PRODUCT"
    ? "Barang"
    : "Jasa";
}

export function formatPurchaseMoney(
  value:
    | string
    | null
    | undefined,
): string {
  return formatIdrDecimal(
    value,
  );
}

export function formatPurchaseDate(
  value:
    | string
    | null
    | undefined,
): string {
  if (!value) {
    return "-";
  }

  const date =
    new Date(
      `${value.slice(0, 10)}T00:00:00`,
    );

  if (
    Number.isNaN(
      date.getTime(),
    )
  ) {
    return value;
  }

  return new Intl.DateTimeFormat(
    "id-ID",
    {
      day: "2-digit",
      month: "short",
      year: "numeric",
    },
  ).format(date);
}
