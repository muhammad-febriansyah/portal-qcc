# Product Requirements Document (PRD)

**Produk:** Portal QCC SIMGROUP Batch 4  
**Rilis:** MVP end-to-end proses bisnis 1–9  
**Status:** Draft untuk approval Owner QCC/IT  
**Sumber dasar:** BRD v1.0 (12 Oktober 2026), Bab 4–15, Lampiran A–E.

## 1. Visi & batas proses

Portal berbasis peran mengelola QCC dari profil, kompetensi, proposal, pemilihan tim berbantuan OpenAI, approval, 8 Step QCC, monitoring, evidence, AI scoring sampai human final approval. **Proses bisnis nomor 10 (dashboard dan laporan penuh) di luar scope delivery**, kecuali dashboard sederhana per peran dan daftar status yang dibutuhkan proses 1–9.

### 1.1 End-to-end process [BRD]

1. Login & lengkapi profil/organisasi/ketersediaan/kompetensi.
2. Draft proposal + deklarasikan kompetensi yang dibutuhkan.
3. AI memberi rekomendasi calon anggota dan alasan.
4. Laravel memvalidasi dan penanggung jawab mengonfirmasi susunan tim.
5. Proposal disubmit, direview, direvisi jika perlu, disetujui/ditolak.
6. Tim mengisi 8 Step QCC dan evidence/risiko/action.
7. Tim menjalankan bi-weekly report & mentoring (paralel dengan step QCC).
8. Sistem mengecek evidence, OpenAI scoring, Laravel kalkulasi skor, `PENDING_REVIEW`.
9. Reviewer berwenang merevisi/menyetujui penilaian; audit log final.

## 2. Personas / role [BRD]

`SUPER_ADMIN`, `OWNER_QCC`, `MENTOR`, `TEAM_LEADER`, `MEMBER`.

Pihak yang menyetujui review dapat diberi permission/assignment khusus. Tidak membuat `REVIEWER` sebagai role keenam secara otomatis. Hasil scoring hanya untuk Owner QCC/Super Admin secara default, kecuali grant eksplisit tercatat.

## 3. Persyaratan fitur per modul

### PRD-AUTH — Authentication & profile (P1)

- Login/logout, reset password, session management, CSRF; MFA privileged bila diwajibkan kebijakan IT.
- Profil: nama, jabatan, organisasi LOB/Divisi, lokasi kerja, kontak kerja/pribadi (finalisasi data yang boleh dikumpulkan), availability.
- Hanya pengguna sesuai cakupan yang dapat melihat/mengedit data; jangan membeberkan kontak pribadi dalam matching kepada role yang tidak berhak.
- UI: profil tab Informasi Dasar / Kompetensi / Ketersediaan.
- **Acceptance:** user tanpa organisasi/kompetensi diwajibkan melengkapi field minimum sebelum ikut matching; aksi terlarang mengembalikan 403.

### PRD-MASTER — Master data (P1)

- Kelola 14 LOB/Divisi dari Lampiran A, daftar 62 kompetensi `C001`–`C062`, kelompok A–F.
- Self-assessment user level `1=Awareness`, `2=Basic`, `3=Proficient`, `4=Expert`.
- `verified_level` disimpan terpisah dari `self_assessed_level` dan hanya dapat diubah petugas berwenang ([KONFIRMASI] siapa verifier).
- Bulk import CSV master kompetensi untuk memasukkan 62 entri resmi; jangan menciptakan 62 nama kompetensi tanpa file master dari stakeholder.
- **Acceptance:** self-assessment tidak pernah mengganti verified level.

### PRD-PROPOSAL — Proposal QCC (P1)

- Bisa dibuat SIMer/role yang ditugaskan. Field [BRD]: judul, tema, latar belakang/masalah, baseline, target, root cause analysis (RCA), rencana perbaikan, manfaat, PIC, lampiran.
- Tambahkan kebutuhan matching [BRD]: improvement, situasi saat ini, sasaran, data pelengkap, ruang lingkup, 3 hal yang ingin dipelajari, kompetensi dibutuhkan.
- UX wizard **usulan**: Informasi Dasar → Masalah & Baseline → Target & Manfaat → Kebutuhan Kompetensi → Lampiran & Review.
- Auto-save draft terdebounce, manual Save Draft, review data sebelum Submit.
- Status `DRAFT → SUBMITTED → UNDER_REVIEW → APPROVED/REJECTED/REVISION_REQUIRED → RESUBMITTED`. State resmi perlu disepakati; status `RESUBMITTED` boleh direpresentasikan sebagai event lalu kembali `SUBMITTED`.
- Proposal final hanya boleh submit setelah komposisi tim valid (alur 1–5). Penempatan proposal scoring sebelum atau setelah approval: [KONFIRMASI].
- **Acceptance:** submit invalid tidak mengubah status; setiap revisi menyimpan catatan dan versi/history.

