# Keputusan Proyek SIGNOVA

File ini hanya mencatat keputusan yang dapat dibuktikan dari implementation, configuration, atau locked project document.

---

DECISION-ID: DEC-KB-001
DATE: 2026-09-26
MODULE: Project Governance

Decision:
Source/schema/routes/tests menentukan kondisi implementasi saat ini. Master Flow/Kitab/Capability Map menentukan arah normatif dan tidak boleh dianggap sebagai bukti fitur sudah implemented.

Reason:
Mencegah AI Agent mengembangkan atau mengklaim capability masa depan hanya berdasarkan planning document.

Impact:
Knowledge base membedakan `IMPLEMENTED`, `NORMATIVE / LOCKED`, `TARGET / PLANNED`, dan `UNKNOWN / NEEDS CONFIRMATION`.

Do not:
Menyebut target module sebagai production-ready tanpa evidence implementasi.

Evidence:
Repository audit + project documentation hierarchy.

---

DECISION-ID: DEC-ARCH-001
DATE: UNKNOWN
MODULE: Architecture

Decision:
SIGNOVA menggunakan API-first modular monolith.

Reason:
Mencegah premature microservices sambil menjaga domain boundary.

Impact:
Domain backend tetap dalam satu Laravel application; integration memakai adapter/contract.

Do not:
Memecah service menjadi microservice hanya karena preferensi organisasi.

Evidence:
Root README; Product Scope.

---

DECISION-ID: DEC-AUTH-001
DATE: UNKNOWN
MODULE: Authentication

Decision:
First-party Web/PWA menggunakan Laravel Sanctum stateful session authentication.

Reason:
UNKNOWN

Impact:
Session/cookie/CSRF model berlaku untuk first-party frontend.

Do not:
Memperkenalkan localStorage bearer token untuk normal first-party flow tanpa keputusan arsitektur eksplisit.

Evidence:
`backend/bootstrap/app.php`, Sanctum config, auth routes.

---

DECISION-ID: DEC-AUTHZ-001
DATE: UNKNOWN
MODULE: Authorization

Decision:
Backend authorization capability-driven, bukan hardcoded berdasarkan role name.

Reason:
Mendukung tenant-specific role capability dan enforcement yang konsisten.

Impact:
Routes menggunakan capability middleware; frontend membaca effective capability.

Do not:
Authorize action hanya dari label OWNER/ADMIN.

Evidence:
`EffectiveCapabilityResolver`, `RequireCapability`, capability registry.

---

DECISION-ID: DEC-AUTHZ-002
DATE: UNKNOWN
MODULE: Authorization

Decision:
`DENY` precedence terhadap `ALLOW`.

Reason:
Explicit override harus fail closed.

Impact:
Role lain yang ALLOW tidak dapat membypass DENY.

Do not:
Gunakan aturan “any ALLOW wins”.

Evidence:
`EffectiveCapabilityResolver`, `PlatformCapabilityResolver`.

---

DECISION-ID: DEC-SAAS-ENT-001
DATE: UNKNOWN
MODULE: SaaS

Decision:
Entitlement terpisah dari role/capability.

Reason:
Tenant dapat berhak secara komersial terhadap feature, sementara user tertentu tetap tidak memiliki permission.

Impact:
Plan/subscription gate dan user authorization perlu dipertimbangkan terpisah.

Do not:
Mengganti permission dengan package check atau sebaliknya.

Evidence:
`EffectiveEntitlementResolver`; SaaS Core rules.

---

DECISION-ID: DEC-SAAS-ENT-002
DATE: UNKNOWN
MODULE: SaaS

Decision:
Ambiguous entitlement state fail closed; override stacking precedence tidak ditebak.

Reason:
Belum ada keputusan resmi untuk multiple simultaneous override.

Impact:
Resolver mengembalikan ambiguous/denied state.

Do not:
Membuat implicit override ordering.

Evidence:
`EffectiveEntitlementResolver`.

---

DECISION-ID: DEC-API-001
DATE: UNKNOWN
MODULE: API

Decision:
Canonical API field/code menggunakan English/stable code; user-facing UI memakai Bahasa Indonesia.

Reason:
Contract integration stabil sambil menjaga UI mudah dipahami.

Impact:
UI memetakan canonical code lewat dictionary/formatter.

Do not:
Rename canonical API state agar sama dengan wording UI.

Evidence:
API/UI Language Convention.

---

DECISION-ID: DEC-API-002
DATE: UNKNOWN
MODULE: API

Decision:
API error memakai canonical sanitized envelope dengan request correlation metadata.

Reason:
Keamanan dan frontend handling yang stabil.

Impact:
Validation/auth/authorization/state error memakai stable code.

Do not:
Expose SQL error, stack trace, secret, atau raw exception.

Evidence:
`ApiResponse`, exception rendering, error registry.

---

DECISION-ID: DEC-WF-001
DATE: UNKNOWN
MODULE: Workflow

Decision:
Critical lifecycle transition menggunakan explicit action/command, bukan generic status update.

