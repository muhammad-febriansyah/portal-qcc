# Database Performance & Query Standards

## 1. Tujuan

Target performa dari BRD adalah **300 pengguna aktif bersamaan**, uji peak **450 virtual users**, API non-AI P95 **<2 detik**, dashboard P95 **<3 detik** pada skenario benchmark yang disepakati. Bukan jaminan tanpa load test.

**Prinsip:** kebenaran authorization + integrity lebih dahulu, lalu optimasi query berdasarkan metrik/EXPLAIN; hindari premature micro-optimization yang menyembunyikan data.

## 2. Aturan query wajib

1. List **di-filter role/project scope pada SQL** sebelum pagination; jangan ambil semua lalu `Collection::filter()`.
2. Hindari N+1: eager load relasi yang dipakai dengan `with()`, hitungan via `withCount()`, agregat via `withSum()`/`selectRaw()` terbatas.
3. Pilih kolom (`select`) minimal termasuk FK untuk eager relation; jangan `SELECT *` dari evidence/scoring JSON di list.
4. Pagination server-side: `paginate(10/25/50)`, `simplePaginate()` untuk count mahal jika UI tidak butuh total, `cursorPaginate()` untuk ordered feeds stabil (syarat sort key unik/stabil dan query kompatibel).
5. Filter & sort dari allowlist, **jangan** memasukkan column/table user input langsung ke `orderByRaw`.
6. Tidak memuat file bytes/PDF, full AI prompt atau payload JSON megabyte ke props Inertia list.
7. Gunakan Redis cache untuk master organisasi/kompetensi, counts atau charts mahal; invalidate saat data berubah.
8. Queue heavy operations (file scan/extract, AI matching/scoring, notifications, export future).
9. Tambahkan indeks berdasar `WHERE + JOIN + ORDER BY`, lalu verifikasi `EXPLAIN ANALYZE`/slow query log.
10. Lakukan load test concurrency state transitions, bukan hanya dashboard page-load.

## 3. Example Query Eloquent — list proposal

```php
$user = auth()->user();

$query = Project::query()
    ->select(['id', 'batch_id', 'proposer_id', 'title', 'proposal_status', 'updated_at'])
    ->with([
        'batch:id,name',
        'proposer:id,name,organization_id',
        'proposer.organization:id,name',
    ])
    ->withCount('members');

// PENTING: scope akses diterapkan di query BEFORE paginate.
if (! $user->can('projects.viewAll')) {
    $query->where(function ($q) use ($user) {
        $q->where('proposer_id', $user->id)
          ->orWhereHas('members', fn ($members) => $members->where('user_id', $user->id))
          ->orWhereHas('mentors', fn ($mentors) => $mentors->where('mentor_id', $user->id));
    });
}

$query->when(request('status'), fn ($q, $status) =>
    $q->where('proposal_status', $status)
);

$allowedSorts = ['updated_at', 'title', 'proposal_status'];
$sort = in_array(request('sort'), $allowedSorts, true)
    ? request('sort') : 'updated_at';
$direction = request('direction') === 'asc' ? 'asc' : 'desc';

$rows = $query->orderBy($sort, $direction)
    ->orderBy('id', $direction)
    ->paginate(min(max((int) request('per_page', 25), 1), 50))
    ->withQueryString();
```

> `projects.viewAll`, `members`, `mentors`, `proposer`, `batch` adalah **nama permission/relation contoh**. Buat/selaraskan pada Model/Policy sesuai `06_WORKFLOWS_RBAC.md`. Pastikan kolom FK tersedia saat memakai `select` pada eager relation. `can()` tanpa subject bukan pengganti Policy per-row untuk aksi mutasi.

## 4. Eager loading vs lazy loading

| Kasus | Pilihan |
| --- | --- |
| Proposal table butuh nama pengaju+org+jumlah anggota | `with(proposer.organization)` + `withCount(members)` |
| Project detail tab 8 Step | load 8 record `steps` + evidence summary sesuai tab |
| Project detail tab Mentoring tidak sedang aktif | **Tidak** preload semua mentoring; load pada tab/route setelah diminta |
| Scoring run list | ringkasan saja; detail score/criteria/evidence on-demand |
| Dashboard quick stats | SQL aggregate & cache dengan invalidation jelas |
| Record besar untuk ekspor mendatang | `cursor()/lazyById()/chunkById()` pada worker, tidak return ke Inertia |

**Bedakan:**
- `Eloquent lazy loading` (`$project->members` di loop) dapat menyebabkan N+1 dan **tidak dianjurkan** pada list.
- `lazy()` / `lazyById()` untuk processing dataset besar adalah streaming/chunking **yang berguna di job**, bukan teknik menampilkan semua baris browser.
- `Inertia lazy/optional/deferred props` berguna mengurangi payload/tunggu pada tab/chart yang belum dibutuhkan; tidak menggantikan eager-loading query di server.

