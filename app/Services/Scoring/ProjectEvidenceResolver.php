<?php

namespace App\Services\Scoring;

use App\Models\ProjectEvidence;
use App\Services\Scoring\Contracts\EvidenceResolver;
use Illuminate\Support\Facades\Storage;

class ProjectEvidenceResolver implements EvidenceResolver
{
    /** @return list<array{evidence_id: int, title: string, content: string}> */
    public function readyEvidenceFor(int $projectId): array
    {
        return array_values(ProjectEvidence::query()
            ->where('project_id', $projectId)
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('source_type', 'FILE')
                        ->where('scan_status', 'PASSED')
                        ->where('processing_status', 'READY')
                        ->where('extraction_status', 'READY')
                        ->whereNotNull('extracted_text_path');
                })->orWhere(function ($query): void {
                    $query->where('source_type', 'AI_CHAT_LINK')
                        ->whereNotNull('summary');
                });
            })
            ->orderBy('id')
            ->get()
            ->map(function (ProjectEvidence $evidence): ?array {
                if ($evidence->source_type === 'AI_CHAT_LINK') {
                    $summary = trim((string) $evidence->summary);

                    return $summary === '' ? null : [
                        'evidence_id' => $evidence->id,
                        'title' => $evidence->title,
                        'content' => $summary,
                    ];
                }

                if ($evidence->storage_disk === null || $evidence->extracted_text_path === null) {
                    return null;
                }

                $disk = Storage::disk($evidence->storage_disk);

                if (! $disk->exists($evidence->extracted_text_path)) {
                    return null;
                }

                $content = trim($disk->get($evidence->extracted_text_path));

                return $content === '' ? null : [
                    'evidence_id' => $evidence->id,
                    'title' => $evidence->title,
                    'content' => $content,
                ];
            })
            ->filter()
            ->values()
            ->all());
    }
}
