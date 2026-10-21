# Form Catalog & Page Specifications — QCC SIMGROUP

**Status:** Usulan spesifikasi field untuk coding; field yang dicantumkan pada BRD ditandai `[BRD]`, field rincian UX ditandai `[UI]`, yang belum final `[KONFIRMASI]`.  
**Aturan universal:** Semua halaman internal **Breadcrumb → Title → Description**. Setiap form punya label, placeholder/hint, required `*` merah, inline error, cancel/reset/simpan, loading, permission check. Input tanggal **shadcn Popover + Calendar**, bukan native date. Lampiran gunakan file upload preview. List memakai shadcn **Base Data Table** dengan `Ellipsis` actions.

## 1. Pages and standard page headers

| Route module | Breadcrumb example | Title | Description |
| --- | --- | --- | --- |
| `/dashboard` | Beranda | Dashboard | Ringkasan pekerjaan dan progres QCC sesuai akses Anda. |
| `/profile` | Beranda / Profil | Profil Saya | Kelola identitas, organisasi dan ketersediaan. |
| `/competencies` | Beranda / Kompetensi Saya | Kompetensi Saya | Nilai kemampuan dan pantau verifikasi kompetensi. |
| `/proposals` | Beranda / Proposal QCC | Proposal QCC | Kelola pengajuan, revisi dan status persetujuan proposal. |
| `/teams/{project}` | Beranda / Proposal / Tim | Pembentukan Tim | Pilih anggota lintas LOB/Divisi dengan bantuan AI. |
| `/projects/{project}/steps/{step}` | Beranda / Proyek / 8 Step | Progress 8 Step QCC | Catat aktivitas dan evidence tiap tahap improvement. |
| `/projects/{project}/biweekly` | Beranda / Proyek / Bi-weekly | Bi-weekly Report | Dokumentasikan pemantauan berkala proyek QCC. |
| `/projects/{project}/mentoring` | Beranda / Proyek / Mentoring | Mentoring | Catat sesi, rekomendasi dan tindak lanjut mentor. |
| `/projects/{project}/evidence` | Beranda / Proyek / Evidence | Evidence Repository | Kelola dokumen pendukung dan status pemrosesan. |
| `/projects/{project}/scoring` | Beranda / Proyek / AI Scoring | AI Scoring | Lihat penilaian berbasis evidence yang memerlukan review manusia. |
| `/approval` | Beranda / Approval | Review & Approval | Tinjau pengajuan sesuai penugasan dan wewenang Anda. |
| `/admin/competencies` | Beranda / Pengaturan / Kompetensi | Master Kompetensi | Kelola 62 kompetensi resmi QCC. |

## 2. Auth & profil

### FORM-01 — Login

| Field | UI/validation | Placeholder |
| --- | --- | --- |
| Email* | `Input` email, required | `nama@perusahaan.co.id` |
| Password* | `Input` password + toggle Eye/EyeOff | `Masukkan kata sandi` |
| Remember me | `Checkbox` | n/a |

Actions: Masuk, Lupa Password. Login tidak perlu app breadcrumb; tetap H1 + desc.

### FORM-02 — Profil Saya

| Field | UI/validation | Placeholder |
| --- | --- | --- |
| Nama lengkap* [BRD] | Input | `Masukkan nama lengkap` |
| Jabatan* [BRD] | Input / Select corporate data | `Contoh: Staff Operasional` |
| LOB/Divisi* [BRD] | shadcn Select / Combobox dari 14 org | `Pilih LOB/Divisi` |
| Lokasi kerja* [BRD] | Input | `Contoh: Jakarta` |
| Email kerja* [BRD] | Input email | `nama@perusahaan.co.id` |
| Nomor kontak [BRD] | Input tel | `Masukkan nomor kontak kerja` |
| Kontak pribadi [BRD] | Input, optional sesuai privacy decision | `Masukkan nomor pribadi` |
| Availability* [BRD] | Select availability enum | `Pilih ketersediaan` |
| Foto profil [UI] | Image uploader + circle/avatar preview | `Pilih foto profil` |

Actions: Simpan Perubahan, Batalkan. Tab Profile, Kompetensi, Ketersediaan.

### FORM-03 — Self-Assessment Kompetensi

