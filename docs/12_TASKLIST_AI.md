# TASKLIST IMPLEMENTASI AI — Portal QCC SIMGROUP

**Tujuan:** satu sumber pelacakan untuk coding agent (Codex/Claude Code/dll) agar bisa **mencentang tugas yang benar-benar sudah dikerjakan** dan tidak melewati acceptance criteria.  
**Scope:** proses bisnis 1–9 termasuk 8 Step QCC; bukan modul laporan agregat nomor 10.

## 0. Protokol task status (WAJIB)

- Tiap baris `- [ ]` berarti **TODO**, ubah menjadi `- [x]` hanya ketika **semua acceptance criteria terkait terbukti**.
- Jangan mark DONE bila baru scaffolding, UI statis, code belum compile, atau test gagal.
- Saat memulai task yang belum selesai, boleh tulis catatan `IN_PROGRESS` pada progress log bagian akhir, **tanpa** centang checkbox.
- Setiap task selesai harus punya jejak commit/PR atau file path, perintah & hasil test, bukti screenshot bila UI, dan perubahan doc jika kontrak berubah.
- Task berlabel `[BLOCKED: DEC-xx]` tidak boleh diisi data bisnis karangan; tunggu keputusan atau implementasi interface/configurable draft tanpa mengklaim final.
- Saat satu task selesai, edit **file ini langsung**: centang task ID itu saja dan update `Progress Log`. Jangan reset task yang dicentang.
- Tugas yang perlu migrasi wajib melampirkan test migration/rollback atau migrasi backward compatible yang dievaluasi.
- Setiap page yang dibuat harus mematuhi `05_DESIGN_SYSTEM.md` secara keseluruhan, bukan hanya menyalin satu komponen.
- Semua task wajib lolos authorizations, security dan query standards relevan; non-goals proses nomor 10 tidak boleh diselundupkan ke scope.

**Format log wajib:** `YYYY-MM-DD | TASK-ID | DONE | files: ... | tests: ... | notes: ...`.

## FASE 0 — Discovery & keamanan keputusan (paling ringan)

- [x] **QCC-001 [P0]** — Review dokumen `00`–`13`, tetapkan scope 1–9; **AC:** tidak ada fitur proses 10 di MVP backlog tanpa change request. Dependensi: —.
- [ ] **QCC-002 [P0]** — Workshop/susun approval matrix 5 role & reviewer assignment; **AC:** permission scope disetujui; `[BLOCKED: DEC-03, DEC-05..09, DEC-14]`. Dependensi: QCC-001.
- [ ] **QCC-003 [P0]** — Minta & impor daftar resmi C001–C062; **AC:** tepat 62 record bernama/terdefinisi valid tanpa data fiktif; `[BLOCKED: DEC-02]`. Dependensi: QCC-001.
- [ ] **QCC-004 [P0]** — Kunci evidence minimum, rubrik 3 tipe & scoring timing; **AC:** ditandatangani Owner QCC; `[BLOCKED: DEC-10..13]`. Dependensi: QCC-001.
- [ ] **QCC-005 [P0]** — Konfirmasi privacy/OpenAI/file/hosting/backup; **AC:** keputusan vendor/data/retention dicatat; `[BLOCKED: DEC-17..20]`. Dependensi: QCC-001.
- [ ] **QCC-006 [P1]** — Finalisasi navigation map per role dan user journey; **AC:** halaman 1–9 + breadcrumb/title/desc terpetakan. Dependensi: QCC-002.

## FASE 1 — Bootstrap, design foundation, dev productivity

