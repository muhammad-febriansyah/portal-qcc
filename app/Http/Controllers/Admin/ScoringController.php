<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Scoring\ReviewScoringRun;
use App\Actions\Scoring\StartScoringRun;
use App\Enums\ScoringStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\RubricVersion;
use App\Models\ScoringRun;
use App\Models\User;
use App\Services\ScoringListingQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ScoringController extends Controller
{
    public function index(Request $request, ScoringListingQuery $listing): Response
    {
        Gate::authorize('viewAny', ScoringRun::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_map(fn (ScoringStatus $status): string => $status->value, ScoringStatus::cases()))],
            'per_page' => ['nullable', Rule::in([10, 25, 50])],
            'sort' => ['nullable', Rule::in(['project_title', 'status', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $paginator = $listing->build($request->user(), $filters)
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString();

        $paginator->setCollection($paginator->getCollection()->map(fn (ScoringRun $run): array => [
            'id' => $run->id,
            'projectId' => $run->project_id,
            'projectTitle' => $run->project_title ?? "Proyek #{$run->project_id}",
            'rubricName' => $run->rubric_name ?? 'Rubrik',
            'scoringType' => $run->scoring_type ?? '',
            'version' => $run->rubricVersion?->version_number,
            'status' => $run->status->value,
            'statusLabel' => $run->status->label(),
            'score' => $run->result?->ai_weighted_score,
            'requestedBy' => $run->triggeredBy->name ?? 'Akun dihapus',
            'createdAt' => $this->formatDate($run->created_at),
        ]));

        return Inertia::render('admin/scoring/index', [
            'runs' => $paginator,
            'filters' => [
                'search' => $search,
                'status' => $filters['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => (int) ($filters['per_page'] ?? 10),
            ],
            'statusOptions' => array_map(fn (ScoringStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ], ScoringStatus::cases()),
            'canStart' => $request->user()->can('scoring.run'),
            'projects' => $request->user()->can('scoring.run')
                ? Project::query()
                    ->visibleTo($request->user())
                    ->get(['id', 'title'])
                    ->filter(fn (Project $project): bool => Gate::forUser($request->user())->allows('runScoring', $project))
                    ->map(fn (Project $project): array => ['id' => $project->id, 'title' => $project->title])
                    ->values()
                : [],
            'rubrics' => $request->user()->can('scoring.run')
                ? DB::table('rubric_versions')
                    ->join('rubric_sets', 'rubric_sets.id', '=', 'rubric_versions.rubric_set_id')
                    ->where('rubric_versions.publication_status', 'published')
                    ->orderBy('rubric_sets.name')
                    ->orderByDesc('rubric_versions.version_number')
                    ->get([
                        'rubric_versions.id',
                        'rubric_versions.version_number',
                        'rubric_sets.name',
                        'rubric_sets.scoring_type',
                    ])
                    ->map(fn (object $rubric): array => [
                        'id' => $rubric->id,
                        'label' => "{$rubric->name} · v{$rubric->version_number}",
                        'scoringType' => $rubric->scoring_type,
                    ])
                : [],
        ]);
    }

    public function store(Request $request, StartScoringRun $start): RedirectResponse
    {
        Gate::authorize('scoring.run');

        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'rubric_version_id' => ['required', 'integer', 'exists:rubric_versions,id'],
        ]);

        $project = Project::query()->whereKey((int) $validated['project_id'])->firstOrFail();
        $version = RubricVersion::query()->whereKey((int) $validated['rubric_version_id'])->firstOrFail();

        $start->handle($project->id, $version, $request->user());

        return back()->with('success', 'Scoring berhasil dimulai dan masuk antrean.');
    }

    public function show(ScoringRun $scoringRun): Response
    {
        Gate::authorize('view', $scoringRun);

        $scoringRun->load([
            'rubricVersion.rubricSet',
            'triggeredBy',
            'result.reviewer',
            'result.criterionScores.criterion',
        ]);

        $manifest = is_array($scoringRun->input_manifest_json) ? $scoringRun->input_manifest_json : [];
        $manifestItems = [];

        if (array_is_list($manifest)) {
            foreach ($manifest as $item) {
                if (is_array($item)) {
                    $manifestItems[] = $item;
                }
            }
        }
        $result = $scoringRun->result;

        return Inertia::render('admin/scoring/show', [
            'run' => [
                'id' => $scoringRun->id,
                'projectId' => $scoringRun->project_id,
                'projectTitle' => $scoringRun->project->title ?? "Proyek #{$scoringRun->project_id}",
                'status' => $scoringRun->status->value,
                'statusLabel' => $scoringRun->status->label(),
                'requestedBy' => $scoringRun->triggeredBy->name ?? 'Akun dihapus',
                'rubricName' => $scoringRun->rubricVersion->rubricSet->name ?? 'Rubrik',
                'scoringType' => $scoringRun->rubricVersion->rubricSet->scoring_type ?? '',
                'rubricVersion' => $scoringRun->rubricVersion?->version_number,
                'model' => $scoringRun->model,
                'promptVersion' => $scoringRun->prompt_version,
                'attemptCount' => $scoringRun->attempt_count,
                'createdAt' => $this->formatDate($scoringRun->created_at),
                'startedAt' => $this->formatDate($scoringRun->started_at),
                'completedAt' => $this->formatDate($scoringRun->completed_at),
                'errorSummary' => $scoringRun->error_summary,
                'canRequestRevision' => Gate::allows('review', $scoringRun),
                'canApprove' => Gate::allows('approve', $scoringRun),
                'manifestCount' => $manifest['evidence_count'] ?? count($manifestItems),
                'minimumEvidence' => $manifest['minimum'] ?? null,
                'evidence' => array_map(fn (array $item): array => [
                    'id' => $item['evidence_id'],
                    'title' => $item['title'],
                ], $manifestItems),
                'result' => $result === null ? null : [
                    'summary' => $result->overall_summary,
                    'aiScore' => $result->ai_weighted_score,
                    'reviewerScore' => $result->reviewer_weighted_score,
                    'finalScore' => $result->final_score,
                    'reviewComment' => $result->review_comment,
                    'reviewerName' => $result->reviewer?->name,
                    'reviewedAt' => $this->formatDate($result->reviewed_at),
                    'cautions' => $result->cautions_json ?? [],
                    'criteria' => $result->criterionScores
                        ->sortBy(fn ($score): int => $score->criterion->position)
                        ->values()
                        ->map(fn ($score): array => [
                            'criterionId' => $score->rubric_criterion_id,
                            'code' => $score->criterion->code,
                            'name' => $score->criterion->name,
                            'weight' => $score->criterion?->weight_pct,
                            'aiScore' => $score->ai_score,
                            'reviewerScore' => $score->reviewer_score,
                            'finalScore' => $score->final_score,
                            'rationale' => $score->rationale,
                            'gaps' => $score->gaps_json ?? [],
                            'uncertainty' => $score->uncertainty,
                            'evidenceReferences' => $score->evidence_references_json ?? [],
                        ])
                        ->all(),
                ],
            ],
        ]);
    }

    public function approve(Request $request, ScoringRun $scoringRun, ReviewScoringRun $review): RedirectResponse
    {
        Gate::authorize('approve', $scoringRun);

        $validated = $request->validate([
            'scores' => ['nullable', 'array', 'max:50'],
            'scores.*' => ['required', 'numeric', 'between:0,100'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $reviewer */
        $reviewer = $request->user();

        $review->approve(
            $scoringRun,
            $reviewer,
            $validated['scores'] ?? [],
            $validated['reason'] ?? null,
            $validated['comment'] ?? null,
        );

        return back()->with('success', 'Penilaian berhasil disetujui.');
    }

    public function requestRevision(Request $request, ScoringRun $scoringRun, ReviewScoringRun $review): RedirectResponse
    {
        Gate::authorize('review', $scoringRun);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        /** @var User $reviewer */
        $reviewer = $request->user();

        $review->requestRevision($scoringRun, $reviewer, $validated['reason']);

        return back()->with('success', 'Permintaan revisi berhasil dikirim.');
    }

    private function formatDate(CarbonImmutable|string|null $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $formattedDate = new CarbonImmutable($date);
        $formattedDate->locale('id');

        return $formattedDate->translatedFormat('d M Y, H:i');
    }
}
