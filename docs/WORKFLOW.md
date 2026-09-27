# Workflow Bisnis SIGNOVA

**Terakhir diaudit:** 2026-09-26

Dokumen ini membedakan flow yang **sudah terimplementasi** dengan flow **target/normatif**.

---

FLOW-ID: AUTH-001
MODULE: Authentication & Context

Upstream:
User belum login atau belum memiliki context aktif.

Current:
Login/session → tenant resolution → business resolution → capability resolution.

Downstream:
Tenant/business-scoped API.

Actor:
User.

Capability:
Sesuai endpoint.

Data affected:
Session/context; domain data hanya berubah bila endpoint berikutnya melakukan action.

Next action:
Masuk ke module/action yang diizinkan.

Allowed side effects:
Session/context resolution.

Forbidden side effects:
Cross-tenant/business exposure atau guessing context.

Status: IMPLEMENTED.

---

FLOW-ID: SALES-001
MODULE: Quotation

Upstream:
Customer + kebutuhan barang/jasa.

Current:
Draft quotation → version/revision → send/public action → approval/rejection.

Downstream:
Approved quotation dapat dilanjutkan ke invoice melalui action yang sah.

Actor:
Sales/authorized user; customer pada tokenized public action.

Capability:
`quotation.view/create/update/issue`; handoff invoice memerlukan `invoice.create`.

Data affected:
Quotation, version, items, status/history/public link.

Next action:
Follow-up, approve/reject, revise, atau create invoice bila memenuhi rule.

Allowed side effects:
Quotation state/history.

Forbidden side effects:
Client-authoritative total; mutation historical pricing snapshot.

Status: IMPLEMENTED.

---

FLOW-ID: BILLING-001
MODULE: Invoice / Customer Payment

Upstream:
Direct invoice atau approved quotation.

Current:
Invoice `DRAFT → ISSUED → PARTIALLY_PAID/PAID`; VOID melalui explicit action.

Downstream:
Receivable/finance/cash sesuai payment verification.

Actor:
Finance/authorized tenant user; customer dapat menggunakan public payment flow.

Capability:
`invoice.*`, `payment.*`, finance view capability.

Data affected:
Invoice, invoice items, history, payment, allocation, reversal, cash record sesuai service.

Next action:
Record/verify/allocate/reverse payment atau void invoice bila sah.

Allowed side effects:
Payment-derived invoice state.

Forbidden side effects:
Mark paid tanpa payment sah; destructive correction tanpa history.

Status: IMPLEMENTED.

---

FLOW-ID: PUR-001
MODULE: Purchase Request

Upstream:
Kebutuhan procurement internal.

Current:
`DRAFT → SUBMITTED → APPROVED / REJECTED / CANCELLED`, dengan revise sesuai guard.

Downstream:
Approved PR → Purchase Order.

Actor:
Requester + Approver.

Capability:
`purchasing.request`, `purchasing.approve_request`.

Data affected:
PR, items, procurement semantics, status history.

Next action:
PR `APPROVED` → **Buat Pesanan Pembelian**.

Allowed side effects:
Request/state/history.

Forbidden side effects:
Stock movement, payable, atau cash movement.

Status: IMPLEMENTED.

---

FLOW-ID: PUR-002
MODULE: Purchase Order

Upstream:
Approved PR atau direct PO.

Current:
`DRAFT → ISSUED → PARTIALLY_RECEIVED → RECEIVED`, atau `CANCELLED`.

Downstream:
Goods Receipt.

Actor:
Purchasing/Owner sesuai capability.

Capability:
`purchasing.create_po`, `purchasing.approve_po`, `purchasing.cancel_po`.

Data affected:
PO header/items, source PR allocation, state history.

Next action:
PO `ISSUED` → **Catat Penerimaan**.

Allowed side effects:
PO state/history + source allocation.

Forbidden side effects:
Stock movement atau cash payment pada issue.

Status: BACKEND + FRONTEND PO WORKFLOW IMPLEMENTED.

---

FLOW-ID: INV-001
MODULE: Goods Receipt

Upstream:
PO `ISSUED` / `PARTIALLY_RECEIVED`.

Current:
Goods Receipt `DRAFT → POSTED → REVERSED`.

