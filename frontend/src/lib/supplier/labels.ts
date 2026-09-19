import type {
  SupplierStatus,
} from "@/types/supplier";

export function supplierStatusLabel(
  status: SupplierStatus,
): string {
  return status === "ACTIVE"
    ? "Aktif"
    : "Nonaktif";
}
