<?php

namespace App\Services\Scoring\Exceptions;

use RuntimeException;

/**
 * The scoring model could not be reached or refused the request.
 * `retryable` tells the job whether trying again can help.
 */
class ScoringUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = true, public readonly string $errorCode = 'ai_unavailable')
    {
        parent::__construct($message);
    }
}