Downstream:
Inventory movement untuk inventory item; Supplier Bill/AP dapat mengikuti receipt.

Actor:
Inventory/receiving user.

Capability:
`inventory.view`, `inventory.receive`.

Data affected:
Receipt, receipt items/history, stock movement, PO received state.

Next action:
Jika partial → lanjut receiving. Jika selesai → catat Supplier Bill.

Allowed side effects:
Stock IN hanya untuk inventory-backed line.

Forbidden side effects:
Material reassignment oleh client; stock movement untuk service/non-stock good; cash payment.

Status: BACKEND IMPLEMENTED. FRONTEND RECEIVING MASIH PLACEHOLDER.

---

FLOW-ID: INV-002
MODULE: Stock Adjustment

Upstream:
Selisih/koreksi stok fisik pada material `TRACKED` dan warehouse aktif dalam
tenant/business context yang sama.

Current:
Stock Adjustment `DRAFT → POSTED → REVERSED`.

Downstream:
Stock Balance/read model berubah melalui movement ledger.

Actor:
Inventory/warehouse user sesuai effective capability.

Capability:
- list/detail → `inventory.view`
- create/update/post/reverse → `inventory.adjust`

Data affected:
Stock adjustment, adjustment items, immutable status history, dan stock movement.

Next action:
- `DRAFT` → POST adjustment bila data sudah benar;
- `POSTED` → REVERSE hanya bila perlu koreksi;
- `REVERSED` → terminal untuk dokumen foundation ini.

Allowed side effects:
- POST membuat movement `ADJUSTMENT`;
- REVERSE membuat movement kompensasi `ADJUSTMENT_REVERSAL`;
- reversal mempertahankan movement asli dan menghubungkan
  `reversal_of_movement_id`.

Forbidden side effects:
- direct-edit saldo/on-hand;
- cash transaction atau supplier payable;
- material `NOT_TRACKED`;
- quantity delta nol;
- quantity lebih dari 4 digit desimal;
- cross-tenant/cross-business warehouse/material;
- destructive correction terhadap movement awal.

Status:
BACKEND IMPLEMENTED + TESTED + STAGING DEFAULT-DENY UAT VERIFIED.
FRONTEND STOCK ADJUSTMENT BELUM DIIMPLEMENTASIKAN.

Unresolved:
negative-stock policy, second-approval threshold/policy, baseline role mapping,
dan package/entitlement enforcement tetap `UNKNOWN / NEEDS CONFIRMATION`.

---

FLOW-ID: AP-001
MODULE: Supplier Bill

Upstream:
Supplier transaction, optional link ke PO/Receipt.

Current:
Create/update Supplier Bill → POST/CANCEL sesuai state.

Downstream:
Payable balance → Supplier Payment.

Actor:
Finance.

Capability:
`finance.payable.view`, `finance.payable.manage`.

Data affected:
Supplier bill, status history, payable.

Next action:
Bayar pemasok.

Allowed side effects:
Payable recognition sesuai posting logic.

Forbidden side effects:
Cash decrease hanya karena bill dicatat.

Status: BACKEND IMPLEMENTED. FRONTEND PAYABLES MASIH PLACEHOLDER.

---

FLOW-ID: AP-002
MODULE: Supplier Payment

Upstream:
Posted/open Supplier Bill.

Current:
Create/update Supplier Payment → POST → optional REVERSE.

Downstream:
Payable balance + cash transaction/history.

Actor:
Finance.

Capability:
`finance.payable.manage` dan finance cash permission sesuai enforcement aktual.

Data affected:
Supplier payment, allocations, cash transaction, reversal link/history.

Next action:
Reconcile/complete settlement.

Allowed side effects:
Payable down + cash/bank down saat posting.

Forbidden side effects:
Delete posted payment sebagai koreksi.

Status: BACKEND IMPLEMENTED. FULL FRONTEND BELUM TERBUKTI.

---

FLOW-ID: FIN-EXP-001
MODULE: Expense

Upstream:
Expense entry/evidence.

Current:
Draft/record → submit → approve/reject/revise → post → void sesuai state.

Downstream:
Cash/finance summary.

Actor:
Finance/requester/approver.

Capability:
`finance.expense.view/manage/approve`.

Data affected:
Expense, evidence, cash/read model sesuai posting.

