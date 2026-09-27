import type {
  InventoryStatus,
  InventoryStockTracking,
  MaterialInventoryType,
} from "@/types/inventory";


export const inventoryStatusLabels:
  Record<
    InventoryStatus,
    string
  > = {
    ACTIVE: "Aktif",
    INACTIVE: "Nonaktif",
  };


export const inventoryTypeLabels:
  Record<
    MaterialInventoryType,
    string
  > = {
    RAW_MATERIAL:
      "Bahan Baku",

    COMPONENT:
      "Komponen",

    CONSUMABLE:
      "Bahan Habis Pakai",

    RESALE:
      "Barang Dijual Kembali",

    FINISHED_GOOD:
      "Barang Jadi",
  };


export const stockTrackingLabels:
  Record<
    InventoryStockTracking,
    string
  > = {
    TRACKED:
      "Stok dipantau",

    NOT_TRACKED:
      "Stok tidak dipantau",
  };
