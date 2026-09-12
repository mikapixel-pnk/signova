# SIGNOVA API & UI Language Convention

Status: Locked
Scope: Backend API, Frontend UI, Mobile/PWA, Integration

## Prinsip

1. Semua konten yang dilihat user menggunakan Bahasa Indonesia yang sederhana dan konsisten.
2. Nama field API tetap English/canonical, misalnya `name`, `type`, `pricing_method`, `base_price`, `status`.
3. Canonical business code tetap English/stable, misalnya `ACTIVE`, `INACTIVE`, `STANDARD`, `AREA`, `LENGTH`, `VOLUME`, `TIME`, `PACKAGE`, `MANUAL`.
4. Error code tetap canonical, misalnya `VALIDATION_FAILED`, `FORBIDDEN_CAPABILITY`, `RESOURCE_NOT_FOUND`, `TENANT_ACCESS_DENIED`.
5. API dianjurkan mengirim label Bahasa Indonesia siap tampil bersama canonical code, misalnya:
   - `type: SERVICE` + `type_label: Jasa`
   - `pricing_method: AREA` + `pricing_method_label: Berdasarkan Luas`
   - `status: ACTIVE` + `status_label: Aktif`
6. Frontend tidak menjadi source of truth untuk business rule. Backend tetap source of truth.
7. Internal code, class, method, test, migration, capability, feature code, database column, API field, dan canonical state tetap English.
8. Menu, tombol, form, badge, notifikasi, empty state, success/error message, confirmation, dan istilah bisnis user-facing memakai Bahasa Indonesia.
9. Integrasi eksternal memakai canonical SIGNOVA; adapter menerjemahkan format provider.
10. Sebelum coding API/frontend, baca file ini dan kitab domain terkait.

## Mapping Metode Harga

| Canonical | Label Indonesia |
|---|---|
| STANDARD | Harga Standar |
| AREA | Berdasarkan Luas |
| LENGTH | Berdasarkan Panjang |
| VOLUME | Berdasarkan Volume |
| TIME | Berdasarkan Waktu |
| PACKAGE | Harga Paket |
| MANUAL | Harga Manual |

## Mapping Status Umum

| Canonical | Label Indonesia |
|---|---|
| ACTIVE | Aktif |
| INACTIVE | Nonaktif |
| DRAFT | Draf |
| ISSUED | Diterbitkan |
| PARTIALLY_PAID | Dibayar Sebagian |
| PAID | Lunas |
| OVERDUE | Jatuh Tempo |
| VOID | Dibatalkan |

Perubahan terhadap konvensi ini harus dilakukan secara eksplisit melalui dokumentasi dan review, bukan secara ad-hoc per modul.
