# User Stories dan Acceptance Criteria

Format: `US-ID` — **Sebagai [role], saya ingin [aksi], agar [manfaat]**.  
Priority `P0` = wajib aman/fungsional, `P1` = MVP proses 1–9, `P2` = penyempurnaan.  
**Catatan:** Role yang diberi `review` harus memiliki project assignment/explicit permission, tidak cukup nama role.

## A. Foundation & profil

### US-001 — Authentication (P0)
**Sebagai** pengguna, **saya ingin** login/logout/reset akses **agar** hanya saya yang dapat menggunakan akun saya.
- AC: kredensial salah tidak mengungkap user; session aman; logout invalidates session; unauthorized 403.
- AC: UI login memuat judul/deskripsi; error form eksplisit; ikon Lucide.

### US-002 — Profil (P1)
**Sebagai** SIMer, **saya ingin** mengisi biodata, LOB/Divisi, lokasi kerja, kontak dan ketersediaan **agar** profil siap ikut QCC.
- AC: pilihan organisasi hanya dari 14 master aktif; field wajib diproteksi di backend; informasi pribadi dibatasi.

### US-003 — Self-assessment (P1)
**Sebagai** SIMer, **saya ingin** memilih kompetensi dan level 1–4 **agar** kontribusi saya dapat dipertimbangkan saat matching.
- AC: hanya kode master resmi; kombinasi user+kompetensi unik; level self dan verified terpisah.

### US-004 — Verified competencies (P1)
**Sebagai** petugas verifikasi terotorisasi, **saya ingin** memvalidasi level kemampuan **agar** AI tidak hanya mengandalkan klaim diri.
- AC: self level tidak ditimpa; verifier dan waktu dicatat; permission khusus; identity verifier [KONFIRMASI].

## B. Proposal & tim

### US-005 — Draft proposal (P1)
**Sebagai** SIMer/Leader, **saya ingin** menyimpan proposal sebagai draft **agar** pengisian panjang tidak hilang.
- AC: draft berisi field wajib bila disubmit; resume/reload mempertahankan data; lampiran private.

### US-006 — Kebutuhan tim (P1)
**Sebagai** pengaju, **saya ingin** memilih kompetensi proyek dan menulis konteks masalah **agar** pencarian kandidat lebih relevan.
- AC: kebutuhan kompetensi valid dari master; terkait tepat satu proyek; editing dibatasi status.

### US-007 — AI matching (P1)
**Sebagai** Leader/pihak berwenang, **saya ingin** rekomendasi anggota AI beserta alasannya **agar** pemilihan tim transparan.
- AC: AI hanya mendapat kandidat eligible; respons structured JSON; kandidat ID tidak valid ditolak; hasil disimpan versioned.

### US-008 — Konfirmasi tim (P0)
**Sebagai** pihak berwenang, **saya ingin** menyusun tim dengan tepat satu leader, maksimal lima orang, minimal dua organisasi berbeda **agar** sesuai aturan QCC.
- AC: Laravel memblok 0/2 leader, >5 anggota, <2 organisasi, duplikat; concurrent edits tidak meloloskan aturan.

### US-009 — Override AI (P1)
**Sebagai** pihak yang menyusun tim, **saya ingin** mengubah rekomendasi AI dengan alasan **agar** pilihan tetap mempertimbangkan kondisi lapangan.
- AC: perubahan terdokumentasi dengan actor/time/reason; aturan tim tetap berlaku.

### US-010 — Submit proposal (P1)
**Sebagai** pengaju, **saya ingin** submit proposal dan susunan tim valid **agar** dapat direview.
- AC: validasi backend berjalan dalam transaksi; jika tim invalid status tidak berubah; pemberitahuan reviewer dikirim.

### US-011 — Review proposal (P1)
**Sebagai** Owner QCC/reviewer ditugaskan, **saya ingin** approve, reject, atau meminta revisi **agar** proposal yang berjalan layak.
- AC: history review, alasan revisi/reject tercatat; hanya actor berizin; resubmit membentuk event baru.

## C. 8 Step QCC, report dan evidence

### US-012 — Inisialisasi 8 step (P1)
**Sebagai** Team Leader, **saya ingin** delapan tahap tersedia setelah proposal disetujui **agar** tim memiliki jalur kerja.
- AC: 8 record step unik per project; urutan/nama sesuai Lampiran B; tidak terduplikasi saat retry.

### US-013 — Isi step QCC (P1)
**Sebagai** anggota tim berizin, **saya ingin** mengisi aktivitas, target, aktual, bukti dan progres setiap step **agar** progress tercatat.
- AC: step terkait project yang diikuti, validasi field, history perubahan, due date shadcn Calendar.

### US-014 — Review step (P1)
**Sebagai** reviewer berwenang, **saya ingin** mereview/meminta revisi/approve step **agar** hasil tidak sekadar klaim anggota.
- AC: review status terpisah dari progress; perubahan sesudah approval dibatasi/versioned.

### US-015 — Bi-weekly report (P1)
**Sebagai** Leader, **saya ingin** membuat laporan per periode, rapat, kendala dan action **agar** Owner memonitor kegiatan rutin.
- AC: periode dan tanggal, peserta, agenda, plan vs actual, decision, PIC & due date tersimpan; lampiran dan review history.

### US-016 — Mentoring (P1)
**Sebagai** Mentor, **saya ingin** melihat proyek binaan, memberi saran dan action item **agar** tim dapat memperbaiki kinerja.
- AC: hanya proyek ditugaskan; action memiliki PIC/status/due date; bisa review/approve sesuai permission.

