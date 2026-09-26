# Kondisi Development SIGNOVA Saat Ini

**Terakhir diperbarui:** 2026-09-26

**Branch audit:** `feat/foundation-backend`
**HEAD audit:** `4d1e74c`

Runtime evidence:
- frontend staging sudah direstart setelah HEAD `4d1e74c`,
- service aktif,
- local HTTP check menghasilkan 200.

## Modul Aktif

Purchasing / Inventory / Supplier Payables integration.

Development fitur saat ini **PAUSE sementara** untuk membangun knowledge base dan governance AI Agent.

## Tujuan Saat Ini

Membuat knowledge base project yang akurat agar pengembangan pada chat/worktree berbeda tetap konsisten.

Jangan melanjutkan feature development sebelum dokumentasi baseline ini direview.

## Selesai

### Project Audit
- source structure audited,
- database/schema + applied migrations audited,
- routes/API audited,
- controller/service/model indexed,
- authentication/context audited,
- capability/role baseline audited,
- frontend consumers audited,
- PWA/offline foundation audited,
- tests/configuration/infra scripts audited,
- relevant commit history audited.

### Purchasing Backend
- Purchase Request workflow,
- Purchase Order workflow,
- procurement semantics,
- decimal-safe PO calculation,
- Goods Receipt backend workflow,
- receipt/inventory movement semantics.

### Purchasing Frontend
- Purchase Request list/form/detail/workflow,
- PR approved → contextual CTA Buat Pesanan Pembelian,
- Purchase Order list,
- PO draft create/edit,
- PO detail,
- issue/cancel workflow.

### Supplier Finance Backend
- Supplier Bill workflow,
- Supplier Payment workflow,
- supplier payable read model/balance.

### Frontend Deployment
Latest known checks:
- ESLint hijau,
- TypeScript hijau,
- Next.js production build hijau,
- `signova-frontend` aktif,
- local HTTP 200.

## Sedang Berjalan

Knowledge base saja:

- `AGENTS.md`
- `docs/PROJECT_CHARTER.md`
- `docs/ARCHITECTURE.md`
- `docs/DOMAIN_RULES.md`
- `docs/WORKFLOW.md`
- `docs/PERMISSIONS.md`
- `docs/DECISIONS.md`
- `docs/CURRENT_STATE.md`

Jangan campur feature patch ke milestone dokumentasi ini.

## Known Issues / Observations

1. Root README masih menggambarkan repository terutama sebagai Basic/Starter foundation dan sudah tertinggal dari implementasi aktual.
2. `penerimaan/page.tsx` masih generic `ModulePage`; backend Goods Receipt sudah ada.
3. Inventory frontend masih generic/placeholder; backend Inventory Master Foundation sudah implemented dan staging UAT verified. Frontend typed contract + UI kategori/material/gudang masih pending.
4. Payables page masih generic `ModulePage`; Supplier Bill/Payment backend tersedia.
5. Frontend automated test belum terbukti.
6. Redis queue dikonfigurasi, tetapi active SIGNOVA queue worker belum terbukti.
7. Business scheduler SIGNOVA belum terbukti.
8. Backup script tersedia, tetapi automated cron/timer schedule belum terbukti.
9. Platform runtime role matrix belum terverifikasi karena supplemental SQL memakai field yang tidak sesuai schema.
10. Marketplace/Shopee production connector belum terbukti.
11. ANA WhatsApp delivery adapter tersedia; ANA SSO status `UNKNOWN / NEEDS CONFIRMATION`.
12. Full offline transactional synchronization belum terbukti.

## Technical Debt

- root knowledge base sebelumnya belum ada,
- README perlu future documentation refresh,
- capability registry document lebih lama/kecil daripada capability seeder aktual,
- Receiving/Inventory/Payables UI tertinggal dari backend,
- platform/SaaS work berada pada worktree/branch terpisah dan harus direkonsiliasi secara sengaja,
- generated `backend/AGENTS.md` menyarankan install Laravel Boost; dependency tidak boleh ditambahkan otomatis.

## Last Known Test

### Frontend purchasing milestone terakhir
Pada HEAD `4d1e74c`:
- approved PR → PO contextual route terverifikasi,
- lint = 0,
- TypeScript = 0,
- production build = 0,
- commit/push sukses,
- frontend service sudah direstart,
- HTTP check = 200.

### Backend domain evidence terbaru
Test milestone sebelumnya mencakup:
- Purchase Request API,
- Purchase Order API,
- Goods Receipt API,
- Supplier Bill API,
- Supplier Payment API,
- Inventory Master API,
- finance exact-decimal behavior.

Jangan menyimpulkan full backend suite sudah dijalankan pada current HEAD tanpa evidence baru.

## Open Risks

