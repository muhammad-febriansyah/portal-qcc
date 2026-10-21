<?php

namespace App\Services\Scoring\Contracts;

interface EvidenceResolver
{
    /**
     * Evidence that is ready to be scored for a project: scanned, accepted and
     * owned by that project. Each item is a manifest entry the model may cite.
     *
     * @return list<array{evidence_id: int, title: string, content: string}>
     */
    public function readyEvidenceFor(int $projectId): array;
}