Next action:
Review/approve/post/correct.

Allowed side effects:
Finance movement pada posting yang sah.

Forbidden side effects:
Approval bypass/destructive correction.

Status: IMPLEMENTED.

---

FLOW-ID: FIN-INC-001
MODULE: Income

Upstream:
Manual non-invoice income.

Current:
Create/update → post → void sesuai state.

Downstream:
Cash/finance summary.

Actor:
Finance.

Capability:
`finance.income.view/manage`.

Data affected:
Income + cash/read model bila posted.

Next action:
Post atau void.

Allowed side effects:
Finance movement melalui service.

Forbidden side effects:
Direct ledger mutation.

Status: IMPLEMENTED.

---

FLOW-ID: SAAS-ENT-001
MODULE: Subscription / Entitlement

Upstream:
Active tenant + subscription/plan data.

Current:
Resolve feature availability dari subscription aktif + entitlement snapshot + maksimum satu applicable override.

Downstream:
Feature availability.

Actor:
System/platform admin mengelola; tenant user mengonsumsi hasil.

Capability:
Platform capability untuk platform administration; tenant capability tetap terpisah.

Data affected:
Entitlement resolution.

Next action:
Allow/deny feature atau masuk ke commercial lifecycle.

Allowed side effects:
Resolver bersifat read/effective-state resolution.

Forbidden side effects:
Guessing ambiguous subscription/snapshot/override.

Status: FOUNDATION IMPLEMENTED. FULL LIFECYCLE AUTOMATION PERLU RE-AUDIT.

---

## Master Operational Flow — Target / Normatif

Master Application Flow menetapkan arah:

```text
Lead / Customer Need
→ Quotation
→ Survey (jika diperlukan)
→ Design / Customer Approval (jika diperlukan)
→ Project / Job
→ Material Need
→ Stock / Procurement
→ Production
→ QC
→ Installation / Handover
→ Invoice
→ Payment
→ Profitability / Attention
```

Status:

`TARGET / NORMATIVE`

Branch audit saat ini belum membuktikan seluruh stage tersebut sudah implemented.

Agent wajib:
- tidak membuat stage baru secara opportunistic,
- hanya implement bila scope task jelas,
- menjaga context upstream/current/downstream,
- mengizinkan stage skipping hanya bila domain/template rule menyatakannya.

## Guided Next Action

Detail/workflow sebaiknya memiliki satu dominant next action yang sah.

Contoh yang sudah align:
- PR `APPROVED` → **Buat Pesanan Pembelian**
- PO `DRAFT` → **Terbitkan Pesanan**
- PO `ISSUED` → **Catat Penerimaan** (backend siap, UI belum selesai)
- Receipt/PO `RECEIVED` → **Catat Tagihan Pemasok** (backend AP foundation tersedia, UI belum selesai)

Jangan membuat CTA aktif menuju screen yang belum functional tanpa penanganan state implementasi yang jelas.

## Inventory Master Workflow — 2026-09-26

Status: **IMPLEMENTED — backend**.

### Inventory Master

Kategori Persediaan -> Material -> Purchasing / Inventory.

Material menyimpan:

- inventory type;
- stock tracking;
- unit;
- minimum stock;
- reorder point;
- maximum stock;
- description.

Gudang adalah lokasi fisik terpisah.

Master mutation tidak boleh:

- membuat stock movement;
- mengubah saldo stok;
- mengubah Purchase Order state;
- membuat Goods Receipt;
- membuat financial transaction.

### Procurement semantics

Flow canonical:

TRACKED material
-> INVENTORY_ITEM
-> Goods Receipt POST
-> Stock IN

NOT_TRACKED material
-> NON_STOCK_GOOD
-> Goods Receipt
-> no stock movement

SERVICE
-> SERVICE
-> Goods Receipt
-> no stock movement

Explicit procurement type yang bertentangan dengan `stock_tracking`
ditolak backend.

### Authorization

- `inventory.view`
  -> read category/material/warehouse

- `inventory.master.manage`
  -> create/update category/material/warehouse

- `inventory.receive`
  -> Receiving transaction

Frontend Inventory Master dan read-only Stock visibility sudah implemented,
tested, deployed, dan staging UAT verified.