- [x] **QCC-010 [P0]** — Inisialisasi Laravel 13 + React Inertia TS starter kit + Tailwind 4; **AC:** login skeleton/build/health check jalan, typecheck lulus. Dependensi: QCC-001.
- [x] **QCC-011 [P0]** — Setup MySQL, Redis, `.env.example`, migrations, local/staging config; **AC:** migration up, Redis ping, secrets tidak dicommit. Dependensi: QCC-010.
- [x] **QCC-012 [P0]** — Install/pin shadcn/ui **Base UI** + Lucide, tanpa campur Radix; **AC:** components import dan Vite build lulus. Dependensi: QCC-010.
- [x] **QCC-013 [P0]** — Implementasi token CSS light-only `#2547F9`, `#F7F8FD`, `#FFFFFF` + Google Poppins; **AC:** visual snapshot desktop/mobile, tidak ada dark mode/emoji. Dependensi: QCC-012.
- [x] **QCC-014 [P0]** — Shared AppShell/topbar/sidebar collapsible + mobile Sheet; **AC:** role navigation dapat dipakai keyboard/responsive. Dependensi: QCC-013.
- [x] **QCC-015 [P0]** — Shared `PageHeader` Breadcrumb → Title → Description → Actions; **AC:** setiap page internal menggunakan komponen, pengecualian halaman auth. Dependensi: QCC-014.
- [x] **QCC-016 [P0]** — Shared FormField + placeholder + required red star + inline error; **AC:** form sample aksesibel, server/client validation terlihat. Dependensi: QCC-012.
- [ ] **QCC-017 [P0]** — Shared shadcn Date Picker `Popover + Calendar` (termasuk date range); **AC:** tidak ada native date inputs pada form app. Dependensi: QCC-012.
- [ ] **QCC-018 [P0]** — Shared Base Data Table sesuai official link, pin TanStack API version; **AC:** server pagination/filter/sort/search dan empty/loading state. Dependensi: QCC-012.
- [ ] **QCC-019 [P0]** — Shared `Ellipsis` row DropdownMenu + AlertDialog destructive; **AC:** item berdasarkan policy, bukan action button langsung bertebaran. Dependensi: QCC-018.
- [ ] **QCC-020 [P0]** — Global Sonner success hijau/warning oranye/error merah; **AC:** accessible icon Lucide & no emoji, test 3 variants. Dependensi: QCC-013.
- [ ] **QCC-021 [P0]** — Shared file/image uploader & PDF viewer preview; **AC:** drag-drop, thumbnail, size, progress, cancel/retry, URL cleanup. Dependensi: QCC-016, QCC-020.
- [ ] **QCC-022 [P1]** — Global empty/loading/403/404/error/skeleton components; **AC:** demo 4 states tiap major module. Dependensi: QCC-014.
- [ ] **QCC-023 [P0]** — PHP Pint, ESLint/TS checking, tests, CI setup; **AC:** pipeline gagal pada build/test failure. Dependensi: QCC-010.

## FASE 2 — Identity, master, authorization

- [ ] **QCC-030 [P0]** — Implementasi auth/login/logout/reset/session hardening; **AC:** auth E2E success/denied/expired session. Dependensi: QCC-010.
- [ ] **QCC-031 [P0]** — Integrasi Spatie Permission roles 5 dan grant policy; **AC:** seed idempotent, no role sexto otomatis. Dependensi: QCC-030, QCC-002.
- [ ] **QCC-032 [P0]** — Laravel Policies project-level, evidence, scoring, reviewer; **AC:** unauthorized direct route 403. Dependensi: QCC-031.
- [x] **QCC-033 [P1]** — Migrations users profile/organizations/batches/competency group/master; **AC:** FK + indexes + rollback test. Dependensi: QCC-011.
- [ ] **QCC-034 [P1]** — Seed 14 organisasi resmi & kelompok A–F; **AC:** repeat seed tidak menggandakan row. Dependensi: QCC-033.
- [ ] **QCC-035 [P1]** — Import CSV 62 kompetensi resmi; **AC:** validasi duplicate, missing and group refs; `[BLOCKED: DEC-02]`. Dependensi: QCC-034, QCC-003.
- [ ] **QCC-036 [P1]** — CRUD Admin Users/Role/Org/Batch/Master Kompetensi pakai Data Table; **AC:** per page breadcrumb/title/desc, Ellipsis actions, policy, Sonner. Dependensi: QCC-015, QCC-018, QCC-032, QCC-034.
- [ ] **QCC-037 [P1]** — Form profil + availability; **AC:** field validation, org selector, privacy; `[BLOCKED: DEC-01]` untuk fields final. Dependensi: QCC-016, QCC-032.
- [ ] **QCC-038 [P1]** — Self-assessment kompetensi 1–4; **AC:** unique member skill, verified tidak tertimpa; US-003 test. Dependensi: QCC-035, QCC-037.
- [ ] **QCC-039 [P1]** — Workflow verifikasi level kompetensi; **AC:** separate verified_by, permission & audit; `[BLOCKED: DEC-03]`. Dependensi: QCC-038, QCC-032.

## FASE 3 — Proposal & pembentukan tim

- [ ] **QCC-049 [P0]** — OpenAI server client wrapper, secret config, log sanitization, timeout/cost controls; **AC:** SDK calls mocked, key never exposed, client test passes; production awaits DEC-18. Dependensi: QCC-011.

