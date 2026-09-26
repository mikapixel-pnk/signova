# Hak Akses & Authorization SIGNOVA

**Terakhir diaudit:** 2026-09-26

## 1. Model Authorization

SIGNOVA menggunakan capability-driven authorization.

Backend adalah authoritative.

Tenant user authorization:

```text
authenticated user
→ active tenant membership
→ assigned tenant role(s)
→ role_capabilities
→ active capability
→ DENY precedence terhadap ALLOW
```

Relevant code:
- `EffectiveCapabilityResolver`
- `RequireCapability`
- `ResolveTenantContext`
- `ResolveBusinessContext`

Platform authorization terpisah:
- `PlatformCapabilityResolver`
- `RequirePlatformCapability`

Entitlement juga terpisah dari role capability.

## 2. Master Role Tenant

Seeder/runtime audit mengonfirmasi:

- `OWNER` — Pemilik / Owner
- `ADMIN` — Administrator
- `SALES` — Penjualan / Sales
- `FINANCE` — Keuangan

Tenant role dapat memiliki explicit capability override. Seeder sengaja tidak menimpa ALLOW/DENY yang sudah ada pada tenant role.

## 3. Capability Registry Aktual

### Beranda
- `dashboard.view`

### Pelanggan
- `customer.view`
- `customer.create`
- `customer.update`

### Barang & Jasa
- `catalog.view`
- `catalog.manage`

### Penawaran
- `quotation.view`
- `quotation.create`
- `quotation.update`
- `quotation.issue`

### Tagihan
- `invoice.view`
- `invoice.create`
- `invoice.update`
- `invoice.issue`
- `invoice.void` — sensitif

### Pembayaran
- `payment.view` — sensitif
- `payment.record` — sensitif
- `payment.verify` — sensitif
- `payment.reverse` — sensitif

### Keuangan
- `finance.receivable.view`
- `finance.payable.view`
- `finance.payable.manage`
- `finance.summary.view`
- `finance.cash_bank.view`
- `finance.cash_bank.manage`
- `finance.income.view`
- `finance.income.manage`
- `finance.expense.view`
- `finance.expense.manage`
- `finance.expense.approve`
- `finance.margin.view`

Finance capability pada seeder saat ini dikategorikan sensitif.

### Proyek
- `project.view`
- `project.create`
- `project.update`

### Pemasok
- `supplier.view`
- `supplier.create`
- `supplier.update`
- `supplier.view_price` — sensitif

### Pembelian
- `purchasing.request`
- `purchasing.approve_request` — sensitif
- `purchasing.create_po`
- `purchasing.approve_po` — sensitif
- `purchasing.cancel_po` — sensitif

### Stok & Gudang
- `inventory.view`
- `inventory.receive`

### Tim & Hak Akses
- `team.user.view`
- `team.user.manage` — sensitif
- `team.role.view`
- `team.role.manage` — sensitif

### Pengaturan
- `settings.view`
- `settings.manage` — sensitif

## 4. Baseline Master Role → Capability

### OWNER

Runtime audit mengonfirmasi OWNER memiliki baseline terluas, termasuk:

- dashboard
- customer create/update/view
- catalog manage/view
- quotation create/update/issue/view
- invoice create/update/issue/void/view
- payment record/verify/reverse/view
- finance receivable/payable/summary/cash-bank/income/expense/margin
- `finance.expense.approve`
- project create/update/view
- supplier create/update/view/view_price
- purchasing request/approve request/create PO/approve PO/cancel PO
- inventory view/receive
- team user/role view/manage
- settings view/manage

OWNER **bukan** bypass terhadap middleware/state guard.

### ADMIN

Baseline runtime:
- `dashboard.view`
- customer view/create/update
- catalog view/manage
- `quotation.view`
- project view/create/update
- team user view/manage
- team role view/manage
- settings view/manage

ADMIN tidak otomatis memperoleh finance/payment/purchasing sensitive capability.

### SALES

Baseline runtime:
- `dashboard.view`
- customer view/create/update
- `catalog.view`
- quotation view/create/update/issue
- `invoice.view`
- project view/create/update

### FINANCE

Baseline runtime:
- `dashboard.view`
- `customer.view`
- invoice view/create/update/issue/void
- payment view/record/verify/reverse
- finance receivable/payable/summary
- cash-bank view/manage
- income view/manage
- expense view/manage
- margin view
- `project.view`

Catatan:
Current master FINANCE baseline tidak memiliki `finance.expense.approve`; OWNER memiliki capability tersebut.

## 5. Mapping Route Pembelian / Inventory

### Purchase Request
- list/store/show/update/submit/revise/cancel → `purchasing.request`
- approve/reject → `purchasing.approve_request`

### Purchase Order
- list/store/show/update → `purchasing.create_po`
- issue → `purchasing.approve_po`
- cancel → `purchasing.cancel_po`

### Goods Receipt
- list/show → `inventory.view`
- create/update/post/reverse → `inventory.receive`

### Inventory Master
- materials/warehouses read → `inventory.view`
- materials/warehouses create/update → saat ini `inventory.receive`

**Observasi:** pemakaian `inventory.receive` untuk write inventory master adalah implementasi saat ini. Jangan mengganti capability ini diam-diam. Bila ingin capability inventory-master khusus, itu harus menjadi keputusan authorization eksplisit.

## 6. Platform Capability

Seeder mendefinisikan:

- `platform.dashboard.view`
- `platform.tenant.view`
- `platform.tenant.manage`
- `platform.subscription.view`
- `platform.subscription.manage`
- `platform.plan.view`
- `platform.plan.manage`
- `platform.order.view`
- `platform.order.manage`
- `platform.usage.view`
- `platform.integration.view`
- `platform.notification.view`
- `platform.audit.view`
- `platform.settings.view`
- `platform.settings.manage`

Platform role:
- `SUPER_ADMIN` — Super Admin SIGNOVA

Seeder memberikan capability tersebut kepada SUPER_ADMIN kecuali explicit existing platform override sudah ada.

Runtime platform matrix supplemental audit belum berhasil karena query audit memakai `platform_roles.is_active`, sedangkan schema/seeder menggunakan `status`.

Status:

`UNKNOWN / NEEDS CONFIRMATION`

## 7. Aturan Capability Resolution

Tenant:
- user inactive → deny
- tenant membership inactive → tidak dihitung
- role inactive → tidak dihitung
- capability inactive → tidak dihitung
- ada `DENY` → deny
- ada `ALLOW` dan tidak ada DENY → allow

Platform:
- user inactive → deny
- platform role/capability inactive → tidak dihitung
- DENY precedence berlaku

## 8. Aturan Frontend

Frontend boleh:
- hide/disable action berdasarkan active capability,
- menyesuaikan navigation,
- menampilkan penjelasan akses.

Frontend tidak boleh:
- menjadi final authorization authority,
- menciptakan capability sendiri,
- menganggap OWNER bypass permission,
- menganggap package entitlement sama dengan capability.

Deep link tetap harus diauthorize backend.

## 9. Area Protection

Protected tenant API pada audit menggunakan:
- `auth:sanctum`
- `tenant.context`
- `business.context` bila dibutuhkan
- capability middleware

Public token route sengaja tidak memakai tenant login dan wajib menjaga token/domain safety.

Jangan menambah unprotected internal business route tanpa security review.

## 10. Prosedur Perubahan Permission

Sebelum mengubah permission:

```text
Actor:
Role:
Capability:
Sensitive?:
Route/Action:
State Guard:
Tenant Scope:
Business Scope:
Package/Entitlement:
Frontend Visibility:
Tests:
```

Update file ini pada milestone yang sama.