| Field | UI/validation | Placeholder |
| --- | --- | --- |
| Kelompok* [BRD] | Select enam kelompok A–F | `Pilih kelompok` |
| Kompetensi* [BRD] | Searchable Combobox master 62 | `Cari kompetensi...` |
| Self-level* [BRD] | RadioGroup 1/2/3/4 | `Pilih level kemampuan` |
| Keterangan [UI] | Textarea | `Jelaskan pengalaman jika diperlukan` |
| Verified level [BRD] | Readonly Badge/field, tidak user-editable | `Belum terverifikasi` |

No upload competency evidence required by BRD; bila stakeholder ingin sertifikasi/bukti verifikasi, buat change request/field tambahan, bukan wajib otomatis.

### FORM-04 — Verifikasi Kompetensi

Select user/skill, level 1–4, status verifikasi, catatan, date verified **shadcn date picker**, decision/reviewer ID server generated. `[KONFIRMASI]` verifier role, bukti, level approval.

## 3. Proposal: 5-step wizard

### FORM-05 — Step 1 Informasi Dasar

- `Judul Proposal*` — Input placeholder `Contoh: Digitalisasi Laporan Produksi`.
- `Tema Improvement*` — Select/Input `Pilih tema improvement`.
- `Latar Belakang*` — Textarea `Jelaskan kondisi saat ini`.
- `Permasalahan*` — Textarea `Masalah apa yang ingin diselesaikan?`.
- `Situasi Saat Ini*` — Textarea `Tuliskan proses dan kendala saat ini`.
- `Batch QCC*` — Select active batch `Pilih periode batch`.

### FORM-06 — Step 2 Baseline & RCA

- `Baseline*` — Textarea/metric set `Kondisi awal sebelum improvement`.
- `Root Cause Analysis*` — Textarea `Jelaskan akar penyebab utama` (detail terkait step QCC boleh disempurnakan kemudian).
- `Data Pendukung` — Repeater refs/evidence uploader `Tambahkan bukti baseline`.

### FORM-07 — Step 3 Target & Manfaat

- `Target Perbaikan*` — Textarea `Hasil terukur yang ingin dicapai`.
- `Tujuan/Sasaran Proyek*` — Textarea `Jelaskan tujuan proyek`.
- `Manfaat*` — Textarea `Jelaskan dampak yang diharapkan`.
- `PIC*` — Searchable Combobox user eligible `Pilih penanggung jawab`.
- `Rencana Perbaikan*` — Textarea `Jelaskan rencana awal improvement`.
- `Tenggat Proposal/Target` — shadcn Date Picker `Pilih tanggal` (kalau disepakati owner).

### FORM-08 — Step 4 Kebutuhan Kompetensi

- `Kompetensi Dibutuhkan*` — Multi Combobox from official 62 master `Cari kompetensi`.
- `Minimum Level` — Select optional 1–4 `Pilih level minimum`.
- `Ruang Lingkup*` — Textarea `Jelaskan cakupan proyek`.
- `Tiga Hal yang Dipelajari*` — 3 Textarea/Input, `Tuliskan hal 1/2/3`.
- `Informasi Tambahan` — Textarea `Data atau kendala tambahan`.

### FORM-09 — Step 5 Lampiran & Review

- `Lampiran Proposal` — multi-file uploader (PDF, gambar, dokumen per izin), preview, progress, remove, error, scan status.
- `Catatan Submit` — optional textarea `Tambahkan catatan untuk reviewer`.
- `Review Ringkasan` — read-only summary sebelum Submit.

Actions: Simpan Draft, Kembali, Berikutnya, Submit untuk Review (server must check valid team), confirmation AlertDialog if irreversible submit.

## 4. AI Team Matching / anggota

### FORM-10 — AI Matching input & hasil

Input tidak perlu user mengetik ulang profil; read-only project requirements and selected candidate filters. Actions `Jalankan AI Matching`, `Refresh Rekomendasi` (rate-limited), `Pilih Kandidat`. Output cards menampilkan nama, organisasi, self/verified competence yang relevan, ketersediaan, alasan matching, gap, uncertainty. **Tidak menampilkan data kontak pribadi**.

### FORM-11 — Susunan Tim / konfirmasi

- `Anggota Tim*` — user Combobox multi-select max 5, candidate table.
- `Leader*` — pilih tepat 1 user dalam anggota.
- `Role masing-masing` — Select LEADER/MEMBER.
- `Catatan Perubahan Pilihan AI` — Textarea wajib jika override.
- Read-only `Jumlah Anggota`, `Jumlah LOB/Divisi` live client feedback.

