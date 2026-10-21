# Instruksi Coding Agent — Portal QCC SIMGROUP

Baca sebelum menjalankan fitur, mengubah database, UI, atau task.

## Startup per sesi

1. Baca `00_README.md`, `12_TASKLIST_AI.md`, `13_OPEN_DECISIONS.md`.
2. Untuk fitur yang akan dikerjakan baca PRD, user story, ERD, workflow/RBAC, design system dan dokumen teknis yang relevan.
3. Pilih task paling awal yang seluruh dependency-nya terpenuhi. Jangan mengisi business gap dengan asumsi palsu.
4. Implementasi kecil, tes, bukti, lalu tandai selesai pada tasklist.

## Mutlak

- Laravel 13 + React Inertia TS + Tailwind 4 + shadcn/ui **Base UI**. Tidak dark mode.
- UI `#2547F9`, `#F7F8FD`, `#FFFFFF`, Poppins; no emoji, only Lucide icons.
- Semua halaman internal `Breadcrumb, Title, Description` pada urutan itu.
- Semua list: shadcn Base Data Table + server filtering/sorting/pagination; row actions lewat Ellipsis dropdown.
- Semua form: placeholder/hint, red `*` for required, inline validation, date picker shadcn bukan native, upload preview modern.
- Notifikasi Sonner success hijau, warning oranye, error merah.
- Gunakan eager loading relasi yang terlihat, lazy/deferred data khusus tab yang belum diminta, indeks dan profiling query; database scope sebelum pagination.
- OpenAI backend only, output validated; team constraints & weighted score Laravel deterministic; final approval human only.
- 5 role official; `reviewer` merupakan assignment/permission, tidak buat role keenam tanpa persetujuan.
- Evidence private, storage file bukan database BLOB, scanning & AI processing via queue.
- Scope hanya proses bisnis 1–9, termasuk QCC steps 1–8.

## Task updates

- `- [ ]` belum selesai; baru ubah `- [x]` setelah build, test, security & acceptance relevant lulus.
- Tambahkan progress entry `YYYY-MM-DD | QCC-XXX | DONE | files: ... | tests: ... | notes: ...` di `12_TASKLIST_AI.md`.
- Jika task blocked, tulis alasan di log tanpa mencentang.
- Jangan centang semua sekaligus atau mengklaim test lulus tanpa output.
- Jika requirement berubah, update PRD/ERD/User Stories/Tests/Design sebelum menandai Done.

## Periksa sebelum commit

- PHP test/Pest, PHP Pint, TS typecheck, JS lint/build, critical Playwright paths.
- No N+1, no unbounded `->get()` on lists, no lazy-load in row loops.
- No upload/storage public disclosure; no private scoring inside Member Inertia props.
- Form labels and required markers, toast colors, date component, row action menu, responsive UI verified.
