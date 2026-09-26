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