## 5. Deteksi N+1

Di environment non-production aktifkan prevent lazy loading:

```php
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    Model::preventLazyLoading(! app()->isProduction());
}
```

Di produksi kumpulkan slow query metrics melalui profiler/APM; jangan mengaktifkan exception yang memutus user traffic tanpa strategi.

### Checklist diagnosis

- Query count dan total SQL time per halaman & endpoint.
- P95 non-AI latency, memory php-fpm, MySQL connections, Redis latency.
- EXPLAIN untuk list proposal, project scope, join kompetensi, dashboard.
- Audit apakah eager-loaded data terlalu besar (overfetching) walau N+1 tidak ada.
- Lint/test assertion pencegahan query yang scale linear terhadap rows (`N+1`).

## 6. Indeks prioritas

| Query | Rekomendasi indeks |
| --- | --- |
| Proposals filter batch/status + terbaru | `projects(batch_id, proposal_status, updated_at)` |
| My proposals | `projects(proposer_id, proposal_status)` |
| My team memberships | `project_members(user_id, status, project_id)` |
| Lookup team membership | `UNIQUE(project_id, user_id)` |
| Steps by project | `UNIQUE(qcc_steps(project_id, step_number))` |
| Member skill search | `member_competencies(competency_id, verified_level, user_id)` dan `UNIQUE(user_id,competency_id)` |
| Reports by project/periode | `biweekly_reports(project_id, period_no)` |
| Overdue action per PIC | `action_items(pic_id, status, due_date)` |
| Evidence by project/status | `project_evidence(project_id, scan_status, uploaded_at)` |
| Scoring history | `scoring_runs(project_id, status, created_at)` |
| Audit subject timeline | `audit_logs(subject_type, subject_id, occurred_at)` |

Pastikan indeks FK tidak redundan dengan komposit; ukur cardinality & selectivity sebelum menambah indeks. `LIKE '%kata%'` tidak memakai B-tree secara efektif pada prefix wildcard; untuk pencarian isi teks kompleks pertimbangkan FULLTEXT atau search service setelah profiling.

## 7. Advanced team matching candidate query

- Ambil candidates lewat `organization` aktif, `availability` valid, `member_competencies` yang diperlukan, verified score bias.
- `whereHas('competencies', ...)` atau join terukur + bounded top N. Jangan kirim semua karyawan perusahaan ke OpenAI.
- Index `member_competencies(competency_id, user_id)`, organization/availability; query `EXPLAIN` pada data realistis.
- Hindari query satu per kandidat untuk organisasi/skills; gunakan eager loading selected columns untuk candidate shortlist.
- Simpan snapshot candidate IDs & competence levels untuk reproducibility matching.

## 8. Cache strategy

- Master `organizations`, `competency_groups`, `competency_master`: cache key berversi, TTL 30–60 menit **contoh**; bust pada CRUD.
- Aggregates dashboard: TTL 30–120 detik sebagai baseline, invalidation saat proposal/step/scoring state berubah.
- Per-user cache key harus menyertakan user scope/role/assignment version; jangan cache scoring di key global.
- Redis locking/singleflight untuk mencegah cache stampede pada dashboard besar.
- Jangan cache izin secara stale setelah revoked; revalidate protected endpoints.

## 9. Inertia React performance

- `deferred props` untuk chart/history besar; paginated props minimal untuk first paint.
- Gunakan URL query param sebagai source-of-truth filter; debounce pencarian 350–500ms, cancel stale requests.
- `preserveState`, `preserveScroll` pada filter/submit yang relevan; hindari reload semua props.
- Lazy load dialog PDF viewer/chart yang jarang dibuka; bundle code-splitting per page.
- Render 25–50 rows/table, no huge nested forms. For long list, server paging is primary; virtualization bila ada proven need.
- Upload progressive XHR/fetch with progress; large files tidak dimasukkan ke JSON props.

## 10. Testing dan budget awal

| Endpoint/jenis | Kriteria yang diukur |
| --- | --- |
| List proposal / index | P95 latency, SQL count, rows returned, DB CPU |
| Profile/competency | Payload size, query count, cache hit |
| Team confirm | concurrent correctness, lock time, throughput |
| 8 Step detail | eager relation query count, evidence list size |
| Owner dashboard | P95 <3s BRD target, cache invalidation |
| AI enqueue endpoint | HTTP cepat, job queue wait time terpisah |
| File evidence 10MB/50MB | throughput, memory, timeout, virus scan, background work |

Production latency SLO tetap butuh hardware, data volume & load scenario disetujui. Rekam baseline sebelum dan sesudah tuning, jangan menyatakan berhasil tanpa hasil load/EXPLAIN.
