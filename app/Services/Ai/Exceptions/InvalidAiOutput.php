<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

/**
 * The model answered, but the answer breaks the contract the backend expects.
 */
class InvalidAiOutput extends RuntimeException
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct('Hasil AI tidak memenuhi kontrak: '.implode('; ', array_slice($problems, 0, 5)));
    }
}
