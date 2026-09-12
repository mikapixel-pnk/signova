# SIGNOVA API Capability Registry

Status: Baseline

Authorization backend menggunakan capability, bukan nama role.

| Capability | Domain | Penggunaan awal |
|---|---|---|
| dashboard.view | Dashboard | Dashboard utama |
| customer.view | Customer | Melihat pelanggan |
| customer.create | Customer | Membuat pelanggan |
| customer.update | Customer | Mengubah pelanggan |
| catalog.view | Catalog | Melihat barang/jasa/kategori/satuan |
| catalog.manage | Catalog | Membuat/mengubah barang/jasa/kategori |
| quotation.view | Quotation | Melihat penawaran |
| quotation.create | Quotation | Membuat penawaran |
| quotation.update | Quotation | Mengubah draft penawaran |
| quotation.issue | Quotation | Menerbitkan penawaran |
| invoice.view | Invoice | Melihat tagihan |
| invoice.create | Invoice | Membuat tagihan |
| invoice.issue | Invoice | Menerbitkan tagihan |
| invoice.void | Invoice | Void tagihan |
| payment.view | Payment | Melihat pembayaran |
| payment.record | Payment | Mencatat pembayaran |
| payment.verify | Payment | Verifikasi pembayaran |
| payment.reverse | Payment | Membalik pembayaran |
| settings.view | Settings | Melihat pengaturan |
| settings.manage | Settings | Mengubah pengaturan |

## Rules

Effective permission ditentukan backend dari:

tenant context
→ membership
→ role
→ role capability
→ capability status
→ entitlement (saat subscription gate aktif)
→ domain guard

Frontend tidak pernah mengirim effective capability sebagai source of truth.
