<?php

namespace App\Services\Scoring\Contracts;

interface EvidenceResolver
{
    /**
     * Evidence ready for scoring: accepted files with extracted text, or an
     * AI conversation link's user-provided summary. URLs are never fetched.
     *
     * @return list<array{evidence_id: int, title: string, content: string}>
     */
    public function readyEvidenceFor(int $projectId): array;
}