- [x] **QCC-050 [P1]** — Migrations Project/Proposal Requirements/ProjectMembers/TeamMatchingRuns; **AC:** FK unique composite dan indexes. Dependensi: QCC-033.
- [ ] **QCC-051 [P1]** — Wizard 5 tahap proposal + draft/save/preview/lampiran; **AC:** validation, responsive, per-field required, resume. Dependensi: QCC-050, QCC-021, QCC-016.
- [ ] **QCC-052 [P1]** — Proposal Data Table & scoped search/filter/status; **AC:** no N+1, paginate SQL, Ellipsis actions. Dependensi: QCC-051, QCC-018, QCC-032.
- [ ] **QCC-053 [P1]** — Form kebutuhan kompetensi proyek; **AC:** master competency IDs, level optional, no duplicate. Dependensi: QCC-038, QCC-051.
- [ ] **QCC-054 [P0]** — `TeamRuleValidator` max5, exactly1 leader, 2+ LOB, no dup; **AC:** unit tests termasuk concurrent invalid updates. Dependensi: QCC-050.
- [ ] **QCC-055 [P1]** — Candidate query indexed, sanitized shortlist & snapshot; **AC:** eligible by project, no private contacts; EXPLAIN measured. Dependensi: QCC-053, QCC-032.
- [ ] **QCC-056 [P1]** — AI Team Matching server job + strict JSON validator; **AC:** real IDs only, persistent run, retries/no manual API key browser. Dependensi: QCC-055, QCC-049 (OpenAI client baseline).
- [ ] **QCC-057 [P1]** — Matching UI candidate cards, alasan/gap, manual override; **AC:** review/change/confirm state, loading and error. Dependensi: QCC-056, QCC-015.
- [ ] **QCC-058 [P0]** — Team confirm action in DB transaction; **AC:** validates 5/1/2/unique before active, records actor/changes. Dependensi: QCC-054, QCC-057.
- [ ] **QCC-059 [P1]** — Proposal submit/review/revise/resubmit/approve/reject; **AC:** state machine, history, permission, notification. Dependensi: QCC-058, QCC-032.
- [ ] **QCC-060 [P1]** — Reviewer/Owner proposal inbox & review dialog; **AC:** approve/revision reasons & audit visible. Dependensi: QCC-059, QCC-019.

## FASE 4 — 8 Step QCC, monitoring, evidence

- [ ] **QCC-061 [P1]** — Migrations + seeder 8 Step fixed order; **AC:** exactly 8 unique steps/project, after approval. Dependensi: QCC-059.
- [ ] **QCC-062 [P1]** — 8 Step UI detail project with progress/navigation; **AC:** all official names and full form fields, responsive. Dependensi: QCC-061, QCC-017.
- [ ] **QCC-063 [P1]** — Step save/submit/review/revision/approve; **AC:** policy + state machine + audit & version control. Dependensi: QCC-062, QCC-032.
- [ ] **QCC-064 [P0]** — Private disk/object storage, file metadata & scan status migrations; **AC:** private URLs, no public leaks. Dependensi: QCC-011, QCC-032.
- [ ] **QCC-065 [P0]** — Evidence upload endpoint type/size/signature scan; **AC:** PDF 10MB+ accepted according to config, invalid file rejected. Dependensi: QCC-064, QCC-021.
- [ ] **QCC-066 [P1]** — Evidence link/versioning & viewer; **AC:** project/step/report relation valid, no overwrite scored evidence. Dependensi: QCC-065.
- [ ] **QCC-067 [P1]** — Evidence preview/thumb & extraction queues; **AC:** scanned PDF and image processing status, retries, no request timeout. Dependensi: QCC-065.
- [ ] **QCC-068 [P1]** — Risk/Issue CRUD and action tracker; **AC:** PIC due/status, audit, filters indexes. Dependensi: QCC-061.
- [ ] **QCC-069 [P1]** — Bi-weekly report + attendees + evidence + review; **AC:** period/meeting/agenda/plan-vs-actual/action fields valid. Dependensi: QCC-066, QCC-068.
- [ ] **QCC-070 [P1]** — Mentor assignments, mentoring sessions, feedback & actions; **AC:** mentor sees assigned projects only; `[BLOCKED: DEC-09]` approval detail. Dependensi: QCC-066, QCC-068.
- [ ] **QCC-071 [P1]** — In-app notifications due/revision/submission/mentoring; **AC:** correct recipient, no private scoring leak. Dependensi: QCC-059, QCC-070.
- [ ] **QCC-072 [P1]** — Role-based compact dashboard; **AC:** pending tasks/proposals/step/status with scoped SQL and no reporting #10. Dependensi: QCC-063, QCC-069.

