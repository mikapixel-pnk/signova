# Arsitektur SIGNOVA

**Terakhir diaudit:** 2026-09-26
**HEAD audit:** `4d1e74c`

## 1. Gaya Arsitektur

SIGNOVA saat ini menggunakan **modular monolith** dengan:

- Laravel API backend
- Next.js frontend/PWA
- PostgreSQL sebagai primary database
- Redis untuk cache/queue
- capability-driven authorization
- tenant + business context
- API-first contract

Jangan menyimpulkan microservices hanya karena domain dipisahkan dalam folder. External system diintegrasikan melalui adapter/contract.

## 2. Struktur Repository

```text
/
├── backend/      Laravel API/application
├── frontend/     Next.js 16 PWA
├── docs/         API/governance/project knowledge
├── infra/        infrastructure/deployment assets
├── scripts/      operational scripts
└── README.md
```

Instruksi lokal penting:
- `backend/AGENTS.md`
- `frontend/AGENTS.md`

## 3. Backend

### Runtime
- PHP 8.3
- Laravel 13
- Laravel Sanctum
- DOMPDF

### Struktur Utama

```text
backend/app/
├── Actions/
├── Authorization/
├── Contracts/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Models/
├── Services/
├── Support/
└── Tenancy/
```

Service domain yang terdeteksi mencakup Customer, Catalog, Quotation, Invoice, Payment, Supplier, Purchasing, Inventory, Finance, Settings, File, dan Messaging.

Controller diharapkan tetap tipis dibanding domain service.

## 4. Frontend

### Runtime
- Node 22
- Next.js 16.3.5
- React 19
- TypeScript
- ESLint
- lucide-react

### Struktur

```text
frontend/src/
├── app/
├── components/
├── config/
├── lib/
└── types/
```

Frontend menggunakan typed service untuk mengakses API canonical.

Prinsip bahasa:
- route/label user-facing: Bahasa Indonesia,
- API path/code/state/capability: canonical English.

## 5. Database

### Engine
PostgreSQL 18.x pada staging yang diaudit.

### Kelompok Domain pada Schema
- auth/session/token
- users/tenants/businesses/memberships
- role/capability
- platform role/capability
- customer/catalog
- quotation/version/history/public link
- invoice/payment/allocation/reversal
- cash/income/expense
- supplier
- purchase request/order
- goods receipt
- inventory materials/warehouse/stock movement
- supplier bill/payment/AP
- file/document settings
- subscription/plan/entitlement/lifecycle/audit foundation

Schema dikelola melalui migration.

Applied migration tidak boleh diedit.

## 6. Authentication

First-party Web/PWA menggunakan Laravel Sanctum stateful session.

Evidence:
- `backend/bootstrap/app.php` menggunakan `statefulApi()`
- protected route menggunakan `auth:sanctum`
- session/cookie berlaku untuk first-party flow
- password recovery memakai OTP/proof service

Jangan menambahkan bearer token di `localStorage` untuk first-party app tanpa keputusan arsitektur eksplisit.

## 7. Tenant & Business Context

Internal API secara umum mengikuti:

```text
Authenticated User
→ Tenant Context
→ Business Context
→ Capability
→ Domain Service
```

Tenant dapat dipilih melalui header:

`X-Signova-Tenant`

Business dapat dipilih melalui:

`X-Signova-Business`

Ambiguity ditolak.

Relevant source:
- `ResolveTenantContext`
- `TenantContextResolver`
- `ResolveBusinessContext`
- `BusinessContextResolver`

## 8. Authorization

Backend capability middleware adalah authoritative.

Tenant capability flow:

```text
tenant membership
→ tenant user role
→ role
→ role_capabilities
→ active capability
→ DENY precedence
```

Relevant source:
- `EffectiveCapabilityResolver`
- `RequireCapability`

Platform capability terpisah:
- `PlatformCapabilityResolver`
- `RequirePlatformCapability`

Frontend visibility tidak boleh dianggap sebagai authorization.

## 9. Entitlement

Subscription entitlement terpisah dari role capability.

`EffectiveEntitlementResolver` memeriksa:
- tenant aktif,
- feature aktif,
- subscription pada effective window,
- entitlement snapshot,
- override,
- ambiguity.

