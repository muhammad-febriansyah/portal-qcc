<?php

namespace App\Services\Teams\Matching;

use App\Services\Ai\Exceptions\AiUnavailable;
use App\Services\Ai\Exceptions\InvalidAiOutput;

interface TeamMatchingClient
{
    /**
     * @param  array<string, mixed>  $request  built by TeamMatchingRequestBuilder
     * @return array{payload: array<string, mixed>, usage: array<string, mixed>, model: string}
     *
     * @throws AiUnavailable
     * @throws InvalidAiOutput
     */
    public function suggest(array $request): array;
}