### US-017 — Feedback & follow-up (P1)
**Sebagai** Team Leader, **saya ingin** melihat dan menindaklanjuti catatan mentor **agar** rekomendasi tidak terlewat.
- AC: update status tidak menghapus catatan mentor; history dan notification.

### US-018 — Evidence upload (P0)
**Sebagai** anggota berizin, **saya ingin** upload PDF/foto/dokumen dan preview sebelum simpan **agar** evidence terdokumentasi.
- AC: validasi MIME/size/signature, progress, preview dan cancel; penyimpanan private; status scanning sebelum READY.

### US-019 — Evidence linking (P1)
**Sebagai** Leader, **saya ingin** menautkan satu evidence ke step/bi-weekly/mentoring **agar** bukti tidak diduplikasi.
- AC: setiap relasi target harus dalam proyek yang sama; evidence version dan actor tersedia; unlinked/deleted evidence dihindari dalam scoring.

### US-020 — Risk & corrective actions (P1)
**Sebagai** Leader, **saya ingin** mencatat risiko, mitigasi, PIC, dan tindak lanjut **agar** pekerjaan tidak terlupakan.
- AC: setiap action punya status/transisi yang valid, deadline dan audit changes.

## D. AI scoring & human approval

### US-021 — Rubric management (P0)
**Sebagai** Super Admin/Owner sesuai hak, **saya ingin** membuat versi rubrik **agar** nilai konsisten dan historis.
- AC: jumlah bobot tepat 100; published immutable; scoring mencatat snapshot rubric_version_id.

### US-022 — Evidence completeness check (P0)
**Sebagai** Owner QCC, **saya ingin** melihat bukti kurang sebelum AI scoring **agar** AI tidak memberi skor atas data kosong.
- AC: jika bukti kurang -> `INSUFFICIENT_EVIDENCE`, daftar gap tampil; tidak menulis skor 0 otomatis.

### US-023 — Async AI scoring (P1)
**Sebagai** Owner QCC, **saya ingin** memulai scoring async dan melihat status proses **agar** aplikasi tidak hang.
- AC: idempotency untuk request ulang, retry terkontrol, job status dan error dipantau, evidence IDs tervalidasi.

### US-024 — Detailed AI result (P1)
**Sebagai** Owner QCC, **saya ingin** skor per kriteria, bobot, alasan dan evidence references **agar** penilaian dapat diaudit.
- AC: sumber bukti click-through hanya jika berhak; weighted score dihitung Laravel; output JSON valid.

### US-025 — Human scoring approval (P0)
**Sebagai** reviewer berwenang, **saya ingin** menerima/mengubah/menolak hasil dan menulis alasan **agar** skor final diputuskan manusia.
- AC: `ai_score`, `reviewer_score`, `final_score` terpisah; alasan revisi wajib; siapa/kapan approval tidak dapat dipalsukan.

### US-026 — Scoring privacy (P0)
**Sebagai** Owner QCC, **saya ingin** skor hanya terlihat role yang berhak **agar** hasil tidak bocor ke anggota.
- AC: member/leader/mentor tanpa explicit grant gagal mengakses halaman, endpoint API, file, dan export skor (403/404 sesuai kebijakan).

## E. Administration, operasional & desain

### US-027 — Role management (P0)
**Sebagai** Super Admin, **saya ingin** mengelola role/permission **agar** pengguna hanya dapat aksi yang ditugaskan.
- AC: perubahan diaudit dan permission cache disinkronkan; tidak ada akses lewat client-only guard.

### US-028 — Batch, organization, competency (P1)
**Sebagai** Super Admin, **saya ingin** menyiapkan batch, organisasi, master kompetensi **agar** pendaftaran dan proyek konsisten.
- AC: 14 organisasi terseed; kompetensi hanya master resmi; data historis tidak hilang saat master dinonaktifkan.

### US-029 — Audit log (P0)
**Sebagai** Super Admin/Owner berizin, **saya ingin** riwayat aktor, aksi, waktu dan perubahan **agar** investigasi dapat dilakukan.
- AC: mencatat role, tim, proposal, evidence, rubrik, review/scoring; log tidak bisa diubah user biasa.

### US-030 — Notifications (P1)
**Sebagai** pengguna yang ditugaskan, **saya ingin** pemberitahuan submit, revisi, deadline dan mentoring **agar** tahu tindakan berikutnya.
- AC: notifikasi hanya ke penerima berhak; status sudah dibaca; tidak menyisipkan konten skor rahasia ke user tidak berizin.

### US-031 — Konsistensi UI (P0)
**Sebagai** pengguna, **saya ingin** Breadcrumb/Title/Description pada setiap halaman, Data Table dan form konsisten **agar** aplikasi mudah dipahami.
- AC: Poppins, warna light `#2547F9 / #F7F8FD / #FFFFFF`; required `*` merah; Date Picker shadcn; Ellipsis actions; Sonner variants; no emoji.

### US-032 — Performa list (P0)
**Sebagai** pengguna, **saya ingin** pencarian/filter/pagination cepat **agar** list besar tetap responsif.
- AC: server pagination; role scoping sebelum paginate; tidak terjadi N+1 pada list; indeks dan explain plan diperiksa.

## Traceability

- US-001–004: BRD FR-01..04.
- US-005–011: BRD FR-05..07, BR-01..05.
- US-012–020: BRD FR-08..12, Lampiran B/D.
- US-021–026: BRD FR-13..14, BR-07..15, Bab 10.
- US-027–032: BRD FR-15..20, Bab 12, serta kebutuhan UI/performa user.

**Implementer:** petakan US ke `12_TASKLIST_AI.md` dan pengujian `11_QA_ACCEPTANCE.md` sebelum memberi status Done.
