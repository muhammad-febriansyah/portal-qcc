<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

/**
 * The AI provider is not set up, could not be reached or refused the request.
 * `retryable` tells a job whether trying again can help.
 */
class AiUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = true, public readonly string $errorCode = 'ai_unavailable')
    {
        parent::__construct($message);
    }
}
