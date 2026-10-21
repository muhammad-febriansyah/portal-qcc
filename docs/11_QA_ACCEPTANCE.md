# QA, Acceptance Criteria & Security Test Plan

## 1. Pengujian wajib

- Laravel **Pest/PHPUnit feature tests**, Policy + FormRequest + service tests.
- React TS component tests untuk input/labels/breadcrumb/table; Playwright untuk end-to-end workflow + responsive.
- OpenAI stub/mock responses untuk deterministik; quality benchmark menggunakan evidence nyata **yang telah diizinkan**.
- Browser desktop modern & viewport mobile/tablet; keyboard/focus/ARIA.
- Load via k6/JMeter/Artillery sesuai alat yang disetujui tim; jalankan di staging, bukan production.

## 2. Mapping acceptance BRD (AT-01 s.d. AT-20)

| AT | Scenario | Expected result | Status dalam scope |
| --- | --- | --- | --- |
| AT-01 | Kompetensi user | self 1–4 terpisah dari verified | Wajib |
| AT-02 | Tim 6 anggota | ditolak backend | Wajib |
| AT-03 | Tim 0 atau 2 leader | ditolak backend | Wajib |
| AT-04 | Tim 1 LOB/Divisi | ditolak backend | Wajib |
| AT-05 | Anggota duplikat | ditolak backend | Wajib |
| AT-06 | Proposal workflow | draft, submit, revision, resubmit, status history | Wajib |
| AT-07 | 8 Step QCC | update status/evidence; review authorized only | Wajib |
| AT-08 | Bi-weekly/mentoring | action, PIC, due date, evidence, follow-up tersimpan | Wajib |
| AT-09 | Evidence gate | incomplete → `INSUFFICIENT_EVIDENCE`, gap list | Wajib |
| AT-10 | Rubric weights | tidak publish bila != 100 | Wajib |
| AT-11 | Weighted score | dihitung backend, reproducible | Wajib |
| AT-12 | Rubric versioning | published immutable, old runs point old version | Wajib |
| AT-13 | Scoring privacy | unauthorized gagal UI + direct route/endpoint | Wajib |
| AT-14 | Human approval | AI tidak final; perubahan+alasan+actor tercatat | Wajib |
| AT-15 | Evidence references | invalid citation ditolak/ditandai | Wajib |
| AT-16 | Prompt injection | file tidak bisa ubah rules/role/rubrik | Wajib |
| AT-17 | Audit trail | tim/rubrik/score/approval/role tercatat | Wajib |
| AT-18 | Load test | 300 active & peak 450 VU, latency/error/resources tercatat | Wajib |
| AT-19 | Recovery | backup/restore diuji terhadap RPO/RTO yang disahkan | Wajib |
| AT-20 | Export access | export hanya data authorized | **Deferred** bila export penuh masuk proses 10; jika ada download file, access test tetap wajib |

## 3. UI acceptance checklist

- Every **internal** route renders breadcrumb + page title + description in that order.
- Light only. Poppins, #2547F9/#F7F8FD/#FFFFFF, borders subtle, no emoji.
- Create/edit forms: placeholder, required asterisk red, inline error, loading, disabled submit, cancel/back.
- Dates use shadcn `Popover + Calendar`, not `input type=date`.
- Tables use Base Data Table, server-side query/filter/sort/page, last-cell Ellipsis dropdown for authorized actions.
- Required bulk action uses `AlertDialog` confirmation where destructive.
- Modern file field: preview images/PDF, upload percent/status/cancel/retry, no direct public object URL.
- Sonner green/orange/red for success/warning/error; errors not only toast.
- No inaccessible icon-only button; add `aria-label`, keyboard focus, contrast.
- Test 375px, 768px, 1280px and 1440px widths (suggestion).

## 4. Negative / abuse tests

1. Member alters `project_id` and accesses someone else's project file → denied.
2. Team Leader calls scoring result endpoint by ID despite hidden sidebar → denied.
3. Concurrent team update by two users tries to create sixth member → only valid transaction succeeds.
4. Published rubric edited via forged PATCH → denied.
5. AI JSON references fake evidence → rejected / invalid run, not approved.
6. Evidence PDF includes adversarial prompt → no change to rubric/permission/calculation.
7. Upload disguised executable / corrupted PDF / over-size → safe failure with 422/blocked.
8. Evidence download token expired or revoked → denied.
9. Reviewer approval double-click / parallel requests → exactly one final decision + audit.
10. Job timeout or provider 429/5xx → retry/backoff, status legible, no double charge attempt where dedupe possible.
11. Review action without explanation on score override → 422.
12. Inertia response should never include private file contents/AI secret/unauthorized scoring info.

## 5. Performance & reliability test plan

- Seed realistic data volume for users, projects, evidence, steps, reports, scoring runs; anonymize.
- Measure query count/p95 for proposal list, dashboard, project detail, team search and scoring review.
- Baseline targets from BRD: non-AI API P95 under 2s; dashboard P95 under 3s; **300 active** and **450 peak VU** on agreed test mix.
- Upload PDF 10MB, 25MB, near max size concurrently; track PHP memory/Nginx timeout/storage errors.
- Run file and OpenAI queues concurrently; verify HTTP pages stay responsive.
- Redis down, S3 timeout, OpenAI unavailable, MySQL connection pool pressure — graceful handling.
- Backup retention/restore test, audit immutability test, secrets not printed in logs.

## 6. E2E happy-path journey

1. Super Admin seeds 14 organizations, imports official 62 competencies, sets batch/rubric.
2. Member logs in, fills profile and self-assessment.
3. Member makes proposal draft, attaches document, selects competencies.
4. Leader/authorized party runs AI match, reviews candidate, confirms valid team.
5. Proposal submitted, reviewer requests revision, team resubmits, owner approves.
6. Project displays exactly eight steps. Team fills and submits progress/evidence.
7. Mentor contributes session/feedback/action; leader submits bi-weekly report.
8. Owner triggers scoring; first attempt missing evidence yields gap list.
9. User completes evidence; scoring job returns valid criteria+citations, Laravel weighted score.
10. Reviewer adjusts score with reason and approves final; audit history shows actor/time, member cannot see restricted result.

## 7. Test evidence format

For each implemented task store `evidence` pointer to test ID/result, screenshot for UI if needed, timing/EXPLAIN for database, and `reviewed_by` for privileged security/scoring tasks. Acceptance is **not** "tested" merely because command ran; it must pass.