On confirm Laravel checks max5, one leader, 2+ LOB and no duplicate. Save invalid team only as transient matching draft, **tidak terbitkan active project_members**.

### FORM-12 — Review Proposal

Read-only proposal+team+files. `Keputusan*` (approve, revision required, reject), `Komentar Review*` untuk revision/reject, `Tanggal Keputusan` generated by server. Actions Minta Revisi, Tolak, Setujui. Confirmation Dialog untuk approve/reject.

## 5. 8 Step QCC — shared step form

### FORM-13 — Step QCC 1–8

Nomor/nama delapan step fix sesuai Lampiran B BRD, bukan user-editable:

1. Menentukan Tema Improvement
2. Menetapkan Target
3. Menemukan Sebab-Sebab yang Mungkin
4. Menentukan Sebab-Sebab yang Dominan
5. Membuat Rencana Perbaikan
6. Implementasi Rencana Perbaikan
7. Evaluasi Hasil Perbaikan
8. Standarisasi & Rencana Pencegahan

| Field | UI | Placeholder |
| --- | --- | --- |
| Deskripsi aktivitas* [BRD] | Textarea | `Jelaskan aktivitas pada step ini` |
| Rencana/plan* [BRD] | Textarea | `Apa yang direncanakan?` |
| Aktual/hasil [BRD] | Textarea | `Tuliskan realisasi dan hasil` |
| Target/tenggat [BRD] | shadcn Date Picker | `Pilih tanggal target` |
| Progress (%) [UI] | Number/Slider and Progress display | `0–100` |
| Status* [BRD] | shadcn Select | `Pilih status` |
| Evidence [BRD] | Modern multi file/link, preview | `Pilih atau tarik evidence` |
| Risiko/isu terkait [UI] | Combobox | `Pilih risiko atau isu` |
| Catatan pengaju [UI] | Textarea | `Tambahkan catatan untuk reviewer` |

Actions: Simpan Draft, Submit Step, Riwayat Revisi, Request Review. Review action hanya role granted dan logged.

## 6. Monitoring & Evidence

### FORM-14 — Bi-weekly report

Periode*, tanggal rapat* (Date Picker), peserta* (multi Combobox dari anggota), agenda*, ringkasan*, rencana vs aktual*, kendala, keputusan*, corrective action + PIC + due date (Date Picker), evidence + preview, catatan reviewer, status. Actions Simpan Draft, Ajukan Review. Review period uniqueness jika disahkan.

### FORM-15 — Mentoring session/report

Proyek*, mentor* (pilih assigned only), sesi/jadwal* (Date Picker + Time Select sesuai desain), topik*, temuan*, feedback/rekomendasi*, action items (repeater title/PIC/due date/status), evidence, tindak lanjut, status review. Actions Simpan, Kirim, Minta Revisi, Approve jika permission.

### FORM-16 — Risk & Issue

Jenis RISK/ISSUE*, judul*, deskripsi*, dampak*, likelihood* (kategori sesuai owner), prioritas*, mitigasi, PIC*, due date (Date Picker), status*, riwayat.

### FORM-17 — Action Item

Judul tindakan*, deskripsi, sumber* (QCC/bi-weekly/mentoring/risk), PIC*, due date* (Date Picker), status*, tanggal selesai (read-only/generated), evidence/link (optional). Lifecycle OPEN/IN_PROGRESS/BLOCKED/COMPLETED/CANCELLED.

### FORM-18 — Evidence Repository

| Field | UI | Placeholder |
| --- | --- | --- |
| Judul evidence* | Input | `Contoh: Hasil Evaluasi Step 7` |
| Kategori* | Select | `Pilih jenis evidence` |
| Deskripsi* / ringkasan | Textarea | `Jelaskan isi evidence` |
| Tipe sumber* | Radio FILE/URL | `Pilih tipe evidence` |
| File* (bila FILE) | Dropzone + PDF/image preview | `Tarik file atau pilih dari perangkat` |
| URL* (bila URL) | Input URL | `https://...` |
| Terkait dengan* | Select target project/step/report | `Pilih keterkaitan` |
| Tanggal evidence [UI] | shadcn Date Picker | `Pilih tanggal` |
| Versi [UI] | Server generated + history UI | n/a |

