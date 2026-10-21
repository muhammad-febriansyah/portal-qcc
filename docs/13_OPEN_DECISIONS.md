# Open Decisions & Stakeholder Sign-off

Dokumen BRD adalah draft dan masih menyatakan banyak hal perlu dikonfirmasi. **Jangan diam-diam mengubah keputusan bisnis menjadi default permanen**. Buat decision log saat workshop dengan Owner QCC, IT/security dan reviewer.

## 1. Daftar pertanyaan terbuka

| ID | Pertanyaan | Implikasi teknis | Status |
| --- | --- | --- | --- |
| DEC-01 | Apakah biodata/kontak pribadi yang boleh dikumpulkan dan disimpan? | fields/encryption/privacy | BELUM_DIPUTUSKAN |
| DEC-02 | Mana daftar lengkap kompetensi C001–C062 beserta nama, definisi dan kelompok resmi? | seed/import competence | BELUM_DIPUTUSKAN |
| DEC-03 | Siapa petugas yang memverifikasi level kompetensi; bagaimana bukti verifikasinya? | permission/workflow | BELUM_DIPUTUSKAN |
| DEC-04 | Apakah satu user boleh dalam beberapa proyek/batch dan berapa batasnya? | matching/filter/concurrency | BELUM_DIPUTUSKAN |
| DEC-05 | Siapa mengonfirmasi tim dan kapan SIMer menjadi Team Leader? | project role assignment | BELUM_DIPUTUSKAN |
| DEC-06 | Approval proposal siapa, berapa level, SLA dan kondisi reject/resubmit? | reviews/states/notifications | BELUM_DIPUTUSKAN |
| DEC-07 | Apakah 8 Step harus sequential dan siapa reviewer setiap step? | qcc_step policy / UI | BELUM_DIPUTUSKAN |
| DEC-08 | Aturan periode bi-weekly & apakah perlu approval setiap report? | reports / cron / reminder | BELUM_DIPUTUSKAN |
| DEC-09 | Siapa menugaskan mentor dan apakah mentor boleh approve step/hasil scoring? | project_mentors + policy | BELUM_DIPUTUSKAN |
| DEC-10 | Minimum evidence per step dan setiap tipe scoring? | evidence gate | BELUM_DIPUTUSKAN |
| DEC-11 | Kapan Proposal Scoring dilaksanakan relatif terhadap approval proposal? | orchestrator/workflow | BELUM_DIPUTUSKAN |
| DEC-12 | Apakah Final Presentation dan Convention dalam scope rilis 1–9 pada batch ini dan kapan dipicu? | page/jobs/review | BELUM_DIPUTUSKAN |
| DEC-13 | Bobot resmi rubric, interpretasi score, kebijakan rounding & reviewer override? | DB & scoring | BELUM_DIPUTUSKAN |
| DEC-14 | Siapa boleh melihat AI score selain Owner QCC & Super Admin? | RBAC / restricted UI | BELUM_DIPUTUSKAN |
| DEC-15 | Apakah Convention menghasilkan ranking/award atau hanya evaluasi? | proses 10/laporan defer | BELUM_DIPUTUSKAN |
| DEC-16 | Kanal notifikasi: email kantor/in-app/lainnya? | notification driver | BELUM_DIPUTUSKAN |
| DEC-17 | Policy file: tipe, batas MB, malware scan, retention, max files/upload? | evidence pipeline | BELUM_DIPUTUSKAN |
| DEC-18 | Data boleh dikirim ke OpenAI? Region, DPA, redaction, retention? | security/legal gating | BELUM_DIPUTUSKAN |
| DEC-19 | Pilihan hosting/object storage, backups, RPO/RTO, log retention? | ops/deployment | BELUM_DIPUTUSKAN |
| DEC-20 | Perlu SSO perusahaan atau login lokal Laravel cukup? | auth design | BELUM_DIPUTUSKAN |
| DEC-21 | Apakah export/report perlu saat proses 1–9 atau tetap proses 10? | release scope | BELUM_DIPUTUSKAN |
| DEC-22 | SLA 300 concurrent / peak 450: mix actions, concurrency duration, budget infra? | load plan | BELUM_DIPUTUSKAN |

## 2. Baseline rekomendasi sambil menunggu

- UI/light: `#2547F9 / #F7F8FD / #FFFFFF`, Poppins, Base UI.
- Upload max 50 MiB per PDF hanya **asumsi sizing**, bukan nilai disetujui.
- Default scoring visibility hanya Owner QCC/Super Admin, akses lainnya explicit grant.
- Semua approval manusia; tidak ada automated final approval.
- Operasional dasar dashboard tetap ada; dashboard/laporan agregat proses 10 deferred.
- Pemrosesan AI/file async; provider API key di server.

## 3. Template decision log

```md
### DEC-xx — [Judul]
- Status: PROPOSED / APPROVED / REJECTED / SUPERSEDED
- Keputusan: ...
- Disetujui oleh: ...
- Tanggal: ...
- Sumber (BRD/workshop/tiket): ...
- Dampak pada PRD: ...
- Dampak pada ERD/migration: ...
- Dampak pada tasklist/test: ...
```

## 4. Checklist sign-off sebelum production

- [ ] Owner QCC menyetujui field, workflow, roles dan rubric.
- [ ] HR/owner master memasok C001–C062 resmi (62 nama + definisi).
- [ ] IT/security menyetujui akses file, OpenAI data transfer, penyimpanan, retention.
- [ ] Tim QA menyetujui skenario load, vulnerability tests, restore drill.
- [ ] Owner menyetujui batas akhir proses 9 dan keputusan fitur proses 10 ditunda.
