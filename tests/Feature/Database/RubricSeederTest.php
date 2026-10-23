<?php

use App\Models\RubricCriterion;
use App\Models\RubricSet;
use App\Models\RubricVersion;
use Database\Seeders\RubricSeeder;

/**
 * @return array<string, list<array{string, float}>> Criterion name and weight per scoring type, in order.
 */
function seededRubric(string $scoringType): array
{
    $version = RubricSet::query()->where('scoring_type', $scoringType)->firstOrFail()->versions()->firstOrFail();

    return [$version->publication_status => $version->criteria->map(fn (RubricCriterion $criterion): array => [$criterion->name, (float) $criterion->weight_pct])->all()];
}

it('seeds the three rubrics of the BRD as draft with the baseline weights', function () {
    $this->seed(RubricSeeder::class);

    expect(seededRubric('proposal'))->toBe(['draft' => [
        ['Goals Project', 30.0],
        ['Problem / Challenge Identification', 35.0],
        ['Corrective Action / Improvement Action', 35.0],
    ]])->and(seededRubric('final_presentation'))->toBe(['draft' => [
        ['8 Step QCC', 25.0],
        ['Business Value & Impact Project', 25.0],
        ['Project Understanding & Analytical Depth / Ketajaman Analisa', 20.0],
        ['Sustainability & Standardization', 12.0],
        ['Cross-Functional Collaboration', 10.0],
        ['Presentation Visual Quality', 8.0],
    ]])->and(seededRubric('convention'))->toBe(['draft' => [
        ['Project Impact', 40.0],
        ['Process Improvement & Standardization', 30.0],
        ['Authenticity of Improvement Idea', 30.0],
    ]]);
});

it('gives every seeded rubric a total weight of exactly 100', function (string $scoringType) {
    $this->seed(RubricSeeder::class);

    $total = RubricCriterion::query()
        ->whereIn('rubric_version_id', RubricVersion::query()->whereHas('rubricSet', fn ($set) => $set->where('scoring_type', $scoringType))->select('id'))
        ->sum('weight_pct');

    expect((float) $total)->toBe(100.0);
})->with(['proposal', 'final_presentation', 'convention']);

it('records the authenticity note of the BRD on the convention rubric', function () {
    $this->seed(RubricSeeder::class);

    $description = RubricCriterion::query()->where('code', 'AUTHENTICITY')->value('description');

    expect($description)->toContain('tidak otomatis menghasilkan skor nol');
});

it('explains what every seeded criterion assesses', function () {
    $this->seed(RubricSeeder::class);

    $withoutDescription = RubricCriterion::query()->where(fn ($query) => $query->whereNull('description')->orWhere('description', ''))->pluck('code')->all();
    $tooLong = RubricCriterion::query()->get()->filter(fn (RubricCriterion $criterion): bool => mb_strlen((string) $criterion->description) > 2000)->pluck('code')->all();

    expect($withoutDescription)->toBe([])
        ->and($tooLong)->toBe([])
        ->and(RubricCriterion::query()->where('code', 'BUSINESS_VALUE_IMPACT')->value('description'))->toContain('Finance');
});

it('can run again without duplicating rubrics and restores an edited draft to the baseline', function () {
    $this->seed(RubricSeeder::class);
    RubricCriterion::query()->where('code', 'GOALS')->update(['weight_pct' => 10, 'name' => 'Edited name']);

    $this->seed(RubricSeeder::class);

    expect(RubricSet::query()->count())->toBe(3)
        ->and(RubricVersion::query()->count())->toBe(3)
        ->and(RubricCriterion::query()->count())->toBe(12)
        ->and(seededRubric('proposal')['draft'][0])->toBe(['Goals Project', 30.0]);
});

it('leaves a published version untouched', function () {
    $this->seed(RubricSeeder::class);
    $version = RubricSet::query()->where('scoring_type', 'proposal')->firstOrFail()->versions()->firstOrFail();
    $version->forceFill(['publication_status' => 'published'])->saveQuietly();
    RubricCriterion::query()->where('rubric_version_id', $version->id)->where('code', 'GOALS')->toBase()->update(['weight_pct' => 99]);

    $this->seed(RubricSeeder::class);

    expect((float) RubricCriterion::query()->where('rubric_version_id', $version->id)->where('code', 'GOALS')->value('weight_pct'))->toBe(99.0)
        ->and($version->fresh()->publication_status)->toBe('published');
});