Reason:
State guard, permission, idempotency, side effect, dan history harus memiliki domain owner.

Impact:
Route memakai submit/approve/issue/post/reverse/void action.

Do not:
Membuat universal editable status field.

Evidence:
State & Workflow; current API routes.

---

DECISION-ID: DEC-MONEY-001
DATE: UNKNOWN
MODULE: Money

Decision:
Authoritative monetary calculation memakai decimal semantics; floating point bukan authoritative.

Reason:
Akurasi finansial.

Impact:
Backend money service memakai exact decimal path; frontend tidak boleh menghitung persisted total dengan JS Number.

Do not:
Gunakan float/Number sebagai final persisted money calculation.

Evidence:
Quotation Pricing Convention; purchasing/finance exact-decimal tests.

---

DECISION-ID: DEC-QUO-001
DATE: UNKNOWN
MODULE: Quotation

Decision:
Quotation version menyimpan immutable pricing snapshot.

Reason:
Historical quotation tidak boleh berubah ketika catalog price berubah.

Impact:
Pricing method/config/unit price/discount/tax/result tersimpan di version/item snapshot.

Do not:
Merecalculate historical quotation dari current catalog.

Evidence:
Quotation Pricing Convention; quotation service/model.

---

DECISION-ID: DEC-PUR-001
DATE: 2026-09-26
MODULE: Purchasing

Decision:
Procurement semantics membedakan inventory item, non-stock good, dan service.

Reason:
Receiving dan stock movement memiliki behavior berbeda.

Impact:
PR/PO/Receipt preserve `procurement_type`.

Do not:
Menganggap semua purchase line sebagai warehouse material.

Evidence:
procurement semantics migration/services/resources/tests.

---

DECISION-ID: DEC-INV-001
DATE: UNKNOWN
MODULE: Inventory

Decision:
Stock mutation berbasis ledger/movement.

Reason:
Traceability dan correction history.

Impact:
Goods Receipt membuat stock movement untuk inventory item; flow issue/transfer/adjustment ke depan wajib menjaga movement semantics.

Do not:
Direct-edit physical stock balance.

Evidence:
`StockMovement`; State & Workflow.

---

DECISION-ID: DEC-FIN-001
DATE: UNKNOWN
MODULE: Supplier Finance

Decision:
PO, Goods Receipt, Supplier Bill, dan Supplier Payment adalah event yang berbeda.

Reason:
Memisahkan procurement commitment, physical receiving, payable recognition, dan cash settlement.

Impact:
Tidak ada cash movement pada PR/PO/Receipt; payment menangani settlement.

Do not:
Collapse seluruh dokumen menjadi satu state.

Evidence:
Purchasing/Inventory/Finance services/tests.

---

DECISION-ID: DEC-PWA-001
DATE: UNKNOWN
MODULE: Frontend/PWA

Decision:
PWA boleh cache app shell/static/runtime asset, tetapi sensitive server-confirmed business action tidak boleh difinalisasi offline.

Reason:
Security dan consistency.

Impact:
Offline policy menentukan never-persist secret dan server-confirmed action.

Do not:
Persist password/OTP/token/provider secret atau memalsukan success financial transition secara offline.

Evidence:
offline policy, service worker foundation, UI/UX Constitution.

---

DECISION-ID: DEC-UX-001
DATE: UNKNOWN
MODULE: UX

Decision:
SIGNOVA mobile-first dan task-oriented; guided next action lebih diutamakan daripada menu-only navigation.

Reason:
Master Application Flow dan UI/UX Constitution.

Impact:
Detail/workflow menjaga context dan menampilkan legitimate next action.

Do not:
Memaksa user mencari downstream menu bila system sudah mengetahui source context.

Evidence:
Master Application & User Flow; Frontend UI/UX Constitution.

## DEC-INV-2026-09-26 — Inventory Master Foundation

Status: **LOCKED / IMPLEMENTED**

### Context

SIGNOVA membutuhkan satu master barang fisik yang dapat digunakan untuk
barang dengan stok dilacak maupun barang fisik tanpa saldo stok.

Authorization sebelumnya juga menggunakan `inventory.receive` untuk
write Inventory Master sehingga hak transaksi Receiving bercampur dengan
hak mengubah master.

### Decision

1. Primary inventory master adalah Barang & Persediaan.
2. Barang & Jasa Penjualan / sales catalog tetap domain terpisah.
3. `inventory_type` dan `stock_tracking` adalah dua dimensi berbeda.
4. `stock_tracking` hanya `TRACKED` atau `NOT_TRACKED`.
5. Kategori inventory menggunakan relational master
   `inventory_categories`.
6. `materials.category_id` adalah canonical category reference.
7. Legacy `materials.category` dipertahankan sementara sebagai
   compatibility bridge.
8. Stock threshold hanya berlaku untuk `TRACKED`.
9. Stock balance tetap berasal dari movement ledger dan tidak boleh
   diedit langsung dari material master.
10. `TRACKED` material menggunakan procurement semantic
    `INVENTORY_ITEM`.