Ambiguous active subscription/snapshot/override → fail closed.

Belum ada evidence bahwa seluruh tenant API route sudah memiliki entitlement gate yang seragam.

Status:

`UNKNOWN / NEEDS CONFIRMATION`

## 10. API

Baseline version:

`/api/v1`

Public token route:

`/api/public/v1`

Domain yang terdeteksi:
- auth
- customers
- catalog/units
- suppliers
- quotations
- invoices
- payments
- finance
- purchasing
- inventory
- settings

Standard internal API error contract dipusatkan melalui `ApiResponse`.

Validation error menggunakan canonical error envelope.

## 11. State Transition

Lifecycle penting menggunakan explicit action endpoint seperti:

```text
.../actions/submit
.../actions/approve
.../actions/reject
.../actions/revise
.../actions/cancel
.../actions/issue
.../actions/post
.../actions/reverse
.../actions/void
```

Jangan mengganti pola ini dengan universal status update.

## 12. Queue / Worker

Environment yang diaudit:
- Redis dikonfigurasi sebagai queue backend.
- Application Job class tidak terbukti dari audit.
- SIGNOVA queue worker aktif tidak terbukti dari audit.

Status:

`UNKNOWN / NEEDS CONFIRMATION`

Sebelum menambah async behavior, audit process manager, retry, idempotency, dan failed job strategy.

## 13. Scheduler

Tidak ada business scheduler SIGNOVA yang terbukti dari audit `routes/console.php`.

Status:

`UNKNOWN / NEEDS CONFIRMATION`

Jangan mengklaim reminder/renewal/expiry otomatis aktif hanya karena target document menyebutkannya.

## 14. Storage

Staging yang diaudit menggunakan default filesystem `local`.

Laravel filesystem config mendukung S3-compatible storage.

Domain file:
- `FileAsset`
- `FileService`

Gunakan provider-independent abstraction.

## 15. Messaging / External Integration

Terdapat:
- `Contracts/Messaging/WhatsAppDelivery`
- `Services/Messaging/AnaWhatsAppGatewayAdapter`

Password recovery memakai messaging abstraction.

Marketplace/Shopee dan omnichannel ada di arsitektur produk, tetapi production connector belum terbukti pada branch audit.

Status marketplace connector:

`UNKNOWN / NEEDS CONFIRMATION`

## 16. PWA / Offline

Foundation yang terbukti:
- service worker registration,
- `/sw.js`,
- manifest,
- static/runtime asset warmup,
- connectivity helper,
- offline policy,
- private cache cleanup.

Bedakan:
- app-shell/static caching → terbukti,
- full offline transaction synchronization → belum terbukti.

Action yang wajib dikonfirmasi server tidak boleh dipalsukan sukses saat offline.

## 17. Deployment

Frontend staging:
- systemd service `signova-frontend`
- menjalankan Next.js `next start`
- bind `127.0.0.1:3000`
- reverse proxy melalui nginx/server infrastructure

Operational scripts:
- `scripts/backup-staging.sh`
- `scripts/migrate-staging.sh`

Automated backup schedule belum terbukti.

## 18. Environment

Staging audit menunjukkan:
- PostgreSQL
- Redis cache
- Redis queue
- database-backed session
- local filesystem

Secret tidak boleh disalin ke dokumentasi.

## 19. Arsitektur UX Frontend

Aturan normatif:
- mobile-first,
- task-first,
- Bahasa Indonesia,
- canonical code hanya internal,
- capability-driven UI,
- hidden UI bukan authorization,
- feedback dekat aksi,
- guided next action,
- dark/light compatible,
- offline policy untuk data sensitif.

## 20. Observasi Arsitektur Saat Ini

- root README masih menggambarkan project terutama sebagai Starter foundation dan tertinggal dari implementasi aktual.
- Receiving, Inventory, dan Payables sudah memiliki backend/service foundation, tetapi page audit masih `ModulePage` placeholder.
- frontend automated test belum terbukti.
- platform runtime role query supplemental gagal karena query audit memakai `platform_roles.is_active`, sedangkan seeder menggunakan `status`.
- queue worker/scheduler/automated backup perlu konfirmasi.