### PRD-MATCH — AI Team Matching (P1)

- Input profil anggota, kompetensi self/verified, organisasi, ketersediaan, pengalaman proyek jika ada, kebutuhan proyek.
- Laravel melakukan kandidat filtering/authorization dahulu, mengirim data minimum ke OpenAI melalui job.
- Output JSON rekomendasi candidate ID, peran, alasan, kontribusi, gap, mitigasi/coaching, ketidakpastian.
- User berwenang boleh override pilihan dengan alasan; harus tercatat.
- Aturan backend: **maksimum 5** termasuk leader; **tepat 1** leader; **minimal 2** organisasi LOB/Divisi; anggota tidak duplikat; kandidat harus eligible.
- Menolak konfirmasi/aktivasi tim yang tidak valid; bila perlu menyimpan draft kandidat, simpan di `team_matching_runs` atau area draft yang **tidak dianggap tim aktif**.
- **Acceptance:** AI menyarankan 6 user atau leader ganda tidak bisa dibenarkan dengan UI override.

### PRD-QCC — 8 Step QCC (P1)

1. Menentukan Tema Improvement.
2. Menetapkan Target.
3. Menemukan Sebab-Sebab yang Mungkin.
4. Menentukan Sebab-Sebab yang Dominan.
5. Membuat Rencana Perbaikan.
6. Implementasi Rencana Perbaikan.
7. Evaluasi Hasil Perbaikan.
8. Standarisasi & Rencana Pencegahan.

- Tiap step punya deskripsi, plan/actual, progress/status, target tanggal, evidence, issue/risk/action terkait, catatan review & revisi.
- Status umum usulan BRD: `NOT_STARTED → IN_PROGRESS → SUBMITTED → UNDER_REVIEW → APPROVED`; revisi `REVISION_REQUIRED` lalu submit ulang.
- Hak edit step yang sudah approved harus dikunci/versioned atau melalui amendment berizin.
- **Acceptance:** hanya reviewer berwenang dapat approve; 8 progress record terinisialisasi tepat sekali untuk proyek approved.

### PRD-MONITOR — Bi-weekly, mentoring, action, risk (P1)

- **Bi-weekly:** periode, tanggal rapat, peserta, agenda, ringkasan, plan vs actual, kendala, keputusan, corrective action, PIC, due date, evidence, status review.
- **Mentoring:** jadwal/sesi, mentor ter-assign, tanggal, topik, temuan, saran, action item, PIC, due date, follow-up, evidence, approval sesuai penugasan.
- **Action:** status `OPEN → IN_PROGRESS → [BLOCKED] → COMPLETED/CANCELLED`; owner/PIC/due date dan histori.
- **Risk & issue:** kategori, dampak, likelihood, prioritas, mitigasi, PIC, deadline, perubahan.
- **Acceptance:** laporan dapat disubmit, direvisi, dikaitkan dengan file, dan ditindaklanjuti; aktivitas lintas proyek tidak bocor.

### PRD-EVIDENCE — File & evidence (P1)

- PDF, foto, dokumen, MoM, tautan materi, tautan percakapan AI + ringkasan pengguna; hubungkan ke proyek/step/report/mentor.
- Upload dengan status `UPLOADING → SCANNING → READY/REJECTED`, preview thumbnail dan metadata, versi, download berotorisasi.
- PDF minimal 10 MB sudah lazim menurut kebutuhan lapangan; batasi ukuran maksimal melalui konfigurasi dan benchmark, **rekomendasi awal 50 MiB**, belum ketetapan BRD.
- Link percakapan AI hanya referensi, tidak otomatis dibaca URL-nya.
- **Acceptance:** file privat; failed scan tidak bisa dipakai scoring; metadata/dokumen tidak bocor lewat URL langsung.

### PRD-SCORING — OpenAI scoring (P1)

- Ada 3 jenis: **Proposal**, **Final Presentation**, **Convention** (semua termuat di BRD; jadwal tahap masing-masing harus dikonfirmasi).
- Rubrik baseline (belum disahkan):
  - Proposal: Goals 30%; Problem/Challenge Identification 35%; Corrective Action 35%.
  - Final Presentation: 8 Step 25%; Business Value 25%; Analytical Depth 20%; Sustainability 12%; Cross-Functional Collaboration 10%; Presentation Visual Quality 8%.
  - Convention: Project Impact 40%; Process Improvement & Standardization 30%; Authenticity 30%.
