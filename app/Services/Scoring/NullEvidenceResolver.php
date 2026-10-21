<?php

namespace App\Services\Scoring;

use App\Services\Scoring\Contracts\EvidenceResolver;

/**
 * Placeholder until the evidence module exists: no evidence is ever ready,
 * so every run ends as "insufficient evidence" instead of scoring empty data.
 */
class NullEvidenceResolver implements EvidenceResolver
{
    public function readyEvidenceFor(int $projectId): array
    {
        return [];
    }
}
