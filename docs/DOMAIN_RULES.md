# Aturan Domain SIGNOVA

Dokumen ini hanya mencatat aturan yang didukung oleh implementasi atau dokumen normatif yang dikunci.

Status evidence:
- `IMPLEMENTED`
- `NORMATIVE / LOCKED`
- `UNKNOWN / NEEDS CONFIRMATION`

---

RULE-ID: TEN-001
MODULE: Tenancy

Rule:
Tenant context harus terselesaikan sebelum operasi bisnis tenant-scoped. Context yang ambigu atau tidak sah harus fail closed.

Trigger:
Authenticated request masuk ke tenant-scoped API.

Allowed:
Menggunakan tenant aktif yang sah.

Forbidden:
Menebak tenant, fallback ke tenant lain, atau membuka data lintas tenant.

Evidence:
`ResolveTenantContext`, `TenantContextResolver`, API error registry.

Status: IMPLEMENTED.

---

RULE-ID: BUS-001
MODULE: Business Context

Rule:
Business context harus berada di tenant aktif dan ambiguity harus ditolak.

Trigger:
Business-scoped request.

Allowed:
Menggunakan business yang valid pada tenant aktif.

Forbidden:
Cross-tenant business selection atau implicit guess saat lebih dari satu context tersedia.

Evidence:
`ResolveBusinessContext`, `BusinessContextResolver`.

Status: IMPLEMENTED.

---

RULE-ID: AUTH-001
MODULE: Authorization

Rule:
Backend capability adalah authoritative. Frontend visibility bukan security.

Trigger:
Protected action/data access.

Allowed:
Aksi hanya jika effective capability mengizinkan.

Forbidden:
Menganggap tombol/menu yang terlihat sebagai bukti authorization.

Evidence:
`RequireCapability`, `EffectiveCapabilityResolver`, API/UI Language Convention.

Status: IMPLEMENTED + NORMATIVE.

---

RULE-ID: AUTH-002
MODULE: Authorization

Rule:
`DENY` memiliki precedence terhadap `ALLOW`.

Trigger:
Capability resolution.

Allowed:
Capability aktif bila ada ALLOW dan tidak ada DENY.

Forbidden:
Mengizinkan aksi ketika salah satu applicable role memberi DENY.

Evidence:
`EffectiveCapabilityResolver`.

Status: IMPLEMENTED.

---

RULE-ID: ENT-001
MODULE: SaaS Entitlement

Rule:
Subscription entitlement dan user capability adalah concern terpisah.

Trigger:
Feature access resolution.

Allowed:
Periksa entitlement dan permission secara terpisah.

Forbidden:
Menganggap package entitlement menggantikan role/capability.

Evidence:
`EffectiveEntitlementResolver`; SaaS Core locked rules.

Status: IMPLEMENTED foundation + NORMATIVE.

---

RULE-ID: ENT-002
MODULE: SaaS Entitlement

Rule:
Ambiguous subscription, snapshot, atau override harus fail closed.

Trigger:
Lebih dari satu applicable state aktif.

Allowed:
Kembalikan denied/ambiguous result.

Forbidden:
Menebak precedence yang belum ditetapkan.

Evidence:
`EffectiveEntitlementResolver`.

Status: IMPLEMENTED.

---

RULE-ID: UI-001
MODULE: Frontend

Rule:
UI menggunakan Bahasa Indonesia; canonical API/state/capability tetap English secara internal.

Trigger:
Rendering UI atau penetapan API contract.

Allowed:
Map canonical code melalui formatter/dictionary terpusat.

Forbidden:
Menampilkan raw code seperti `PARTIALLY_PAID` kepada user umum.

Evidence:
API/UI Language Convention; Frontend UI/UX Constitution.

Status: NORMATIVE / LOCKED.

---

RULE-ID: WF-001
MODULE: Workflow

Rule:
Lifecycle bisnis menggunakan explicit transition, bukan universal status dropdown.

Trigger:
State change penting.

Allowed:
Dedicated action endpoint/service dengan guard, capability, audit/history.

Forbidden:
Direct generic status editing yang melewati domain transition.

Evidence:
State & Workflow; current action endpoints.

Status: IMPLEMENTED pattern + NORMATIVE.

---

RULE-ID: QUO-001
MODULE: Quotation

Rule:
Monetary calculation quotation authoritative di backend.

Trigger:
Create/update quotation line/version.

