<?php

namespace App\Services\Scoring;

use App\Models\RubricVersion;
use App\Services\Scoring\Exceptions\InvalidScoringResult;
use Illuminate\Support\Facades\Validator;

/**
 * Never trust the model. The answer must cover every criterion of the rubric
 * exactly once, stay within 0..100 and cite only evidence that was provided.
 */
class ScoringResultValidator
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<int>  $allowedEvidenceIds
     * @return array<string, mixed> the validated payload
     *
     * @throws InvalidScoringResult
     */
    public function validate(array $payload, RubricVersion $version, array $allowedEvidenceIds): array
    {
        $validator = Validator::make($payload, [
            'overall_summary' => ['required', 'string'],
            'cautions' => ['present', 'array'],
            'cautions.*' => ['string'],
            'criteria' => ['required', 'array', 'min:1'],
            'criteria.*.criterion_code' => ['required', 'string'],
            'criteria.*.score' => ['required', 'numeric', 'between:0,100'],
            'criteria.*.rationale' => ['required', 'string', 'min:1'],
            'criteria.*.evidence_references' => ['present', 'array'],
            'criteria.*.evidence_references.*.evidence_id' => ['required', 'integer'],
            'criteria.*.evidence_references.*.locator' => ['required', 'string'],
            'criteria.*.gaps' => ['present', 'array'],
            'criteria.*.gaps.*' => ['string'],
            'criteria.*.uncertainty' => ['present', 'nullable', 'string'],
        ]);

        $problems = array_values($validator->errors()->all());

        if ($problems !== []) {
            throw new InvalidScoringResult($problems);
        }

        $problems = [];
        $expectedCodes = $version->criteria->pluck('code')->all();
        $givenCodes = array_column($payload['criteria'], 'criterion_code');

        foreach (array_diff($expectedCodes, $givenCodes) as $code) {
            $problems[] = "Kriteria {$code} tidak dinilai.";
        }

        foreach (array_diff($givenCodes, $expectedCodes) as $code) {
            $problems[] = "Kriteria {$code} tidak ada di rubrik.";
        }

        foreach (array_count_values($givenCodes) as $code => $count) {
            if ($count > 1) {
                $problems[] = "Kriteria {$code} dinilai lebih dari sekali.";
            }
        }

        $allowed = array_flip($allowedEvidenceIds);

        foreach ($payload['criteria'] as $criterion) {
            foreach ($criterion['evidence_references'] as $reference) {
                if (! isset($allowed[$reference['evidence_id']])) {
                    $problems[] = "Evidence {$reference['evidence_id']} bukan bagian dari bukti proyek ini.";
                }
            }
        }

        if ($problems !== []) {
            throw new InvalidScoringResult(array_values(array_unique($problems)));
        }

        return $payload;
    }
}