- AI Agent dapat mencampur target document dengan implemented feature.
- AI Agent dapat menyentuh worktree lain.
- Capability change dapat menciptakan security regression jika frontend visibility dianggap authorization.
- Finance/inventory change dapat merusak ledger semantics bila lifecycle document dicampur.
- Entitlement dapat salah dianggap sama dengan user capability.
- Queue/scheduler assumption dapat menghasilkan “automation” yang sebenarnya tidak berjalan.
- Target docs dapat lebih maju daripada source aktual.

## Langkah Aman Berikutnya

Setelah user mereview diff knowledge base:

1. apply/adjust dokumentasi saja,
2. jalankan `git diff --check`,
3. review staged/untracked documentation file,
4. **JANGAN COMMIT** sampai user menyetujui,
5. setelah disetujui, commit documentation baseline secara terpisah,
6. baru lanjut feature development berdasarkan current business priority dan Master Flow.

## Jangan Disentuh

Tanpa task eksplisit:

- `/srv/signova-worktrees/platform-admin`
- `/srv/signova-worktrees/saas-07b`
- branch/worktree lain
- aplikasi lain pada server
- applied migrations
- staging/production data melalui ad-hoc SQL
- finance/stock ledger secara langsung
- secret/environment credential
- systemd/nginx/cron kecuali infrastructure task khusus

## Awareness Worktree

Audit snapshot:

```text
/srv/signova
  branch: feat/foundation-backend

/srv/signova-worktrees/platform-admin
  branch: feat/platform-admin

/srv/signova-worktrees/saas-07b
  branch: feat/saas-07b-onboarding
```

Jangan menganggap code yang belum di-merge pada worktree lain sudah tersedia di current branch.

## Master Data Foundation — Inventory — 2026-09-26

### Status

**BACKEND IMPLEMENTED + TESTED + STAGING UAT VERIFIED**

Frontend Inventory Master masih pending.

### Implemented

- `inventory_categories`
- `materials.category_id`
- `materials.stock_tracking`
- `materials.minimum_stock`
- `materials.reorder_point`
- `materials.maximum_stock`
- `materials.description`
- relational category compatibility/backfill
- Inventory Category API
- additive Material API contract
- stock-policy validation
- duplicate category guard
- tenant/business isolation
- capability `inventory.master.manage`
- Inventory Master write dipisahkan dari Receiving
- TRACKED -> INVENTORY_ITEM
- NOT_TRACKED -> NON_STOCK_GOOD

### Migration

Migration:

`2026_09_26_092000_expand_inventory_master_foundation`

Staging:

**Ran — batch 62**

### Automated test evidence

Targeted run 2026-09-26:

- PurchaseRequestApiTest:
  16 passed / 128 assertions
- PurchaseOrderApiTest:
  14 passed / 83 assertions
- GoodsReceiptApiTest:
  5 passed / 95 assertions
- InventoryMasterApiTest:
  3 passed / 26 assertions
- InventoryMasterFoundationApiTest:
  5 passed / 49 assertions

Total:

**43 tests / 381 assertions passed**

Testing database:

`signova_test`

### Staging UAT evidence

Verified melalui HTTP staging API:

- create Inventory Category -> 201
- duplicate Inventory Category -> 422
- create TRACKED material -> 201
- create NOT_TRACKED material -> 201
- NOT_TRACKED dengan stock threshold -> 422
- create Warehouse -> 201
- Purchase Request NOT_TRACKED -> NON_STOCK_GOOD
- Purchase Request TRACKED -> INVENTORY_ITEM
- direct Purchase Order NOT_TRACKED -> NON_STOCK_GOOD
- direct Purchase Order TRACKED -> INVENTORY_ITEM
- category/material/warehouse readback -> 200

Forbidden side effects:

- stock movements before: 0
- stock movements after: 0
- cash transactions before: 7
- cash transactions after: 7

Result:

`FORBIDDEN_SIDE_EFFECTS=NONE`

Temporary UAT Sanctum token sudah dicabut setelah UAT dan
`remaining_uat_tokens = 0`.

### Authorization verification

Master Role:

- OWNER -> `inventory.master.manage = ALLOW`
- ADMIN -> `inventory.master.manage = ALLOW`
- FINANCE -> no baseline grant
- SALES -> no baseline grant

Current verified staging tenant hanya mempunyai system role OWNER aktual.

### Known remaining scope

Belum selesai:

- frontend typed Material / Inventory Category contract;
- frontend Inventory Master service;
- UI Barang & Persediaan;
- UI Kategori Persediaan;
- UI Gudang;
- supplier pricing/linkage;
- stock reservation;
- project allocation.

### Next Safe Step

1. final source + documentation diff review;
2. commit Inventory Master Foundation backend milestone;
3. verify clean worktree;
4. lanjut frontend Master Barang & Persediaan.

Receiving tetap downstream. Perubahan stock fisik harus terus melalui
movement ledger dan mengikuti procurement semantics.
