# Piagam Proyek SIGNOVA

**Terakhir diaudit:** 2026-09-26
**Branch audit:** `feat/foundation-backend`
**HEAD audit:** `4d1e74c`

## 1. Nama Proyek

**SIGNOVA Platform**

## 2. Tujuan Utama

SIGNOVA adalah platform operasional SaaS untuk bisnis advertising/reklame, produksi custom, dan jasa/proyek terkait.

Arah produk menempatkan SIGNOVA sebagai **sistem operasional bisnis**, bukan sekadar kumpulan menu ERP generik. Platform menghubungkan commercial flow, operational flow, purchasing/inventory, finance, dan integration melalui satu tenant/business context.

## 3. Pengguna Utama

Pengguna yang terbukti dari source dan dokumen:

- Pemilik / Owner tenant
- Administrator tenant
- Sales
- Finance
- User operasional sesuai capability
- Customer melalui public quotation/invoice flow
- Super Admin SIGNOVA pada platform domain

Master Flow juga mendefinisikan actor operasional seperti surveyor, designer, production, QC, dan installer. Keberadaan actor tersebut pada dokumen target tidak otomatis berarti seluruh backend/UI-nya sudah tersedia.

## 4. Masalah yang Diselesaikan

SIGNOVA ditujukan untuk mengurangi masalah seperti:

- data pelanggan dan transaksi tersebar,
- follow-up quotation tidak terstruktur,
- invoice/payment tidak memiliki lifecycle yang jelas,
- pembelian dan penerimaan tidak terdokumentasi end-to-end,
- stock dan purchasing mudah tercampur,
- cash movement dan payable mudah tercampur,
- owner sulit melihat pekerjaan yang membutuhkan tindakan,
- user harus berpindah banyak menu untuk memahami satu pekerjaan,
- external provider berisiko masuk langsung ke core business logic.

## 5. Scope Implementasi Aktual

Terbukti dari source/schema/routes pada audit.

### SaaS / Access Foundation
- authentication user,
- tenant context,
- business context,
- roles/capabilities,
- platform capabilities,
- subscription/entitlement foundation,
- plan/version/offering/trial foundation pada history/branch terkait.

### Commercial Core
- Customer,
- Catalog / Barang & Jasa,
- Quotation,
- public quotation action,
- Invoice,
- public invoice,
- Payment,
- payment verification/allocation/reversal.

### Finance
- Finance summary,
- Cash & Bank,
- Income,
- Expense,
- Receivable capability,
- Supplier Bill / Payables backend,
- Supplier Payment backend.

### Procurement / Inventory
- Supplier,
- Purchase Request,
- Purchase Order,
- Goods Receipt backend,
- Inventory material master,
- Warehouse master,
- StockMovement ledger foundation.

### Settings / Dokumen
- business profile,
- document settings,
- invoice template,
- payment settings,
- business logo/signature/static QR,
- PDF generation.

### PWA Foundation
- service worker registration,
- manifest,
- runtime/static asset warmup,
- explicit offline policy,
- private cache cleanup.

## 6. Product Layer Normatif

Product Scope membagi platform menjadi:

1. SaaS Core
2. Commercial Core
3. Operational Core
4. Enterprise Ops
5. Connectivity
6. Intelligence

Layer tersebut adalah arah produk. Sebelum agent mengimplementasikan capability yang belum ada, harus dilakukan audit terhadap source aktual, entitlement/package, state model, permission, dependency, dan acceptance criteria.

## 7. Non-Scope / Belum Terbukti Implemented

Hal berikut tidak boleh disebut sudah selesai tanpa evidence baru:

- full accounting / statutory GL,
- full HR/asset suite,
- production/QC/installation backend lengkap,
- Job/Activity Cockpit lengkap,
- marketplace connector lengkap,
- production Shopee connector,
- AI workflow lengkap,
- offline transactional sync lengkap,
- platform admin lifecycle seluruh fase SaaS.

Sebagian konsep tersebut ada pada Master Flow/Kitab/Capability Map, tetapi belum terbukti lengkap di branch yang diaudit.

## 8. Prinsip Desain

### One Source of Truth
Customer, catalog, quotation, invoice/payment, supplier, inventory, dan user tidak diduplikasi antar fitur.

### Backend-Owned Business Rules
Frontend menampilkan capability/state dalam bahasa manusia; backend tetap final validator, authorization, dan business-rule authority.

### Mobile / PWA First
Pekerjaan harian harus tetap nyaman dilakukan dari mobile.

### Progressive Complexity
Starter tetap ringan; Business menambah kedalaman workflow; Pro menambah kontrol operasional lanjutan. Visibility package tidak boleh ditentukan oleh hardcoded plan check yang tersebar di frontend.

### Guided Work
Detail screen harus membantu user memahami posisi saat ini dan langkah berikutnya yang sah.

### Provider-Independent Core
State provider eksternal diterjemahkan ke contract canonical SIGNOVA melalui adapter.

### Auditability
State sensitif, finance, dan inventory correction harus menjaga history.

### Fail Closed
Tenant, capability, entitlement, atau akses sensitif yang ambigu harus ditolak, bukan ditebak.

## 9. Constraint Utama

- Multi-tenant boundary wajib.
- Business context berada di dalam tenant context.
- Authorization capability-driven.
- Entitlement dan role capability adalah concern berbeda.
- API canonical code menggunakan English/stable code.
- UI user-facing menggunakan Bahasa Indonesia.
- PostgreSQL adalah primary RDBMS.
- Redis dikonfigurasi untuk cache/queue.
- Aplikasi saat ini modular monolith.
- Monetary persistence menggunakan decimal semantics.
- Applied migration bersifat immutable.
- Critical workflow transition menggunakan explicit action, bukan generic status editing.

## 10. Gate Pengembangan Capability

Sebelum menambah capability, wajib jelas:

```text
Problem:
Actor:
Package/Entitlement:
Domain Owner:
Trigger:
Source of Truth:
State/Lifecycle:
Capability:
Tenant Boundary:
Upstream:
Downstream:
Allowed Side Effects:
Forbidden Side Effects:
API Contract:
UI Flow:
Acceptance Tests:
```

Bagian yang belum diketahui ditandai:

`UNKNOWN / NEEDS CONFIRMATION`

## 11. Evidence

### Implementasi
- root `README.md`
- `backend/app/**`
- `backend/routes/api.php`
- `backend/database/migrations/**`
- `backend/database/seeders/**`
- `frontend/src/**`
- audit snapshot 2026-09-26 pada `4d1e74c`

### Dokumen Normatif
- Product Scope & Feature Matrix
- Master Application & User Flow
- State & Workflow
- SaaS Core / Tenant Lifecycle
- Frontend UI/UX Constitution
- API/UI Language Convention
