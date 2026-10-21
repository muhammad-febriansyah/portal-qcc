<?php

namespace App\Actions\Scoring;

use App\Enums\ScoringStatus;
use App\Jobs\RunAiScoring;
use App\Models\RubricVersion;
use App\Models\ScoringRun;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StartScoringRun
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Queue an AI scoring run. Calling it again with the same idempotency key
     * returns the same run and never queues a second job.
     */
    public function handle(int $projectId, RubricVersion $version, User $requestedBy, ?string $idempotencyKey = null): ScoringRun
    {
        Gate::forUser($requestedBy)->authorize('run', ScoringRun::class);

        if (! DB::table('projects')->where('id', $projectId)->exists()) {
            throw ValidationException::withMessages(['project_id' => 'Proyek tidak ditemukan.']);
        }

        $version->loadMissing('criteria');

        if ($version->publication_status !== 'published') {
            throw ValidationException::withMessages(['rubric_version_id' => 'Hanya rubrik yang sudah dipublikasikan yang dapat dipakai.']);
        }

        if (abs((float) $version->criteria->sum('weight_pct') - 100) > 0.001) {
            throw ValidationException::withMessages(['rubric_version_id' => 'Total bobot rubrik harus tepat 100%.']);
        }

        $key = $idempotencyKey ?? sprintf('project:%d:rubric:%d:%s', $projectId, $version->id, Str::uuid());

        $existing = ScoringRun::query()->where('idempotency_key', $key)->first();

        if ($existing !== null) {
            return $existing;
        }

        $activeStatuses = [
            ScoringStatus::EvidenceCheck,
            ScoringStatus::ReadyForScoring,
            ScoringStatus::AiScoring,
            ScoringStatus::PendingReview,
        ];

        $hasActiveRun = ScoringRun::query()
            ->where('project_id', $projectId)
            ->where('rubric_version_id', $version->id)
            ->whereIn('status', array_map(fn (ScoringStatus $status): string => $status->value, $activeStatuses))
            ->exists();

        if ($hasActiveRun) {
            throw ValidationException::withMessages(['project_id' => 'Masih ada penilaian yang berjalan atau menunggu review untuk proyek dan rubrik ini.']);
        }

        $run = ScoringRun::query()->create([
            'project_id' => $projectId,
            'rubric_version_id' => $version->id,
            'triggered_by' => $requestedBy->id,
            'status' => ScoringStatus::EvidenceCheck,
            'idempotency_key' => $key,
        ]);

        $this->audit->record('scoring.run_requested', $run, subjectLabel: "Penilaian proyek #{$projectId}");

        RunAiScoring::dispatch($run->id)->onQueue((string) config('scoring.queue'))->afterCommit();

        return $run;
    }
}
