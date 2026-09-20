import type {
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

export function formatPurchaseMoney(
  value:
    | string
    | number
    | null
    | undefined,
): string {
  const numeric =
    Number(value ?? 0);

  if (!Number.isFinite(numeric)) {
    return "Rp0";
  }

  return new Intl.NumberFormat(
    "id-ID",
    {
      style: "currency",
      currency: "IDR",
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    },
  ).format(numeric);
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
