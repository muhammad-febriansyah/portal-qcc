<?php

namespace App\Services\Scoring\Exceptions;

use RuntimeException;

/**
 * The model answered, but the answer does not satisfy the scoring contract.
 */
class InvalidScoringResult extends RuntimeException
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct('Hasil AI tidak memenuhi kontrak penilaian: '.implode('; ', array_slice($problems, 0, 5)));
    }
}
