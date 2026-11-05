<?php

namespace App\Http\Controllers;

use App\Actions\Proposals\InspectProposalAttachment;
use App\Enums\BatchStatus;
use App\Enums\CompetencyLevel;
use App\Enums\ProposalStatus;
use App\Enums\QccStep;
use App\Http\Requests\SaveProposalDraftRequest;
use App\Http\Requests\UploadProposalAttachmentsRequest;
use App\Models\Competency;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\QccBatch;
use App\Services\EvidenceFileInspector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProposalWizardController extends Controller
{
    public function create(Request $request): Response
    {
        Gate::authorize('create', Project::class);

        return Inertia::render('proposals/wizard', [
            'project' => null,
            'step' => 1,
            'qccSteps' => QccStep::catalog(),
            'batches' => $this->batchOptions(),
            'competencyOptions' => $this->competencyOptions(),
            'levelOptions' => CompetencyLevel::options(),
            'attachmentLimit' => [
                'maxFiles' => (int) config('qcc.attachments.max_files'),
                'maxSizeMb' => (int) config('qcc.attachments.max_size_mb'),
            ],
            'drafts' => $this->draftOptions($request),
        ]);
    }

    public function store(SaveProposalDraftRequest $request): RedirectResponse
    {
        $data = $this->draftAttributes($request->validated());
        $advance = (bool) $data['advance'];
        $step = (int) $data['wizard_step'];
        unset($data['advance'], $data['wizard_step']);

        if ($step !== 1) {
            throw ValidationException::withMessages([
                'wizard_step' => 'Proposal baru harus dimulai dari langkah pertama.',
            ]);
        }

        $project = new Project;
        $project->forceFill([
            ...$data,
            'proposer_id' => $request->user()->id,
            'pic_id' => $request->user()->id,
            'proposal_status' => 'DRAFT',
            'wizard_step' => $advance ? min($step + 1, SaveProposalDraftRequest::LAST_STEP) : $step,
        ]);
        $project->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft proposal berhasil disimpan.']);

        return to_route('proposals.edit', $project);
    }

    public function edit(Request $request, Project $project): Response
    {
        Gate::authorize('update', $project);
        abort_unless(in_array($project->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true), 404);

        $project->load([
            'batch:id,name',
            'attachments' => fn ($query) => $query->latest('created_at'),
        ]);

        return Inertia::render('proposals/wizard', [
            'project' => [
                'id' => $project->id,
                'batch_id' => $project->batch_id,
                'title' => $project->title,
                'theme' => $project->theme,
                'background' => $project->background,
                'current_situation' => $project->current_situation,
                'improvement_plan' => $project->improvement_plan,
                'target' => $project->target,
                'scope' => $project->scope,
                'supplementary_information' => $project->supplementary_information,
                'cost_projection' => $project->cost_projection,
                'learning_goals' => $project->learning_goals_json ?? ['', '', ''],
                'step_timeline' => $this->timelineDates($project),
                'wizard_step' => $project->wizard_step,
                'updated_at' => $project->updated_at?->toIso8601String(),
                'can_submit' => $request->user()?->can('submit', $project) ?? false,
                'attachments' => $project->attachments->map(fn (ProjectAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'scan_status' => $attachment->scan_status,
                    'preview_url' => $attachment->scan_status === 'PASSED'
                        ? route('proposals.attachments.preview', [$project, $attachment])
                        : null,
                ]),
            ],
            'step' => $project->wizard_step,
            'qccSteps' => QccStep::catalog(),
            'batches' => $this->batchOptions($project->batch_id),
            'competencyOptions' => $this->competencyOptions(),
            'requirements' => $project->requirements()->orderBy('competency_id')->get(['competency_id', 'minimum_level'])->map(fn ($requirement): array => [
                'competency_id' => $requirement->competency_id,
                'minimum_level' => $requirement->minimum_level,
            ])->all(),
            'levelOptions' => CompetencyLevel::options(),
            'attachmentLimit' => [
                'maxFiles' => (int) config('qcc.attachments.max_files'),
                'maxSizeMb' => (int) config('qcc.attachments.max_size_mb'),
            ],
            'drafts' => $this->draftOptions(request()),
        ]);
    }

    public function update(SaveProposalDraftRequest $request, Project $project): RedirectResponse
    {
        $data = $this->draftAttributes($request->validated());
        $advance = (bool) $data['advance'];
        $step = (int) $data['wizard_step'];
        unset($data['advance'], $data['wizard_step']);

        DB::transaction(function () use ($project, $data, $advance, $step): void {
            $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);
            abort_unless(in_array($lockedProject->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true), 404);

            if ($advance && $step > $lockedProject->wizard_step) {
                throw ValidationException::withMessages([
                    'wizard_step' => 'Selesaikan langkah sebelumnya sebelum melanjutkan.',
                ]);
            }

            $lockedProject->forceFill([
                ...$data,
                'wizard_step' => $advance
                    ? max($lockedProject->wizard_step, min($step + 1, SaveProposalDraftRequest::LAST_STEP))
                    : $lockedProject->wizard_step,
            ])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft proposal berhasil disimpan.']);

        return to_route('proposals.edit', $project);
    }

    public function storeAttachments(UploadProposalAttachmentsRequest $request, Project $project, EvidenceFileInspector $inspector): RedirectResponse
    {
        foreach ($request->file('files', []) as $file) {
            $inspection = $inspector->inspectUpload($file, strtolower($file->getClientOriginalExtension()));

            if (! $inspection['valid']) {
                throw ValidationException::withMessages(['files' => $inspection['error'] ?? 'Format lampiran tidak valid.']);
            }
        }

        $storedPaths = [];

        try {
            DB::transaction(function () use ($request, $project, &$storedPaths): void {
                $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);
                abort_unless(in_array($lockedProject->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true), 404);

                foreach ($request->file('files', []) as $file) {
                    $path = $file->store('qcc/proposals/'.$lockedProject->id, 'local');

                    if (! is_string($path)) {
                        throw ValidationException::withMessages(['files' => 'Lampiran gagal disimpan. Coba unggah kembali.']);
                    }

                    $storedPaths[] = $path;
                    $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));

                    ProjectAttachment::query()->create([
                        'project_id' => $lockedProject->id,
                        'uploaded_by' => $request->user()->id,
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => Str::limit($originalName, 255, ''),
                        'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                        'size_bytes' => $file->getSize(),
                        'scan_status' => 'PASSED',
                    ]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lampiran proposal berhasil diunggah.']);

        return to_route('proposals.edit', $project);
    }

    public function destroyAttachment(Project $project, ProjectAttachment $attachment): RedirectResponse
    {
        Gate::authorize('update', $project);
        abort_unless($attachment->project_id === $project->id, 404);

        DB::transaction(function () use ($project, $attachment): void {
            $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);
            abort_unless(in_array($lockedProject->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true), 404);

            $attachment->delete();
        });

        Storage::disk($attachment->disk)->delete($attachment->path);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lampiran berhasil dihapus.']);

        return to_route('proposals.edit', $project);
    }

    public function previewAttachment(Project $project, ProjectAttachment $attachment, InspectProposalAttachment $inspector): mixed
    {
        Gate::authorize('view', $project);
        abort_unless($attachment->project_id === $project->id, 404);
        abort_unless($inspector->handle($attachment) === 'PASSED', 404);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return response()->stream(function () use ($disk, $attachment): void {
            $stream = $disk->readStream($attachment->path);

            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => "inline; filename*=UTF-8''".rawurlencode($attachment->original_name),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none';",
        ]);
    }

    /** @return list<array{id: int, name: string}> */
    private function batchOptions(?int $includeBatchId = null): array
    {
        return array_values(QccBatch::query()
            ->where(function ($query) use ($includeBatchId): void {
                $query->where('status', BatchStatus::Active->value);

                if ($includeBatchId !== null) {
                    $query->orWhereKey($includeBatchId);
                }
            })
            ->orderBy('starts_on', 'desc')
            ->get(['id', 'name'])
            ->map(fn (QccBatch $batch): array => ['id' => $batch->id, 'name' => $batch->name])
            ->all());
    }

    /** @return list<array{id: int, code: string, name: string, label: string}> */
    private function competencyOptions(): array
    {
        return array_values(Competency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Competency $competency): array => [
                'id' => $competency->id,
                'code' => $competency->code,
                'name' => $competency->name,
                'label' => $competency->code.' - '.$competency->name,
            ])
            ->all());
    }

    /** @return list<array{id: int, title: string, batch: string, wizard_step: int, updated_at: string|null}> */
    private function draftOptions(Request $request): array
    {
        return array_values(Project::query()
            ->with('batch:id,name')
            ->where('proposer_id', $request->user()->id)
            ->where('proposal_status', 'DRAFT')
            ->latest('updated_at')
            ->limit(5)
            ->get(['id', 'batch_id', 'title', 'wizard_step', 'updated_at'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'title' => $project->title,
                'batch' => $project->batch->name,
                'wizard_step' => $project->wizard_step,
                'updated_at' => $project->updated_at?->toIso8601String(),
            ])
            ->all());
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function draftAttributes(array $validated): array
    {
        $learningGoals = $validated['learning_goals'] ?? null;
        $timeline = $validated['step_timeline'] ?? null;
        unset($validated['learning_goals'], $validated['step_timeline']);

        $validated['learning_goals_json'] = is_array($learningGoals)
            ? array_values($learningGoals)
            : null;

        $validated['step_timeline_json'] = is_array($timeline)
            ? array_map(fn (QccStep $step): array => [
                'step_number' => $step->value,
                'target_date' => $timeline[$step->value - 1] ?? null,
            ], QccStep::cases())
            : null;

        return $validated;
    }

    /**
     * Target date per QCC step, in step order, for the wizard form.
     *
     * @return list<string>
     */
    private function timelineDates(Project $project): array
    {
        $dates = collect($project->step_timeline_json ?? [])->pluck('target_date', 'step_number');

        return array_map(fn (QccStep $step): string => (string) ($dates->get($step->value) ?? ''), QccStep::cases());
    }
}
