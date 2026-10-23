<?php

namespace App\Actions\Evidence;

use App\Enums\ProposalStatus;
use App\Jobs\InspectProjectEvidence;
use App\Models\BiweeklyReport;
use App\Models\MentoringReport;
use App\Models\Project;
use App\Models\ProjectEvidence;
use App\Models\ProjectStep;
use App\Models\User;
use App\Services\EvidenceFileInspector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreProjectEvidence
{
    public function __construct(private readonly EvidenceFileInspector $inspector) {}

    /** @param array{title: string, category: string, description?: ?string, source_type?: string, url?: ?string, summary?: ?string, target_type: string, target_id: int, file?: ?UploadedFile} $data */
    public function handle(Project $project, User $actor, array $data): ProjectEvidence
    {
        if ($project->proposal_status !== ProposalStatus::Approved) {
            throw ValidationException::withMessages(['project' => 'Evidence proyek tersedia setelah proposal disetujui.']);
        }

        $sourceType = $data['source_type'] ?? 'FILE';
        $file = $data['file'] ?? null;
        $diskName = null;
        $storedPath = null;
        $extension = null;
        $safeOriginalName = null;
        $inspection = null;

        if ($sourceType === 'FILE') {
            if (! $file instanceof UploadedFile) {
                throw ValidationException::withMessages(['file' => 'File evidence wajib diunggah.']);
            }

            $extension = strtolower($file->getClientOriginalExtension());

            if (! in_array($extension, config('qcc_evidence.allowed_extensions'), true)) {
                throw ValidationException::withMessages(['file' => 'Format file tidak didukung.']);
            }

            $inspection = $this->inspector->inspectUpload($file, $extension);

            if (! $inspection['valid']) {
                throw ValidationException::withMessages(['file' => $inspection['error']]);
            }

            $diskName = (string) config('qcc_evidence.disk');
            $storageKey = 'projects/'.$project->getKey().'/'.Str::uuid().'.'.$extension;
            $safeOriginalName = Str::of(basename(str_replace('\\', '/', $file->getClientOriginalName())))
                ->replaceMatches('/[\x00-\x1F\x7F]/u', '')
                ->trim()
                ->limit(255, '')
                ->value();
        }

        try {
            if ($file instanceof UploadedFile && is_string($storageKey ?? null) && is_string($diskName)) {
                $storedPath = $file->storeAs(dirname($storageKey), basename($storageKey), $diskName);

                if ($storedPath === false) {
                    throw new \RuntimeException('File evidence gagal disimpan.');
                }
            }

            return DB::transaction(function () use ($project, $actor, $data, $sourceType, $diskName, $storedPath, $safeOriginalName, $extension, $file, $inspection): ProjectEvidence {
                $lockedProject = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

                if ($lockedProject->proposal_status !== ProposalStatus::Approved) {
                    throw ValidationException::withMessages(['project' => 'Evidence proyek tersedia setelah proposal disetujui.']);
                }

                $latestVersion = ProjectEvidence::query()
                    ->where('project_id', $lockedProject->getKey())
                    ->where('title', $data['title'])
                    ->where('category', $data['category'])
                    ->latest('version_number')
                    ->lockForUpdate()
                    ->first();
                $versionGroup = $latestVersion->version_group ?? (string) Str::uuid();
                $target = $this->target($lockedProject, $data['target_type'], (int) $data['target_id']);

                $evidence = ProjectEvidence::query()->create([
                    'project_id' => $lockedProject->getKey(),
                    'uploaded_by' => $actor->getKey(),
                    'title' => $data['title'],
                    'category' => $data['category'],
                    'description' => $data['description'] ?? null,
                    'source_type' => $sourceType,
                    'url' => $data['url'] ?? null,
                    'summary' => $data['summary'] ?? null,
                    'storage_disk' => $diskName,
                    'storage_key' => $storedPath,
                    'original_name' => $safeOriginalName,
                    'file_extension' => $extension,
                    'mime_type' => $file instanceof UploadedFile ? ($file->getMimeType() ?: 'application/octet-stream') : null,
                    'size_bytes' => $file instanceof UploadedFile ? (int) $file->getSize() : null,
                    'checksum_sha256' => $file instanceof UploadedFile ? (string) hash_file('sha256', $file->getRealPath() ?: $file->getPathname()) : null,
                    'version_group' => $versionGroup,
                    'version_number' => ($latestVersion->version_number ?? 0) + 1,
                    'supersedes_id' => $latestVersion?->getKey(),
                    'scan_status' => $sourceType === 'FILE' ? 'PENDING' : 'NOT_REQUIRED',
                    'processing_status' => $sourceType === 'FILE' ? 'PENDING' : 'NOT_REQUIRED',
                    'extraction_status' => $sourceType === 'FILE' ? 'PENDING' : 'NOT_REQUIRED',
                    'metadata_json' => $inspection['metadata'] ?? null,
                ]);

                $evidence->links()->create([
                    'target_type' => $target::class,
                    'target_id' => $target->getKey(),
                    'created_by' => $actor->getKey(),
                ]);

                if ($sourceType === 'FILE') {
                    InspectProjectEvidence::dispatch($evidence->getKey())->afterCommit();
                }

                return $evidence;
            });
        } catch (Throwable $exception) {
            $recordExists = is_string($storedPath) && is_string($diskName)
                ? ProjectEvidence::query()
                    ->where('storage_disk', $diskName)
                    ->where('storage_key', $storedPath)
                    ->exists()
                : false;

            if (! $recordExists && is_string($storedPath)) {
                Storage::disk($diskName)->delete($storedPath);
            }

            throw $exception;
        }
    }

    private function target(Project $project, string $type, int $id): Project|ProjectStep|BiweeklyReport|MentoringReport
    {
        if ($type === 'PROJECT' && $id === $project->getKey()) {
            return $project;
        }

        if ($type === 'QCC_STEP') {
            return $project->steps()->whereKey($id)->firstOrFail();
        }

        if ($type === 'BIWEEKLY_REPORT') {
            return $project->biweeklyReports()->whereKey($id)->firstOrFail();
        }

        if ($type === 'MENTORING_REPORT') {
            return $project->mentoringReports()->whereKey($id)->firstOrFail();
        }

        throw ValidationException::withMessages(['target_id' => 'Keterkaitan harus berasal dari proyek yang dipilih.']);
    }
}
