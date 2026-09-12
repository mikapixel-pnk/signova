# SIGNOVA API Error Registry

Status: Baseline
API version: v1

Error code pada API bersifat canonical dan stabil.
Frontend menampilkan pesan manusia tanpa mengandalkan exception teknis backend.

| Code | HTTP | Makna |
|---|---:|---|
| AUTH_REQUIRED | 401 | Request membutuhkan autentikasi |
| FORBIDDEN | 403 | Aksi ditolak policy/authorization umum |
| FORBIDDEN_CAPABILITY | 403 | User tidak memiliki capability yang diperlukan |
| TENANT_ACCESS_DENIED | 403 | Tenant tidak tersedia atau tidak boleh diakses |
| ACTIVE_TENANT_REQUIRED | 403 | User tidak memiliki tenant aktif |
| TENANT_SELECTION_REQUIRED | 409 | User memiliki lebih dari satu tenant aktif dan harus memilih |
| RESOURCE_NOT_FOUND | 404 | Resource tidak ditemukan dalam context yang sah |
| METHOD_NOT_ALLOWED | 405 | HTTP method tidak didukung |
| VALIDATION_FAILED | 422 | Request field tidak valid |
| INVALID_TRANSITION | 409 | State transition tidak diperbolehkan |
| QUOTATION_NOT_EDITABLE | 409 | Penawaran tidak dapat diedit pada state saat ini |
| DUPLICATE_ACTION | 409 | Command yang sama sudah diproses |
| RATE_LIMITED | 429 | Request terlalu banyak |
| INTERNAL_ERROR | 500 | Internal server error yang sudah disanitasi |
| FEATURE_NOT_ENTITLED | 403 | Feature tidak termasuk entitlement tenant |
| SUBSCRIPTION_INACTIVE | 403 | Subscription tenant tidak aktif |

## Standard Error Contract

{
  "success": false,
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Periksa kembali data yang dimasukkan.",
    "details": {}
  },
  "meta": {
    "request_id": "req_..."
  }
}

## Security Rules

- Tidak mengirim stack trace.
- Tidak mengirim SQL error.
- Tidak mengirim token atau secret.
- Tidak membuka informasi tenant lain.
- INTERNAL_ERROR selalu menggunakan pesan aman.
- Authorization error bersifat fail-closed.
