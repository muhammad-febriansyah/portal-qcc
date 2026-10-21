# Project Goals — Portal QCC SIMGROUP Batch 4

## 1. Ringkasan tujuan

Mengelola siklus kegiatan Quality Control Circle (QCC) terpusat, dapat diaudit dan aman, dari anggota mengisi kompetensi, membuat proposal, memilih tim lintas LOB/Divisi dengan rekomendasi AI, menjalankan **8 Step QCC**, mencatat bi-weekly/mentoring/evidence, sampai **AI scoring yang disetujui manusia**.

**Batas selesai proyek:** proses bisnis nomor **9** pada Bab 6 BRD; tidak memasukkan proses nomor 10 sebagai modul reporting/export lanjutan. Namun dashboard operasional role-based minimum tetap masuk agar proses 1–9 bisa dipakai.

## 2. Problem statement [BRD]

1. Profil, kompetensi, proposal dan tim tersebar di beberapa tempat.
2. Validasi tim lintas LOB/Divisi dan leader rawan tidak konsisten.
3. Evidence, mentoring, action item, dan progress tidak mudah dilacak.
4. Skor antar proyek sulit konsisten tanpa rubrik, bukti dan jejak keputusan.
5. Hasil penilaian sensitif harus dibatasi berdasarkan peran dan proyek.

## 3. Sasaran terukur

| ID | Sasaran | Indikator penerimaan | Sumber |
| --- | --- | --- | --- |
| G-01 | Single source of truth | Profil, proyek, tim, evidence, mentoring, scoring terhubung ID stabil | BRD |
| G-02 | Validitas tim | Maksimal 5 orang termasuk leader; tepat 1 leader; minimal 2 LOB/Divisi; tidak duplikat | BRD |
| G-03 | Proses 8 Step terlacak | Status, tanggal, target, progress, evidence & review historis per step | BRD |
| G-04 | Monitoring yang bisa ditindaklanjuti | Bi-weekly, mentoring, PIC, due date, tindakan, status jelas | BRD |
| G-05 | AI hanya membantu keputusan | Saran tim dan scoring punya alasan + sumber; keputusan tetap manusia | BRD |
| G-06 | Penilaian deterministik | Bobot rubrik tepat 100%; final weight dihitung backend; snapshot rubrik | BRD |
| G-07 | Keamanan granular | Row-level/project-level authorization, restricted scoring, audit | BRD |
| G-08 | Performa tervalidasi | Uji 300 concurrent active users, peak 450 VU; non-AI API P95 <2s, dashboard P95 <3s, sesuai skenario tes | Target BRD, belum jaminan |
| G-09 | UI implementable dan konsisten | Semua halaman wajib Breadcrumb, Title, Description; shadcn Base UI; responsive; no dark mode | Kebutuhan implementasi |
| G-10 | Berkas siap dianalisis | PDF evidence 10+ MB aman diunggah/ditampilkan, status async jelas; tidak mengunci request HTTP | Kebutuhan implementasi |

## 4. Outcome per pengguna

- **SIMer/Member:** dapat mengisi profil dan kompetensi, mengajukan proposal, berkontribusi ke proyek dan evidence sesuai akses.
- **Team Leader:** dapat membentuk tim valid, mengurus proposal, menjalankan 8 Step dan laporan rutin.
- **Mentor:** dapat memberi feedback, tindak lanjut, approval sesuai penugasan.
- **Owner QCC:** dapat mengawasi proyek, meninjau penilaian AI, dan menyetujui hasil final.
- **Super Admin:** dapat mengelola organisasi, kompetensi, batch, rubrik, role dan audit.

## 5. Non-goals / tidak dikerjakan penuh dalam rilis ini

- Pelaporan eksekutif menyeluruh, export agregat, ranking/award/convention publication dan analitik lanjutan proses 10.
- Pengambilan keputusan final otomatis oleh OpenAI.
- Pembacaan percakapan AI eksternal dari sebuah URL tanpa integrasi dan izin.
- Pengesahan nilai finansial otomatis.
- Aplikasi mobile native; targetnya web responsif.
- Integrasi aplikasi internal yang API dan izin aksesnya belum disepakati.

## 6. Milestone & exit criteria

| Milestone | Lingkup | Kriteria keluar |
| --- | --- | --- |
| M1 | Auth, pengguna, kompetensi, master | Role/policy lolos uji, master 14 organisasi & template 62 kompetensi siap diimport |
| M2 | Proposal + AI matching + tim | Tim invalid ditolak oleh backend, alur submit/revise/approve bekerja |
| M3 | Proyek, 8 step, bi-weekly, mentoring | Evidence terhubung step/report, history review tercatat |
| M4 | AI scoring, review, approval | Gap evidence jelas, hasil JSON valid, skor final dihitung dan disetujui manusia |
| M5 | Security, load, backup, UAT | Acceptance suite lulus, backup-restore & load report tersedia; keputusan stakeholder disahkan |

## 7. Risiko kunci dan strategi

- **Master 62 kompetensi belum ada daftar rinci di BRD:** siapkan CSV importer/seed placeholder **tanpa mengarang nama**; jangan hardcode daftar fiktif.
- **Kriteria evidence dan approval belum final:** model sebagai configurable policy, lakukan sign-off dahulu.
- **LLM tidak deterministik:** schema, validasi evidence IDs, evaluasi kualitas dan approval manusia.
- **PDF berat:** private storage, scan, queue, extraction/cache, limit concurrency, file retention.
- **Beban dashboard:** summary queries, eager loading, indeks, cache yang punya invalidation jelas.

## 8. Definition of Done lintas modul

Task dianggap selesai jika: migration/seed aman; FormRequest + Policy teruji; React Base UI sesuai design system; state error/empty/loading siap; query N+1 dievaluasi; audit/notification sesuai kebutuhan; test lulus; task checklist diperbarui beserta bukti.