## FASE 5 — OpenAI scoring & final review (kompleks)

- [ ] **QCC-075 [P0]** — Rubric sets/version/criteria migrations; **AC:** immutable published, weights=100 check, history. Dependensi: QCC-011, QCC-004.
- [ ] **QCC-076 [P1]** — Rubric management UI/Base Data Table; **AC:** publish blocked weights!=100, version comparison, permission. Dependensi: QCC-075, QCC-018.
- [ ] **QCC-077 [P0]** — Scoring evidence gate & manifest builder; **AC:** missing evidence → INSUFFICIENT_EVIDENCE + gaps (not numeric zero). Dependensi: QCC-067, QCC-075, QCC-004.
- [ ] **QCC-078 [P0]** — Structured output JSON schema + ID/evidence/criteria validation; **AC:** invalid ID/citation/out-of-range rejected. Dependensi: QCC-077, QCC-049.
- [ ] **QCC-079 [P0]** — Deterministic weighted scoring calculator; **AC:** unit tests for all 3 rubrics, decimal/rounding. Dependensi: QCC-075.
- [ ] **QCC-080 [P1]** — Async scoring run orchestration/history/queue retry/idempotency; **AC:** no double runs, output valid or reason failed. Dependensi: QCC-078, QCC-079.
- [ ] **QCC-081 [P1]** — Scoring status & detail UI; **AC:** score by criteria, evidence citation/gaps/uncertainty, no unauthorized access. Dependensi: QCC-080, QCC-032.
- [ ] **QCC-082 [P0]** — Human review, reviewer score override + reason, final approval DB transaction; **AC:** scores separate, all decisions audited, no automatic approval. Dependensi: QCC-081, QCC-060.
- [ ] **QCC-083 [P0]** — AI prompt injection adversarial tests & evidence grounding QA; **AC:** scoring/rubric/permissions unaffected by uploaded instructions. Dependensi: QCC-080.
- [ ] **QCC-084 [P0]** — Scoring privacy penetration tests (UI/API/download/export stub); **AC:** member/mentor/leader unauthorized denied. Dependensi: QCC-082.

## FASE 6 — Hardening, performance, QA, launch

- [ ] **QCC-090 [P0]** — Server pagination/filter/sort audits all modules; **AC:** SQL-scoped data, whitelist sorts, no huge lists. Dependensi: QCC-072.
- [ ] **QCC-091 [P0]** — Eager loading, `withCount`, N+1 detector, indexed queries & EXPLAIN; **AC:** logs/query count evidence per critical route. Dependensi: QCC-090.
- [ ] **QCC-092 [P1]** — Redis caching & invalidation (master/dashboard); **AC:** no stale permission/score leakage. Dependensi: QCC-091.
- [ ] **QCC-093 [P0]** — Full flow Pest/Playwright regression, accessibility, mobile/tablet; **AC:** happy + negative test suites pass and screenshot baseline. Dependensi: QCC-082.
- [ ] **QCC-094 [P0]** — Security review auth/CSRF/upload/authorization/PII/AI safety; **AC:** high/critical findings fixed, reviewer signs off. Dependensi: QCC-093.
- [ ] **QCC-095 [P0]** — Load tests 300 concurrent active, 450 peak VU, p95 report; **AC:** baseline document with request mix, errors, infra metrics. Dependensi: QCC-091, QCC-093.
- [ ] **QCC-096 [P0]** — Backup + restore exercise and RPO/RTO; **AC:** successful rehearsal and runbook. Dependensi: QCC-094.
- [ ] **QCC-097 [P1]** — Staging pilot/UAT 5 role with stakeholder sign-off; **AC:** blockers closed, open decisions resolved or formally waived. Dependensi: QCC-093, QCC-095.
- [ ] **QCC-098 [P0]** — Production readiness checklist and secrets deployment; **AC:** ops monitoring, queue worker, rollback, security sign-off. Dependensi: QCC-096, QCC-097.
- [ ] **QCC-099 [P1]** — Update docs & changelog based on actual implementation; **AC:** PRD/ERD/Design/Tasklist consistent and links work. Dependensi: QCC-098.

## FASE FUTURE — Scope excluded (do not mark as MVP work)

> **Tidak dimasukkan ke checklist rilis 1–9**: dashboard analitik organisasi lanjutan, export report lintas proyek dan ranking/award convention yang termasuk kebutuhan proses 10/keputusan lanjutan. Buat backlog terpisah ketika di-approve.