Allowed:
Client mengirim calculation input; backend menghitung amount/total.

Forbidden:
Mempercayai client-submitted total sebagai authoritative.

Evidence:
Quotation Pricing Convention; `QuotationPricingCalculator`.

Status: IMPLEMENTED + LOCKED.

---

RULE-ID: QUO-002
MODULE: Quotation

Rule:
Quotation version menyimpan pricing snapshot yang immutable.

Trigger:
Create/revise quotation version.

Allowed:
Snapshot pricing method/config/unit price/discount/tax/result.

Forbidden:
Merecalculate historical quotation menggunakan harga catalog terbaru.

Evidence:
Quotation Pricing Convention; quotation version model/service.

Status: IMPLEMENTED + LOCKED.

---

RULE-ID: MONEY-001
MODULE: Cross-Domain Money

Rule:
Authoritative monetary value harus menggunakan decimal arithmetic.

Trigger:
Persisted money calculation.

Allowed:
Exact decimal + explicit rounding boundary.

Forbidden:
PHP float atau JavaScript Number sebagai source of truth.

Evidence:
Quotation Pricing Convention; purchasing/finance decimal-safe tests/commits.

Status: IMPLEMENTED policy.

---

RULE-ID: INV-001
MODULE: Invoice

Rule:
Invoice lifecycle terpisah dari project operational lifecycle.

Trigger:
Project completion atau billing/payment activity.

Allowed:
Project selesai sementara invoice masih unpaid/partial/overdue.

Forbidden:
Menganggap project `COMPLETED` otomatis berarti invoice `PAID`.

Evidence:
State & Workflow.

Status: NORMATIVE / LOCKED.

---

RULE-ID: PAY-001
MODULE: Payment

Rule:
Payment adalah record terpisah; invoice payment state berasal dari valid payment/allocation logic.

Trigger:
Record/verify/allocate/reverse payment.

Allowed:
Koreksi melalui reversal/history.

Forbidden:
Set invoice `PAID` tanpa payment sah; menghapus transaksi salah tanpa jejak.

Evidence:
Payment routes/services; State & Workflow.

Status: IMPLEMENTED + NORMATIVE.

---

RULE-ID: PUR-001
MODULE: Purchase Request

Rule:
Purchase Request merepresentasikan kebutuhan pembelian dan tidak membuat stock/cash movement.

Trigger:
Create/submit/approve/reject/revise/cancel PR.

Allowed:
Membuat dokumen, item, state/history.

Forbidden:
Mengubah stock, AP, atau cash hanya karena PR disetujui.

Evidence:
`PurchaseRequestService`, PR API tests.

Status: IMPLEMENTED.

---

RULE-ID: PUR-002
MODULE: Purchase Order

Rule:
Purchase Order adalah komitmen/order ke supplier dan tidak otomatis membuat stock movement atau supplier payment.

Trigger:
Create/issue PO.

Allowed:
Create, allocate source PR, issue/cancel sesuai state/capability.

Forbidden:
Menambah stock atau mengurangi cash saat PO issue.

Evidence:
`PurchaseOrderService`, PO API tests.

Status: IMPLEMENTED.

---

RULE-ID: PUR-003
MODULE: Purchase Order

Rule:
Satu approved Purchase Request dapat dipenuhi oleh beberapa Purchase Order; remaining quantity authoritative di backend.

Trigger:
Create PO dari `source_purchase_request_id`.

Allowed:
Split allocation sesuai remaining quantity.

Forbidden:
Frontend menganggap original PR quantity selalu remaining; over-allocation.

Evidence:
`PurchaseOrderService`, PurchaseOrder API tests, current PO form.

Status: IMPLEMENTED.

---

RULE-ID: PUR-004
MODULE: Procurement Semantics

Rule:
Procurement type dibedakan menjadi `INVENTORY_ITEM`, `NON_STOCK_GOOD`, dan `SERVICE`.

Trigger:
PR/PO/Receipt item processing.

Allowed:
Preserve procurement type dari source sampai receiving.

Forbidden:
Menganggap semua purchase line adalah warehouse stock.

Evidence:
purchasing migration/models/resources/services.

Status: IMPLEMENTED.

---

RULE-ID: REC-001
MODULE: Goods Receipt

Rule:
Goods Receipt terpisah dari PO issue. Lifecycle receipt: `DRAFT → POSTED → REVERSED`.

