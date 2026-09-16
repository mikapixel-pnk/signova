import {
  ApiClientError,
} from "@/lib/api/types";

export function apiErrorMessage(
  error: unknown,
  fallback =
    "Data belum berhasil diproses. Silakan coba lagi.",
): string {
  if (
    !(
      error instanceof
      ApiClientError
    )
  ) {
    return fallback;
  }

  switch (
    error.code
  ) {
    case "AUTH_REQUIRED":
      return "Sesi Anda sudah tidak tersedia. Silakan masuk kembali.";

    case "TENANT_SELECTION_REQUIRED":
      return "Pilih workspace yang ingin digunakan terlebih dahulu.";

    case "TENANT_ACCESS_DENIED":
      return "Workspace ini sudah tidak dapat diakses oleh akun Anda.";

    case "ACTIVE_TENANT_REQUIRED":
      return "Akun belum memiliki workspace aktif.";

    case "BUSINESS_SELECTION_REQUIRED":
      return "Pilih usaha yang ingin digunakan terlebih dahulu.";

    case "BUSINESS_ACCESS_DENIED":
      return "Usaha ini sudah tidak dapat diakses.";

    case "ACTIVE_BUSINESS_REQUIRED":
      return "Workspace ini belum memiliki usaha aktif.";

    case "FORBIDDEN_CAPABILITY":
      return "Anda belum memiliki izin untuk melakukan tindakan ini.";

    case "ENTITY_HAS_ACTIVITY":
      return "Data ini sudah memiliki riwayat aktivitas sehingga tidak dapat dihapus.";

    case "ENTITY_STATE_CONFLICT":
      return "Tindakan ini belum dapat dilakukan pada status data saat ini.";

    default:
      break;
  }

  if (
    error.status ===
    429
  ) {
    return "Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.";
  }

  if (
    error.status >=
    500
  ) {
    return "Layanan sedang mengalami kendala. Silakan coba lagi.";
  }

  return (
    error.message ||
    fallback
  );
}

export function apiRequestId(
  error: unknown,
): string | null {
  if (
    error instanceof
    ApiClientError
  ) {
    return (
      error.requestId
    );
  }

  return null;
}
