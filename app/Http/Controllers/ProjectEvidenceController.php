<?php

namespace App\Http\Controllers;

use App\Actions\Evidence\StoreProjectEvidence;
use App\Enums\ProposalStatus;
use App\Http\Requests\UploadProjectEvidenceRequest;
use App\Models\BiweeklyReport;
use App\Models\EvidenceLink;
use App\Models\MentoringReport;
use App\Models\Project;
use App\Models\ProjectEvidence;
use App\Models\ProjectStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ProjectEvidenceController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        Gate::authorize('viewEvidence', $project);
        abort_unless($project->proposal_status === ProposalStatus::Approved, 404);

        $selectedStep = $request->integer('step') ?: null;

        if ($selectedStep !== null) {
            $project->steps()->where('step_number', $selectedStep)->firstOrFail();
        }

        $search = $request->string('q')->trim()->value();
        $evidence = $project->evidence()
            ->with(['uploader:id,name', 'links.target'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('original_name', 'like', '%'.$search.'%');
                });
            })
            ->latest('id')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (ProjectEvidence $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'category' => $item->category,
                'description' => $item->description,
                'source_type' => $item->source_type,
                'url' => $item->url,
                'summary' => $item->summary,
                'original_name' => $item->original_name,
                'mime_type' => $item->mime_type,
                'size_bytes' => $item->size_bytes,
                'version_number' => $item->version_number,
                'scan_status' => $item->scan_status,
                'processing_status' => $item->processing_status,
                'extraction_status' => $item->extraction_status,
                'uploader' => $item->uploader?->name,
                'uploaded_at' => $item->created_at === null ? null : Carbon::parse($item->created_at)->toIso8601String(),
                'targets' => $item->links->map(fn (EvidenceLink $link): string => $this->targetLabel($link))->values()->all(),
                'preview_url' => $item->source_type === 'FILE' && $this->supportsPreview($item) && $item->scan_status === 'PASSED'
                    ? route('projects.evidence.preview', [$project, $item])
                    : null,
                'thumbnail_url' => $item->source_type === 'FILE' && $item->thumbnail_path !== null && $item->scan_status === 'PASSED'
                    ? route('projects.evidence.thumbnail', [$project, $item])
                    : null,
                'download_url' => $item->source_type === 'FILE' && $item->scan_status === 'PASSED'
                    ? route('projects.evidence.download', [$project, $item])
                    : null,
            ]);

        return Inertia::render('projects/evidence', [
            'project' => [
                'id' => $project->id,
                'code' => sprintf('PRP-%04d', $project->id),
                'title' => $project->title,
            ],
            'evidence' => $evidence,
            'steps' => $project->steps()->get(['id', 'step_number'])->map(fn (ProjectStep $step): array => [
                'id' => $step->id,
                'number' => $step->step_number,
                'name' => $step->definition()->label(),
            ])->all(),
            'reports' => $project->biweeklyReports()->get(['id', 'period_no'])->map(fn (BiweeklyReport $report): array => [
                'id' => $report->id,
                'type' => 'BIWEEKLY_REPORT',
                'label' => 'Laporan bi-weekly #'.$report->period_no,
            ])->concat($project->mentoringReports()->get(['id', 'session_at'])->map(fn (MentoringReport $report): array => $this->mentoringReportOption($report)))->values()->all(),
            'selected_step' => $selectedStep,
            'can_upload' => $request->user()?->can('uploadEvidence', $project) ?? false,
            'search' => $search,
            'max_file_size_bytes' => (int) config('qcc_evidence.max_file_size_kb') * 1024,
            'accept' => '.pdf,.jpg,.jpeg,.png,.docx,.xlsx',
        ]);
    }

    public function store(UploadProjectEvidenceRequest $request, Project $project, StoreProjectEvidence $store): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 401);
        $evidence = $store->handle($project, $actor, $request->evidenceData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $evidence->source_type === 'FILE'
                ? 'Evidence tersimpan. Pemeriksaan file berjalan di antrean.'
                : 'Tautan evidence berhasil disimpan.',
        ]);

        return back()->with('evidence_id', $evidence->getKey());
    }

    public function preview(Project $project, ProjectEvidence $evidence): mixed
    {
        Gate::authorize('viewEvidence', $project);
        $this->authorizeEvidence($project, $evidence);
        abort_unless($evidence->scan_status === 'PASSED' && $this->supportsPreview($evidence), 404);

        $mimeType = $evidence->mime_type;
        $filename = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $evidence->original_name);

        return $this->streamPrivateFile($evidence, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => $filename,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function thumbnail(Project $project, ProjectEvidence $evidence): mixed
    {
        Gate::authorize('viewEvidence', $project);
        $this->authorizeEvidence($project, $evidence);
        abort_unless($evidence->scan_status === 'PASSED' && $evidence->thumbnail_path !== null, 404);

        return $this->streamPrivateFile($evidence, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], $evidence->thumbnail_path);
    }

    public function download(Project $project, ProjectEvidence $evidence): mixed
    {
        Gate::authorize('viewEvidence', $project);
        $this->authorizeEvidence($project, $evidence);
        abort_unless($evidence->scan_status === 'PASSED', 404);

        return $this->streamPrivateFile($evidence, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $evidence->original_name),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeEvidence(Project $project, ProjectEvidence $evidence): void
    {
        abort_unless($evidence->project_id === $project->id, 404);
    }

    /** @return array{id: int, type: string, label: string} */
    private function mentoringReportOption(MentoringReport $report): array
    {
        $date = Carbon::parse($report->session_at);
        $date->locale('id');

        return [
            'id' => $report->id,
            'type' => 'MENTORING_REPORT',
            'label' => 'Sesi mentoring · '.$date->translatedFormat('d M Y'),
        ];
    }

    private function supportsPreview(ProjectEvidence $evidence): bool
    {
        return in_array($evidence->file_extension, ['pdf', 'jpg', 'jpeg', 'png'], true);
    }

    private function targetLabel(EvidenceLink $link): string
    {
        return match (true) {
            $link->target instanceof Project => 'Proyek',
            $link->target instanceof ProjectStep => 'Step '.$link->target->step_number.' · '.$link->target->definition()->label(),
            $link->target instanceof BiweeklyReport => 'Laporan bi-weekly #'.$link->target->period_no,
            $link->target instanceof MentoringReport => 'Laporan mentoring',
            default => 'Target proyek',
        };
    }

    /** @param array<string, string> $headers */
    private function streamPrivateFile(ProjectEvidence $evidence, array $headers, ?string $path = null): mixed
    {
        $stream = Storage::disk($evidence->storage_disk)->readStream($path ?? $evidence->storage_key);
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, $headers);
    }
}