Trigger:
Receiving.

Allowed:
Create/update draft, POST, REVERSE sesuai state.

Forbidden:
Menganggap PO `ISSUED` berarti barang sudah diterima.

Evidence:
GoodsReceipt routes/service; State & Workflow.

Status: IMPLEMENTED.

---

RULE-ID: REC-002
MODULE: Goods Receipt / Inventory

Rule:
Posting receipt membuat stock movement hanya untuk inventory-backed procurement item.

Trigger:
POST Goods Receipt.

Allowed:
`INVENTORY_ITEM` → Stock IN.

Forbidden:
Stock movement untuk `NON_STOCK_GOOD` atau `SERVICE`.

Evidence:
`GoodsReceiptService`; API tests.

Status: IMPLEMENTED.

---

RULE-ID: REC-003
MODULE: Goods Receipt

Rule:
Material identity receipt diwarisi dari Purchase Order item.

Trigger:
Receiving inventory PO item.

Allowed:
User mencatat received quantity.

Forbidden:
Client memilih/reassign material secara bebas pada receipt.

Evidence:
GoodsReceipt request validation + service.

Status: IMPLEMENTED.

---

RULE-ID: STOCK-001
MODULE: Inventory

Rule:
Perubahan stok fisik harus melalui movement ledger.

Trigger:
Receive/issue/transfer/adjustment.

Allowed:
Movement dengan source reference/audit.

Forbidden:
Direct arbitrary balance overwrite.

Evidence:
`StockMovement`; State & Workflow.

Status: IMPLEMENTED foundation + NORMATIVE.

---

RULE-ID: AP-001
MODULE: Supplier Bill / Payables

Rule:
Supplier Bill dan Supplier Payment adalah financial event yang berbeda.

Trigger:
Supplier invoice/payment.

Allowed:
Bill membentuk payable; payment menyelesaikan payable secara terpisah.

Forbidden:
Menganggap PO/Receipt sebagai cash payment.

Evidence:
SupplierBill/SupplierPayment services/tests.

Status: IMPLEMENTED.

---

RULE-ID: AP-002
MODULE: Supplier Payment

Rule:
Posted Supplier Payment memengaruhi payable/cash melalui posting flow dan reversal menjaga history.

Trigger:
POST/REVERSE supplier payment.

Allowed:
Cash transaction/allocation sesuai service rules.

Forbidden:
Delete posted settlement sebagai koreksi.

Evidence:
`SupplierPaymentService`, API tests.

Status: IMPLEMENTED.

---

RULE-ID: FIN-001
MODULE: Expense

Rule:
Expense memakai explicit approval/posting/void transition.

Trigger:
Submit/approve/reject/revise/post/void expense.

Allowed:
Transition berdasarkan state dan capability.

Forbidden:
Bypass approval/state guard.

Evidence:
Expense routes/services; `finance.expense.approve`.

Status: IMPLEMENTED.

---

RULE-ID: EXT-001
MODULE: External Integration

Rule:
Provider state eksternal tidak boleh menggantikan SIGNOVA core state.

Trigger:
Marketplace/WhatsApp/ANA/provider event.

Allowed:
Adapter menerjemahkan external payload ke canonical command/event.

Forbidden:
Menggunakan raw provider status sebagai project/workflow state.

Evidence:
Master Application Flow; State & Workflow.

Status: NORMATIVE / LOCKED.

---

RULE-ID: PWA-001
MODULE: Offline/PWA

Rule:
Offline mode tidak boleh mengklaim sukses untuk action yang membutuhkan server confirmation.

Trigger:
Network unavailable/unstable.

Allowed:
Cache app shell/static asset dan approved bootstrap metadata.

Forbidden:
Finalize issue/void/verify/reverse/privileged action secara lokal seolah server menerima.

Evidence:
offline policy; Frontend UI/UX Constitution.

Status: IMPLEMENTED policy + NORMATIVE.

---

RULE-ID: GOV-001
MODULE: Project Governance

Rule:
Entry di Master Flow/Capability Map tidak sama dengan fitur yang sudah implemented.

Trigger:
Planning atau development berdasarkan dokumen target.

Allowed:
Gunakan target document sebagai arah dan dependency map.

Forbidden:
Mengklaim capability tersedia tanpa source/schema/test evidence.

Evidence:
Project audit + governance baseline.

Status: NORMATIVE.
