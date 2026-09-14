import type {
  CustomerStatus,
  CustomerType,
} from "@/types/customer";

export function customerTypeLabel(
  value: CustomerType,
): string {
  return value === "COMPANY"
    ? "Perusahaan"
    : "Perorangan";
}

export function customerStatusLabel(
  value: CustomerStatus,
): string {
  return value === "ACTIVE"
    ? "Aktif"
    : "Nonaktif";
}
