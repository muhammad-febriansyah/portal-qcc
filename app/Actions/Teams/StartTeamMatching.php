<?php

namespace App\Actions\Teams;

use App\Enums\TeamMatchingStatus;
use App\Jobs\RunTeamMatching;
use App\Models\Project;
use App\Models\TeamMatchingRun;
use App\Models\User;
use App\Services\Ai\AiConnection;
use App\Services\AuditService;
use App\Services\Teams\CandidateShortlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StartTeamMatching
{
    public function __construct(
        private readonly AiConnection $ai,
        private readonly CandidateShortlist $shortlist,
        private readonly AuditService $audit,
    ) {}

    /**
     * Queue a new AI suggestion for the project.
     *
     * @throws ValidationException when AI is not set up, there is nothing to match, or a run is active or too recent
     */
    public function handle(Project $project, User $requester): TeamMatchingRun
    {
        if (! $this->ai->isReady()) {
            throw ValidationException::withMessages(['ai' => AiConnection::MESSAGE_NOT_READY]);
        }

        $shortlist = $this->shortlist->for($project);

        if ($shortlist['requirements'] === []) {
            throw ValidationException::withMessages(['matching' => 'Tambahkan kebutuhan kompetensi proyek terlebih dahulu.']);
        }

        if ($shortlist['candidates'] === []) {
            throw ValidationException::withMessages(['matching' => 'Belum ada kandidat yang memenuhi kompetensi dan level minimum proyek ini.']);
        }

        $snapshot = $this->shortlist->snapshot($project, $shortlist);

        $run = DB::transaction(function () use ($project, $requester, $snapshot): TeamMatchingRun {
            Project::query()->whereKey($project->id)->lockForUpdate()->first();

            $latest = TeamMatchingRun::query()->where('project_id', $project->id)->latest('id')->first();

            if ($latest?->status->isActive()) {
                throw ValidationException::withMessages(['matching' => 'Rekomendasi sebelumnya masih diproses.']);
            }

            $cooldown = (int) config('team_matching.cooldown_seconds');

            if ($latest !== null && $latest->created_at->diffInSeconds(now(), true) < $cooldown) {
                throw ValidationException::withMessages(['matching' => 'Tunggu sebentar sebelum meminta rekomendasi baru.']);
            }

            $run = TeamMatchingRun::query()->create([
                'project_id' => $project->id,
                'requested_by' => $requester->id,
                'status' => TeamMatchingStatus::Queued,
                'idempotency_key' => (string) Str::uuid(),
                'candidate_snapshot_json' => $snapshot,
            ]);

            $this->audit->record('team_matching.requested', $run, subjectLabel: 'Proposal PRP-'.sprintf('%04d', $project->id));

            return $run;
        });

        RunTeamMatching::dispatch($run->id)->onQueue((string) config('team_matching.queue'))->afterCommit();

        return $run;
    }
}
