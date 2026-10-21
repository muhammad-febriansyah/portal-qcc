<?php

namespace App\Services\Scoring;

use App\Models\RubricCriterion;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Deterministic weighted total. The model never computes the total.
 *
 * weighted = sum(score * weight_pct / 100), rounded half up to two decimals.
 * Integer arithmetic in hundredths avoids floating point drift.
 */
class WeightedScoringCalculator
{
    /**
     * @param  Collection<int, RubricCriterion>  $criteria
     * @param  array<int, float|int|string>  $scores  keyed by rubric criterion id
     * @return string decimal with two digits, for example "82.50"
     */
    public function calculate(Collection $criteria, array $scores): string
    {
        if ($criteria->isEmpty()) {
            throw new InvalidArgumentException('Rubrik tidak memiliki kriteria.');
        }

        $weightTotal = 0;
        $sum = 0;

        foreach ($criteria as $criterion) {
            if (! array_key_exists($criterion->id, $scores)) {
                throw new InvalidArgumentException("Nilai untuk kriteria {$criterion->code} belum ada.");
            }

            $score = $this->toHundredths($scores[$criterion->id]);
            $weight = $this->toHundredths($criterion->weight_pct);

            if ($score < 0 || $score > 10000) {
                throw new InvalidArgumentException("Nilai kriteria {$criterion->code} harus antara 0 dan 100.");
            }

            $weightTotal += $weight;
            $sum += $score * $weight;
        }

        if ($weightTotal !== 10000) {
            throw new InvalidArgumentException('Total bobot rubrik harus tepat 100%.');
        }

        $hundredths = intdiv($sum + 5000, 10000);

        return sprintf('%d.%02d', intdiv($hundredths, 100), $hundredths % 100);
    }

    private function toHundredths(float|int|string $value): int
    {
        return (int) round(((float) $value) * 100);
    }
}