- Semua bobot total 100, rubrik published immutable/versioned.
- Evidence gate: cek status file, ID proyek, minimum bukti per rubrik; jika kurang `INSUFFICIENT_EVIDENCE` beserta daftar gap.
- Output AI structured JSON: skor per kriteria 0–100, alasan, evidence IDs, gap/uncertainty/recommendations. **Laravel** validasi schema/evidence IDs dan menghitung weighted score.
- Simpan per-run model, prompt version, rubrik version snapshot, input manifest, status, retry.
- Simpan **skor AI**, **skor reviewer**, **skor final** terpisah. Final approval hanya manusia berizin.
- **Acceptance:** tanpa bukti tidak otomatis skor 0; AI output invalid tidak boleh dipublikasi.

### PRD-REVIEW — Approval & audit (P1)

- Proposal, step, bi-weekly, mentoring, scoring memiliki review history, komentar/keputusan, aktor dan timestamp.
- Final scoring hanya Owner QCC/izin eksplisit sesuai assignment.
- Perubahan reviewer score harus menyertakan alasan; audit append-only.
- **Acceptance:** approval ganda/berulang ditangani transaction/version check; user tak berizin tidak bisa call endpoint approve meski URL diketahui.

### PRD-DASH — Operasional dasar (P2, pendukung)

- Dashboard sesuai role berisi tugas tertunda, proposal status, 8 Step progress, mentoring terjadwal dan notifikasi.
- Monitoring list minimal untuk Owner QCC; dashboard eksekutif agregat dan export lengkap dikecualikan sampai proses 10.

## 4. List page & komponen

| Page | Entry | List/Base Data Table | Form/detail |
| --- | --- | --- | --- |
| Dashboard | Semua | Daftar tugas/proyek mini | Card, Progress, Badge, Tabs |
| Profil & Kompetensi | Semua (own) | Kompetensi Saya | Tabs, Field, Select/Combobox, Radio Group |
| Anggota | Super Admin | User & role | Dialog/Sheet, Form, Data Table |
| Master Organisasi/Kompetensi | Super Admin | Master | Data Table, Import CSV, Dialog |
| Proposal QCC | Member/Leader/Owner | Proposal | Wizard, Textarea, Date Picker, Attachment |
| Manajemen Tim | Leader/Owner | Candidate result | Candidate cards, selected team, Confirm Dialog |
| Proyek QCC | Assignment | Project list | 8-step vertical navigation, tabs, evidence |
| Bi-weekly | Leader/Mentor/Owner | Period/report list | Date Picker, members, action, file |
| Mentoring | Mentor/Leader | Sessions | Date Picker, notes, tasks, evidence |
| Evidence Repository | Assignment | Evidence list | Upload dropzone, image/PDF preview |
| AI Scoring | Owner/Admin | Scoring runs | Rubric detail, citations/evidence, compare score |
| Approval Inbox | Assigned reviewer | Pending items | Tabs, review history, decision dialog |
| Batch/Rubrik/Permission/Audit | Admin | Lists | Dialog, version history, search/filter |

## 5. UX & validation universal

- Every internal page: Breadcrumb, Title, Description, content, clear primary action.
- Forms: label, placeholder, red required asterisk, helper, inline invalid state, client/server validation; bulk submit disabled when pending; preserve user input on error.
- List: shadcn **Base UI Data Table** based on TanStack Table, backend pagination/filter/sort, row action `Ellipsis` DropdownMenu; actions hidden/disabled based on permission but always verify server-side.
- Dates: shadcn Date Picker (Popover + Calendar), not native date inputs.
- File: drag/drop + Browse, type/size/date, preview (image/PDF), remove before submit, progress, cancel/retry, validation.
- Toasts: Sonner green success, orange warning, red danger/error with Lucide icons; no emoji.
- Empty, loading, permission denied, error, offline, retry and long-running job state.

## 6. Non-functional baseline

- Role + row/project scope authorization, secure cookies/session, CSRF, rate limiting, MFA privileged if required.
- No public evidence buckets; signed preview/download URLs short-lived; app proxy preferred for sensitive docs.
- Queue asynchronous for file processing, matching, AI scoring; idempotency/retry/backoff/dead-letter strategy.
- Targets BRD: 300 active concurrent, 450 VU peak; non-AI API P95 <2s, dashboard P95 <3s, monthly availability initial target 99.5%; **semua perlu load testing**.
- Database query budget, index review, slow query metrics, N+1 detection, cache invalidation.
- Dev/staging/prod separated; automated backup and restore drill; immutable audit / event history.

## 7. Dependency / keputusan terbuka

Lihat `13_OPEN_DECISIONS.md`: master C001-C062, verifier, approval matrix/SLA, scoring timing/evidence minimum, privacy, provider/data residency, max upload, allowed export, user membership limits, notifications.
