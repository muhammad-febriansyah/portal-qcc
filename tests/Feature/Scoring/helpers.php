<?php

use App\Models\QccBatch;
use App\Models\RubricCriterion;
use App\Models\RubricSet;
use App\Models\RubricVersion;
use App\Models\User;
use App\Services\Scoring\Contracts\EvidenceResolver;
use App\Services\Scoring\Contracts\ScoringModelClient;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<int|float>  $weights
 */
function scoringRubric(array $weights = [30, 35, 35], string $status = 'published'): RubricVersion
{
    $set = RubricSet::query()->create(['scoring_type' => 'proposal-'.uniqid(), 'name' => 'Rubrik uji']);
    $version = RubricVersion::query()->create([
        'rubric_set_id' => $set->id,
        'version_number' => 1,
        'publication_status' => 'draft',
    ]);

    foreach ($weights as $index => $weight) {
        RubricCriterion::query()->create([
            'rubric_version_id' => $version->id,
            'code' => 'K'.($index + 1),
            'name' => 'Kriteria '.($index + 1),
            'description' => 'Deskripsi kriteria '.($index + 1),
            'weight_pct' => $weight,
            'position' => $index + 1,
        ]);
    }

    if ($status === 'published') {
        $version->update(['publication_status' => 'published']);
    }

    return $version->load('criteria');
}

function scoringProjectId(): int
{
    return DB::table('projects')->insertGetId([
        'batch_id' => QccBatch::factory()->create()->id,
        'proposer_id' => User::factory()->create()->id,
        'title' => 'Proyek uji',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @param  list<int>  $evidenceIds
 * @return array<string, mixed>
 */
function aiPayload(RubricVersion $version, array $evidenceIds = [7], int|float $score = 80): array
{
    return [
        'overall_summary' => 'Ringkasan penilaian.',
        'cautions' => [],
        'criteria' => $version->criteria->map(fn (RubricCriterion $criterion): array => [
            'criterion_code' => $criterion->code,
            'score' => $score,
            'rationale' => 'Alasan untuk '.$criterion->code,
            'evidence_references' => array_map(fn (int $id): array => ['evidence_id' => $id, 'locator' => 'halaman 1'], $evidenceIds),
            'gaps' => [],
            'uncertainty' => null,
        ])->values()->all(),
    ];
}

/**
 * @param  list<array{evidence_id: int, title: string, content: string}>  $items
 */
function fakeEvidence(array $items): void
{
    app()->instance(EvidenceResolver::class, new class($items) implements EvidenceResolver
    {
        /** @param list<array{evidence_id: int, title: string, content: string}> $items */
        public function __construct(private array $items) {}

        public function readyEvidenceFor(int $projectId): array
        {
            return $this->items;
        }
    });
}

function oneEvidence(): array
{
    return [['evidence_id' => 7, 'title' => 'Laporan Step 1', 'content' => 'Isi laporan.']];
}

/**
 * @param  array<string, mixed>|Throwable  $answer
 */
function fakeModel(array|Throwable $answer): object
{
    $client = new class($answer) implements ScoringModelClient
    {
        public int $calls = 0;

        /** @var array<string, mixed>|null */
        public ?array $lastRequest = null;

        /** @param array<string, mixed>|Throwable $answer */
        public function __construct(private array|Throwable $answer) {}

        public function score(array $request): array
        {
            $this->calls++;
            $this->lastRequest = $request;

            if ($this->answer instanceof Throwable) {
                throw $this->answer;
            }

            return ['payload' => $this->answer, 'usage' => ['input_tokens' => 100, 'output_tokens' => 50], 'model' => 'fake-model'];
        }
    };

    app()->instance(ScoringModelClient::class, $client);

    return $client;
}

function userWith(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}
