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
3. Inventory page masih generic `ModulePage`; inventory API/service foundation tersedia.
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