URL percakapan AI wajib ringkasan/relevan evidence lain; aplikasi tidak otomatis membaca isi percakapan hanya karena user menaruh URL.

## 7. Scoring & approval

### FORM-19 — Jalankan AI Scoring

`Proyek*` Combo scoped, `Jenis Rubrik*` Select Proposal/Final Presentation/Convention, `Rubrik Versi*` published readonly/select, `Evidence terkait*` multi-select READY, `Catatan` optional. Actions Periksa Kelengkapan, Jalankan AI Scoring. Status loading/queued/failed/gaps. **Penilaian AI tidak meng-upload file baru di sini; pilih evidence repository**.

### FORM-20 — Review Hasil Scoring

Read-only: project, type/version, scores AI, criteria weights, rationale, citations, missing evidence, uncertainty. Editable **jika authorized**: `Skor reviewer per kriteria` (0–100), `Alasan perubahan* jika override`, `Komentar reviewer`, `Keputusan*` (REVISION_REQUIRED/APPROVE). Weighted total recalculated Laravel; read-only `Final Score` sampai approval. Actions Minta Revisi, Setujui Final (confirm). Hasil default Owner/Super Admin only.

## 8. Administration

### FORM-21 — User & roles

Name*, company email*, organization*, role*, active status*, password reset workflow, optional MFA controls. Audit changes and prevent self-removing last admin.

### FORM-22 — Master Organization

Code*, name*, active*. Seed 14 official entries. Soft deactivate preferred over deleting referenced org.

### FORM-23 — Master Competency

Official code* C001–C062, group A–F*, name*, definition*, active*. Import CSV with preview & validation, no fake placeholder name as real master.

### FORM-24 — Batch QCC

Batch code*, name*, start date* (Date Picker), end date* (Date Picker), deadline settings, status*. Validation end>=start.

### FORM-25 — Rubric version

Scoring type*, version, criterion name/code*, weight_pct* (numeric 0–100), criterion description/rules, draft/publish, approval. Total weight 100 before publish; published immutable. Use Data Table inline rubric rows + Ellipsis edit/delete only draft.

### FORM-26 — Mentor / reviewer assignment

Project*, user/mentor/reviewer*, scope*, effective start/end date (Date Picker), permission/expiry, reason, status. No blanket scoring permission when assigning Mentor.

## 9. Data Table inventory and row actions

| Table page | Columns minimum | Ellipsis dropdown (conditional) |
| --- | --- | --- |
| Proposal | Code, Title, Batch, Pengaju, Status, Last Update | Detail, Edit Draft, Review, Audit, Archive |
| Project | Title, Team, Current Step, Progress, Status | Detail, Lihat 8 Step, Lihat Evidence |
| Team | Name, LOB/Divisi, Role, Competency summary | Lihat Profil, Jadikan Leader, Keluarkan (authorized) |
| Bi-weekly | Period, Meeting Date, Submitted By, Status | Detail, Edit Draft, Review, Download Evidence |
| Mentoring | Date, Mentor, Topic, Actions, Status | Detail, Feedback, Review, Evidence |
| Evidence | Title, Type, Size, Linked Step, Scan Status, Uploaded | Preview, Download, Ganti Versi, Hapus jika allowed |
| AI Scoring | Type, Rubric Version, Run Status, Requested, Reviewed | Detail, Retry (authorized), Review Pending |
| Audit | Actor, Action, Subject, Timestamp | Detail (read only) |
| Users & Masters | Identifier, Display Name, Status, Updated | View, Edit, Activate/Deactivate, Delete if safe |

**Data Table official reference:** https://ui.shadcn.com/docs/components/base/data-table. Server pagination/filter required, never render all projects in browser.

## 10. Shared state and actions

- Each form has `Saving...` disabled state and clear success/warning/error Sonner toast.
- Date/time represent consistent ISO in Laravel; frontend date selects use shadcn Calendar/Popover.
- File is validated in client for user feedback and again on Laravel server.
- `AlertDialog` for destructive/approve actions, `Sheet` for contextual editing, `Dialog` for preview PDF, `Skeleton` for async content.
- Status badge with text + Lucide icon (no emoji).
- Back link should return list with preserved filters/pagination where possible.
