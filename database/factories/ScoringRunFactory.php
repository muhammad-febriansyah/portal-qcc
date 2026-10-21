<?php

namespace Database\Factories;

use App\Enums\ScoringStatus;
use App\Models\ScoringRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ScoringRun>
 */
class ScoringRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => ScoringStatus::EvidenceCheck,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
