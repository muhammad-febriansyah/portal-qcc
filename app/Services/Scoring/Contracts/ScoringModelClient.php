<?php

namespace App\Services\Scoring\Contracts;

use App\Services\Scoring\Exceptions\ScoringUnavailable;

interface ScoringModelClient
{
    /**
     * Ask the model to score the request.
     *
     * @param  array<string, mixed>  $request  built by ScoringRequestBuilder
     * @return array{payload: array<string, mixed>, usage: array<string, mixed>, model: string}
     *
     * @throws ScoringUnavailable
     */
    public function score(array $request): array;
}
