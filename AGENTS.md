# Aturan Kerja AI Agent SIGNOVA

**Status:** Baseline tata kelola proyek
**Berlaku untuk:** seluruh repository SIGNOVA, kecuali aturan lokal yang lebih spesifik dan tidak bertentangan dengan aturan keselamatan/tata kelola di file ini.

## 1. Tujuan

File ini menjaga pengembangan SIGNOVA tetap konsisten ketika dikerjakan oleh AI Agent pada chat, sesi, branch, atau worktree yang berbeda.

AI Agent wajib memahami kondisi aktual project sebelum menulis kode. Dokumen arsitektur, Master Flow, Capability Map, dan Kitab memberi arah, tetapi source code, schema database, routes, tests, configuration, dan runtime evidence menentukan apa yang benar-benar sudah terimplementasi.

## 2. Wajib Dibaca Sebelum Menulis

Sebelum melakukan perubahan apa pun, baca sekurangnya:

1. `AGENTS.md`
2. `docs/CURRENT_STATE.md`
3. dokumen domain yang relevan:
   - `docs/ARCHITECTURE.md`
   - `docs/DOMAIN_RULES.md`
   - `docs/WORKFLOW.md`
   - `docs/PERMISSIONS.md`
   - `docs/DECISIONS.md`
4. source code aktual upstream/current/downstream
5. test yang sudah ada untuk domain tersebut
6. route/API contract dan frontend consumer yang terdampak
7. migration/schema bila perubahan menyentuh persistence

Untuk frontend, baca juga `frontend/AGENTS.md` dan konstitusi UI/UX yang berlaku.
Untuk backend, baca juga `backend/AGENTS.md`.

**Catatan penting:** jangan menambah dependency/tooling hanya karena bootstrap framework menyarankannya. Penambahan dependency wajib menjadi task eksplisit dan mendapat persetujuan.

## 3. Alur Kerja Wajib

Urutan kerja:

`AUDIT → BACKUP → PATCH KECIL → DIFF → TEST → UAT → COMMIT → CLEAN`

Tahap **AUDIT, DIFF, dan TEST tidak boleh dilewati**.

### AUDIT
Sebelum patch, jawab:

- Apa upstream yang memicu flow?
- Entity/current state apa yang sedang diubah?
- Apa downstream yang bergantung pada perubahan?
- Siapa actor?
- Capability/permission apa yang dibutuhkan?
- Tabel/database apa yang terdampak?
- API request/response apa yang terdampak?
- Frontend consumer apa yang terdampak?
- Apakah ada dampak keuangan?
- Apakah ada dampak inventory?
- Apakah ada dampak integration?
- Test apa yang membuktikan perubahan?

Jika salah satu belum diketahui, audit terlebih dahulu. Jangan menebak.

### BACKUP
- Perubahan source: buat backup source secara targeted.
- Perubahan schema/data: backup database terlebih dahulu.
- Backup bukan alasan untuk melakukan operasi destruktif tanpa audit.

### PATCH KECIL
- Terapkan perubahan sekecil dan seaman mungkin.
- Jangan blind patch.
- Exact-string patch harus memastikan anchor unik.
- Jangan refactor area yang tidak diperlukan task.
- Jangan mengubah contract lintas domain secara diam-diam.

### DIFF
- Selalu jalankan `git diff --check`.
- Review seluruh diff sebelum test.
- Stage file secara eksplisit.
- Jangan gunakan `git add .`.

### TEST
- Jalankan targeted test lebih dahulu.
- Untuk frontend minimal: lint + TypeScript + production build jika area frontend berubah.
- Untuk backend minimal: test domain terkait.
- Jangan menyatakan berhasil hanya karena build/lint hijau jika behavior belum diuji.
- Monetary/quantity logic wajib menggunakan exact decimal path yang berlaku.
- Floating point PHP/JavaScript bukan nilai authoritative untuk transaksi finansial.

### UAT
- UAT dilakukan pada flow nyata yang terdampak.
- Browser/mobile UAT hanya dinyatakan lulus bila ada evidence.
- Periksa success, error, empty/loading, capability denial, dan langkah berikutnya.

### COMMIT
- Commit hanya setelah diff + test + UAT sesuai scope.
- Jangan commit bila user meminta review terlebih dahulu.
- Jangan force push.

### CLEAN
- Pastikan worktree kembali bersih setelah milestone.
- Jangan reset/clean file milik agent/worktree lain.

## 4. Aturan Keselamatan

Dilarang tanpa audit dan persetujuan eksplisit:

- `git reset --hard`
- arbitrary `git clean`
- force push
- drop/truncate database
- `migrate:fresh`
- mengedit migration yang sudah applied
- menghapus audit/history untuk “memperbaiki” data
- mengubah status bisnis langsung di database
- mengubah finance/inventory ledger secara manual tanpa domain flow
- menambah dependency baru
- mengubah konfigurasi infra secara luas
- menyentuh aplikasi lain di server

Gunakan `set +e` untuk script operasional agar kegagalan dapat diaudit dan tidak memicu chain action yang tersembunyi.

Perubahan schema wajib menggunakan migration baru dan prosedur migration staging resmi.

## 5. Isolasi Worktree / Branch

Snapshot audit 2026-09-26 menunjukkan worktree terpisah:

- `/srv/signova` → `feat/foundation-backend`
- `/srv/signova-worktrees/platform-admin` → `feat/platform-admin`
- `/srv/signova-worktrees/saas-07b` → `feat/saas-07b-onboarding`

AI Agent hanya boleh mengubah worktree/branch yang ditetapkan pada task.

Dilarang:

- reset branch lain,
- clean worktree lain,
- stage file worktree lain,
- merge/rebase branch lain tanpa task eksplisit,
- menganggap commit branch lain sudah tersedia di current branch.

## 6. Hirarki Sumber Kebenaran

Gunakan urutan berikut.

### A. Implementasi Aktual
Paling kuat untuk menjelaskan kondisi saat ini:

- database schema aktual,
- applied migrations,
- source code,
- routes,
- authorization middleware,
- tests,
- runtime configuration,
- current frontend consumers.

### B. Tata Kelola / Konvensi yang Dikunci
Dipakai untuk memastikan implementasi tetap konsisten:

- API/UI Language Convention,
- Quotation Pricing Convention,
- Frontend UI/UX Constitution,
- security/infrastructure rules,
- state/workflow rules.

### C. Master Flow / Product Scope / Capability Map
Dipakai sebagai arah pengembangan dan dependency map.

Dokumen target **bukan bukti bahwa fitur sudah terimplementasi**.

### D. CURRENT_STATE
Ringkasan posisi development aktif. Wajib diperbarui setelah milestone penting.

Jika ada pertentangan:
- jangan memilih secara diam-diam,
- catat sebagai `OBSERVATION`,
- gunakan `UNKNOWN / NEEDS CONFIRMATION` bila belum cukup evidence,
- minta keputusan jika contract perlu diubah.

## 7. Bedakan Kondisi Saat Ini dan Target

Setiap agent wajib membedakan:

- `IMPLEMENTED` — terbukti dari source/schema/test/runtime.
- `NORMATIVE / LOCKED` — aturan yang wajib diikuti dari dokumen governance.
- `TARGET / PLANNED` — ada di Master Flow/Capability Map tetapi belum terbukti implemented.
- `UNKNOWN / NEEDS CONFIRMATION` — evidence belum cukup.

Dilarang menyebut `TARGET / PLANNED` sebagai fitur yang sudah tersedia.

## 8. Aturan Pengembangan Capability

Capability baru tidak boleh dibuat hanya karena “ada di roadmap”.

Sebelum implementasi capability baru, minimal harus jelas:

- Problem
- Actor
- Package/Entitlement
- Domain Owner
- Trigger
- Source of Truth
- State/Lifecycle
- Capability/Permission
- Tenant/Security Boundary
- Upstream
- Downstream
- Allowed Side Effects
- Forbidden Side Effects
- API Contract
- Frontend Flow
- Acceptance Tests

Jika salah satu belum jelas, tandai:

`UNKNOWN / NEEDS CONFIRMATION`

## 9. Master Flow Contract

Untuk pekerjaan domain penting, agent harus dapat mengisi:

```text
Upstream:
Current:
Downstream:
Actor:
Capability:
State Changed:
Data Affected:
Financial Impact:
Inventory Impact:
Integration Impact:
Next Action:
Risks:
Tests/UAT:
```

Jika belum dapat mengisi, jangan patch.

## 10. Aturan Frontend

- Bahasa UI: Bahasa Indonesia yang natural.
- Canonical API/state/code tetap English secara internal.
- Backend adalah source of truth business rule.
- Mobile-first dan task-first.
- Menu adalah tempat mencari; flow adalah cara bekerja.
- Detail/workflow harus menjawab: posisi sekarang, blocker, dan langkah berikutnya.
- Hindari dead-end “buka menu X” bila konteks dapat dibawa dengan deep link.
- Context downstream harus diprefill bila source of truth mendukung.
- Hidden UI bukan security; backend tetap authorize.
- Touch target minimum 44x44 untuk control utama.
- Jangan tampilkan raw canonical code/JSON kepada user.
- Jangan cache data sensitif sembarangan.
- Offline tidak boleh memalsukan keberhasilan action yang wajib dikonfirmasi server.

## 11. Guard Keuangan & Inventory

Sebelum menyentuh finance/inventory:

- cek state transition,
- cek idempotency/reversal,
- cek ledger/movement,
- cek exact decimal,
- cek tenant/business scope,
- cek capability,
- cek downstream balance/read model.

Purchase Request dan Purchase Order bukan stock movement.
Goods Receipt POST dapat membuat inventory movement sesuai procurement semantics.
Supplier Bill dan Supplier Payment memiliki side effect berbeda; jangan digabung.

## 12. Pemeliharaan Dokumentasi

Setelah pekerjaan penting:

- update `docs/CURRENT_STATE.md`,
- update `docs/DECISIONS.md` jika ada keputusan arsitektur/bisnis baru,
- update `docs/DOMAIN_RULES.md` bila rule benar-benar berubah,
- update `docs/WORKFLOW.md` bila flow berubah,
- update `docs/PERMISSIONS.md` bila capability/role mapping berubah.

Dokumentasi sebaiknya ikut commit yang sama dengan perubahan contract terkait, kecuali user meminta review dokumentasi terpisah.

## 13. Disiplin Evidence

Jangan mengarang.

Gunakan:

`UNKNOWN / NEEDS CONFIRMATION`

untuk hal yang belum terbukti.

Evidence yang baik:

- file + class/method,
- route + middleware,
- migration/table/constraint,
- automated test,
- runtime config,
- locked project document,
- commit hash yang relevan.

## 14. Definition of Done untuk AI Agent

Pekerjaan belum selesai sampai:

- audit scope selesai,
- backup sesuai risiko tersedia,
- diff direview,
- test hijau,
- UAT sesuai scope dilakukan/ditandai pending,
- permission/tenant boundary diperiksa,
- financial/inventory impact dinyatakan,
- CURRENT_STATE diperbarui bila milestone,
- tidak ada file asing ter-stage,
- worktree bersih atau WIP dijelaskan dengan jelas.