11. `NOT_TRACKED` material menggunakan procurement semantic
    `NON_STOCK_GOOD`.
12. `SERVICE` tidak menggunakan material master.
13. Write Inventory Master menggunakan `inventory.master.manage`.
14. Receiving transaction tetap menggunakan `inventory.receive`.
15. OWNER dan ADMIN mendapat baseline `inventory.master.manage`.
16. Capability adalah source of truth authorization, bukan role-name check.
17. Transaction history menyimpan reference + snapshot dan tidak ditulis
    ulang ketika master kemudian berubah.

### Verification

Pada 2026-09-26:

- targeted backend test hijau;
- migration
  `2026_09_26_092000_expand_inventory_master_foundation`
  applied pada staging batch 62;
- capability dan Master Role mapping diverifikasi;
- HTTP staging UAT untuk kategori, material, gudang, Purchase Request,
  dan Purchase Order berhasil;
- TRACKED dipetakan menjadi INVENTORY_ITEM;
- NOT_TRACKED dipetakan menjadi NON_STOCK_GOOD;
- stock movement tidak bertambah saat UAT;
- cash transaction tidak bertambah saat UAT.


---

## DEC-INV-2026-09-27 — Inventory Frontend IA & Stock Read Model

Status: **LOCKED / IMPLEMENTED / STAGING UAT VERIFIED**

### Context

Manual UAT Inventory Master menemukan dua masalah usability:

1. menu `Kategori` pada Master Data dan `Kategori` pada Inventory sulit
   dibedakan;
2. user tidak memiliki lokasi yang jelas untuk melihat saldo stok aktual.

Backend sudah memiliki `StockMovement` sebagai ledger source of truth sehingga
saldo tidak boleh dibuat sebagai nilai editable pada Material Master.

### Decision

1. Domain Inventory Master tetap mengikuti
   `DEC-INV-2026-09-26`; sales catalog dan inventory master tetap terpisah.
2. Label sales catalog category pada frontend adalah
   **Kategori Penjualan**.
3. Label primary inventory master pada frontend adalah
   **Barang Persediaan**.
4. Pekerjaan inventory pada grup **Operasional** menggunakan menu terpisah:
   - **Stok**
   - **Barang Persediaan**
   - **Kategori Persediaan**
   - **Gudang**
5. Pemisahan menu adalah keputusan IA untuk memudahkan pencarian pekerjaan;
   bukan pemisahan backend domain dan bukan penambahan top-level group.
6. Halaman **Stok** bersifat read-only dan memakai capability
   `inventory.view`.
7. Read contract stok adalah
   `GET /api/v1/inventory/stock-balances`.
8. `on_hand` berasal dari
   `SUM(stock_movements.quantity_signed)`.
9. Material `TRACKED` tanpa movement tetap muncul dengan saldo `0`.
10. Material `NOT_TRACKED` tidak diperlakukan sebagai physical stock balance
    pada halaman Stok.
11. Balance tidak boleh diedit langsung dari frontend ataupun Material Master.
12. Versi awal halaman Stok menampilkan **total seluruh gudang**.
13. Breakdown per gudang dapat ditambahkan melalui read model ketika
    workflow membutuhkannya.
14. Legacy `/app/operasional/stok-gudang` tetap menjadi redirect ke
    `/app/operasional/barang-persediaan`.
15. Issue, transfer, adjustment, stock opname, reservation, dan allocation
    belum dianggap selesai oleh keputusan ini.

### Reason

User harus dapat membedakan kategori penjualan dengan kategori persediaan dan
dapat menemukan saldo stok tanpa memahami struktur backend. Ledger tetap
menjadi source of truth perubahan stok fisik.

### Impact

- navigasi Inventory lebih eksplisit;
- Stock visibility dapat berkembang tanpa mutable balance;
- read authorization tetap `inventory.view`;
- write Inventory Master tetap `inventory.master.manage`;
- Receiving tetap `inventory.receive`;
- future stock transaction wajib menghasilkan movement ledger yang auditable.

### Do not

- jangan menyimpan editable `on_hand` pada Material Master;
- jangan memasukkan `NOT_TRACKED` sebagai physical stock balance;
- jangan menggunakan nama role sebagai authorization;
- jangan menjadikan kalkulasi frontend sebagai source of truth saldo;
- jangan menganggap Stock read-only menyelesaikan
  issue/transfer/adjustment/reservation.

### Verification — 2026-09-27

- Stock Balance API registered dan tenant/business/capability scoped;
- targeted Inventory Foundation test PASS;
- full backend suite PASS: 699 tests / 3521 assertions;
- frontend full ESLint dan TypeScript PASS;
- Next.js 16.3.5 production build PASS;
- staging BUILD_ID `VAuJb10muXa5poGcSoymO`;
- route Stok, Barang Persediaan, Kategori Persediaan, dan Gudang HTTP 200;
- legacy route redirect 307;
- manual mobile UAT halaman Stok PASS;
- protected staging counts tetap `cash_transactions=7` dan
  `stock_movements=0`.
