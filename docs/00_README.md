# Portal QCC SIMGROUP Batch 4 — Dokumentasi Implementasi

**Versi:** 1.0 — Draft teknis untuk review  
**Tanggal penyusunan:** 21 Oktober 2026  
**Dasar:** BRD Portal QCC SIMGROUP Batch 4 v1.0, 12 Oktober 2026 (11 halaman, draft)  
**Stack target:** Laravel 13, React + Inertia + TypeScript, Tailwind CSS 4, shadcn/ui **Base UI**, MySQL, Redis, OpenAI API.  
**Cakupan:** proses bisnis target **1–9** pada Bab 6 BRD; termasuk seluruh **8 Step QCC**. Proses 10 berupa dashboard/laporan agregat lanjutan tidak menjadi deliverable penuh. Dashboard operasional dasar tetap dibutuhkan.

## Cara membaca paket ini

| File | Isi / fungsi |
| --- | --- |
| `01_PROJECT_GOALS.md` | Sasaran, KPI, batasan, definisi selesai |
| `02_PRD.md` | PRD modul, field, alur, non-fungsional, scope |
| `03_USER_STORIES.md` | User stories berkode + acceptance criteria |
| `04_ERD.md` | Mermaid ERD, tabel, kolom, relasi, indeks, constraint |
| `05_DESIGN_SYSTEM.md` | Token UI, layout, Poppins, komponen Base UI, forms, Data Table, Sonner |
| `06_WORKFLOWS_RBAC.md` | 5 role, permissions, state machine, keputusan review |
| `07_ARCHITECTURE.md` | Modul Laravel/Inertia, deployment, service boundary |
| `08_OPENAI_INTEGRATION.md` | AI team matching, evidence scoring, validasi schema, prompt safety |
| `09_FILE_EVIDENCE.md` | Upload PDF 10+ MB, preview, storage, processing, audit |
| `10_DATABASE_PERFORMANCE.md` | Eager loading, query, indeks, pagination, cache, N+1 |
| `11_QA_ACCEPTANCE.md` | QA, security, performance, scenario uji |
| `12_TASKLIST_AI.md` | Checklist bertahap dengan task ID, dependency, bukti verifikasi |
| `13_OPEN_DECISIONS.md` | Hal belum disahkan, asumsi dan keputusan stakeholder |
| `14_FORM_CATALOG.md` | Inventaris semua form, field, placeholder, validasi, page UI |
| `AGENTS.md` | Aturan bagi coding agent untuk mengerjakan dan menandai task |

## Konvensi sumber

- **[BRD]** = eksplisit ditetapkan pada BRD.
- **[REKOMENDASI]** = rancangan teknis/UX untuk memenuhi BRD, perlu persetujuan owner/IT.
- **[KONFIRMASI]** = keputusan belum tersedia dalam BRD; jangan hardcode sebagai fakta bisnis.
- Setiap hal berbasis AI wajib **human-in-the-loop**; OpenAI tidak melakukan approval atau menghitung skor berbobot final.
- **Tidak ada emoji** di UI, dokumen copy, toast, button, maupun status: gunakan **Lucide Icons**.

## Aturan absolut proyek

1. **Semua halaman aplikasi pascalogin** memakai urutan `Breadcrumb → Title → Description → Actions/Content`. Halaman login/forgot password adalah pengecualian karena tidak punya hirarki navigasi aplikasi; gunakan heading dan deskripsi.
2. Warna utama `#2547F9`, latar `#F7F8FD`, surface `#FFFFFF`; light-only. Font **Poppins**.
3. Komponen **shadcn/ui Base UI**. Tabel list mengikuti https://ui.shadcn.com/docs/components/base/data-table; aksi tiap baris memakai tombol ikon **Ellipsis (titik 3)** + `DropdownMenu` dengan menu yang relevan dan permission-aware.
4. Tanggal **bukan** input native `type=date`; gunakan komposisi `Popover + Calendar` shadcn. Rentang tanggal: dua picker atau range Calendar sesuai kebutuhan.
5. Setiap input memiliki **label + placeholder** (atau placeholder ekuivalen untuk Select/Calendar), required bertanda `*` merah, helper/error jelas. Upload file punya thumbnail/preview yang aman dan progress.
6. **Sonner**: success hijau, warning oranye, error/destructive merah; tiap toast memakai ikon Lucide, tidak emoji.
7. Server memegang autentikasi, authorization, validasi bisnis, final score, audit, dan OpenAI secret; **jangan mempercayai pemeriksaan di React**.
8. Query list server-side, paginated + filter terindeks; cegah N+1 dengan eager loading terbatas dan pilih kolom relevan.
9. AI tidak boleh membuat kompetensi, evidence ID, user ID, atau angka proyek yang tidak ada; output JSON/schema divalidasi di backend.
10. Checklist AI tidak boleh dicentang tanpa implementasi **dan bukti pengujian**.

## Keputusan baseline sementara

- Batas PDF contoh: **50 MiB upload aplikasi** ([REKOMENDASI], perlu keputusan IT dan pertimbangkan batas request provider); jika input OpenAI punya batas lebih rendah, pecah/ekstrak di server.
- Penyimpanan file **private**, prefer object storage S3-compatible untuk production.
- Tahapan implementasi inti: `Core & Master → Proposal & Tim → 8 Step + Monitoring → Scoring + Approval → Hardening`.
- **5 role utama [BRD]:** Super Admin, Owner QCC, Mentor, Team Leader, SIMer/Member. Reviewer memakai penugasan/permission, bukan role keenam otomatis.

## Hasil yang diharapkan

Paket ini adalah **spesifikasi untuk coding**, bukan kode aplikasi siap jalan. AI coding wajib membaca `12_TASKLIST_AI.md` terlebih dahulu, lalu dokumen terkait tiap task; setiap perubahan harus menyelaraskan PRD, ERD, dan test bila berdampak kontrak.