## Progress Log (coding agent updates below)

```text
YYYY-MM-DD | TASK-ID | STATUS | files: ... | tests: ... | notes: ...
```

<!-- Tambahkan entry di bawah komentar ini setelah pekerjaan benar-benar selesai. Jangan hapus entry lama. -->
2026-10-21 | QCC-001 | DONE | files: docs/00_README.md, docs/01_PROJECT_GOALS.md, docs/02_PRD.md, docs/03_USER_STORIES.md, docs/04_ERD.md, docs/05_DESIGN_SYSTEM.md, docs/06_WORKFLOWS_RBAC.md, docs/07_ARCHITECTURE.md, docs/08_OPENAI_INTEGRATION.md, docs/09_FILE_EVIDENCE.md, docs/10_DATABASE_PERFORMANCE.md, docs/11_QA_ACCEPTANCE.md, docs/12_TASKLIST_AI.md, docs/13_OPEN_DECISIONS.md | tests: n/a | notes: reviewed scope 1–9; process 10 stays deferred.
2026-10-21 | QCC-010 | DONE | files: composer.json, package.json, routes, resources/js/pages/auth/login.tsx | tests: `rtk npm run build` PASS; `rtk npm run types:check` PASS | notes: `rtk php artisan about` and login/health route checks PASS; Laravel 13, Inertia 3, React, Tailwind 4 present.
2026-10-21 | QCC-011 | IN_PROGRESS | files: .env.example | tests: MySQL database provisioned and `rtk php artisan migrate --force` PASS; `rtk redis-cli ping` PONG | notes: `.env` ignored by `.gitignore`; sample now uses MySQL and Redis; PHP Redis client unavailable, so runtime cache/queue cannot yet use Redis.
2026-10-21 | QCC-033 | DONE | files: database/migrations/2026_10_21_124753_create_qcc_organizations_competencies_and_batches_tables.php, database/migrations/2026_10_21_124754_add_qcc_profile_fields_to_users_table.php, database/migrations/2026_10_21_124755_create_member_competencies_table.php, tests/Feature/Database/QccFoundationSchemaTest.php | tests: Pest 1 passed/2 assertions; SQLite migration up→rollback→up PASS | notes: schema verified on MySQL; official C001–C062 records not seeded pending DEC-02.
2026-10-21 | QCC-011 | BLOCKED | files: .env.example, config/database.php, config/cache.php, config/queue.php | tests: `rtk redis-cli -h 127.0.0.1 -p 6379 ping` PASS; `rtk php artisan migrate:status` BLOCKED | notes: MySQL has no `portal_qcc` database; Redis server responds but PHP Redis client unavailable; `.env.example` still defaults to SQLite/database cache+queue.
2026-10-21 | QCC-011 | DONE | files: .env.example, .env, composer.json, composer.lock, database/migrations | tests: `rtk php artisan migrate:status` PASS; Predis `PING` PASS; Redis CLI PING PASS | notes: created/confirmed local `portal_qcc`, installed Predis 3.7.0, set local/example queue+cache to Redis and aligned DB name; no credentials logged.
2026-10-21 | QCC-012 | BLOCKED | files: package.json, components.json, resources/js/components/ui | tests: not run | notes: shadcn CLI cannot start; npm registry request hangs and offline install fails because existing npm cache has root-owned files. Base UI migration not applied.
2026-10-21 | QCC-012 | DONE | files: components.json, package.json, package-lock.json, pnpm-lock.yaml, resources/js/components/ui, resources/js/components, resources/js/layouts, resources/js/app.tsx, resources/js/types/global.d.ts | tests: `rtk npm run build` PASS; `rtk npm run types:check` PASS | notes: shadcn config uses base-nova, @base-ui/react pinned at 1.9.0, Lucide retained; removed direct Radix dependencies/imports and replaced `asChild` composition with Base UI `render` props.
2026-10-21 | QCC-013 | DONE | files: resources/css/app.css, resources/views/app.blade.php, resources/js/app.tsx, resources/js/components/ui/sonner.tsx, resources/js/pages/settings/appearance.tsx, resources/js/components/appearance-tabs.tsx, resources/js/hooks/use-appearance.tsx, resources/js/**/*.tsx | tests: `rtk npm run build` PASS; `rtk npm run types:check` PASS; screenshots `qcc013-desktop.png` (1440x900), `qcc013-mobile.png` (390x844) | notes: light token palette/Poppins applied; system dark preference and appearance toggle removed; dark-emulated browser remained `rgb(247, 248, 253)` with `color-scheme: light`; Poppins CSS/font requests returned 200.
2026-10-21 | QCC-014 | IN_PROGRESS | files: resources/js/components/ui/sidebar.tsx, resources/js/components/nav-main.tsx | tests: `rtk npm run build` PASS; `rtk npm run types:check` PASS | notes: sidebar width aligned to 240px; added primary-navigation landmark and aria-current; Base UI sidebar has keyboard shortcut and mobile Sheet; users table empty, so authenticated shell screenshot and approved role map (QCC-002/QCC-006) remain pending.
2026-10-21 | QCC-014 | DONE | files: resources/js/components/ui/sidebar.tsx, resources/js/components/nav-main.tsx, docs/12_TASKLIST_AI.md | tests: `rtk npm run build` PASS; `rtk npm run types:check` PASS; `Control+b` collapsed desktop sidebar; mobile Sheet opened with keyboard-accessible Dashboard link; screenshots `qcc014-shell-desktop.png` (1440x900), `qcc014-shell-desktop-collapsed.png`, `qcc014-shell-mobile.png` (390x844), `qcc014-shell-mobile-sheet.png` | notes: temporary UserFactory account deleted; verified users table count returned to 0; role-specific destinations remain governed by QCC-006, no menu decisions invented.
2026-10-21 | QCC-015 | IN_PROGRESS | files: resources/js/components/page-header.tsx, resources/js/pages/dashboard.tsx, resources/js/layouts/settings/layout.tsx | tests: `rtk npm run types:check` PASS; `rtk npm run build` PASS | notes: shared header now owns breadcrumbs; screenshots/render review pending.
2026-10-21 | QCC-015 | DONE | files: resources/js/components/page-header.tsx, resources/js/components/app-sidebar-header.tsx, resources/js/layouts/app-layout.tsx, resources/js/layouts/app/app-sidebar-layout.tsx, resources/js/layouts/settings/layout.tsx, resources/js/pages/dashboard.tsx, resources/js/pages/settings/profile.tsx, resources/js/pages/settings/security.tsx, resources/js/pages/settings/appearance.tsx, docs/12_TASKLIST_AI.md | tests: `rtk npm run types:check` PASS; `rtk npm run build` PASS; Playwright desktop/mobile snapshots verified PageHeader order on dashboard/profile/appearance; screenshots `qcc015-dashboard-desktop.png`, `qcc015-dashboard-mobile.png`, `qcc015-settings-desktop.png`, `qcc015-settings-mobile.png` | notes: auth screens excluded; settings PageHeader sits above settings navigation; no console errors after Base UI link semantics fix; security route requires password confirmation, so visual check deferred behind app middleware.
2026-10-21 | QCC-016 | IN_PROGRESS | files: resources/js/components/form-field.tsx, resources/js/pages/settings/profile.tsx | tests: not run | notes: add accessible label/hint/required/error wiring; check native and server validation.
2026-10-21 | QCC-016 | DONE | files: resources/js/components/form-field.tsx, resources/js/pages/settings/profile.tsx, resources/js/pages/settings/security.tsx, resources/js/pages/auth/forgot-password.tsx, resources/js/pages/auth/confirm-password.tsx, docs/12_TASKLIST_AI.md | tests: `rtk npm run types:check` PASS; `rtk npm run build` PASS; browser confirmed required blocks empty submit, server max-length error displays inline with `aria-invalid`/alert; screenshot `qcc016-profile-validation.png` | notes: added shared label/placeholder/hint/required marker/error component; set form CTAs to submit where missing; Laravel `id` locale returns validation translation keys, mapped common field errors to readable Indonesian copy; temporary QA user deleted and users count returned to 0.
2026-10-21 | QCC-050 | DONE | files: database/migrations/2026_10_21_140748_create_projects_proposal_requirements_members_and_matching_runs_tables.php, tests/Feature/Database/QccProjectsSchemaTest.php, docs/04_ERD.md, docs/12_TASKLIST_AI.md | tests: `rtk php artisan test --compact tests/Feature/Database/QccProjectsSchemaTest.php` PASS (1 test, 7 assertions); SQLite migration up/down/up PASS; `rtk php artisan migrate --force` PASS; Boost schema inspection PASS | notes: created projects, project_requirements, project_members, team_matching_runs with FK, unique constraints, and indexes; local MySQL schema updated; remaining ERD tables and RBAC decisions still pending.
