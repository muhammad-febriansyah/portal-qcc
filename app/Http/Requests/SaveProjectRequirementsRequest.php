<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectRequirementsRequest extends FormRequest
{
    public const MAX_REQUIREMENTS = 20;

    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user() !== null
            && $this->user()->can('update', $project);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'requirements' => ['present', 'array', 'max:'.self::MAX_REQUIREMENTS],
            'requirements.*.competency_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('competency_master', 'id')->where('is_active', true),
            ],
            'requirements.*.minimum_level' => ['nullable', 'integer', 'between:1,4'],
        ];
    }

    /** @return list<array{competency_id: int, minimum_level: int|null}> */
    public function requirements(): array
    {
        $validated = $this->validated('requirements');

        if (! is_array($validated)) {
            return [];
        }

        $requirements = [];

        foreach ($validated as $item) {
            if (! is_array($item) || ! isset($item['competency_id'])) {
                continue;
            }

            $minimumLevel = $item['minimum_level'] ?? null;
            $requirements[] = [
                'competency_id' => (int) $item['competency_id'],
                'minimum_level' => $minimumLevel === null ? null : (int) $minimumLevel,
            ];
        }

        return $requirements;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'requirements' => 'kebutuhan kompetensi',
            'requirements.*.competency_id' => 'kompetensi',
            'requirements.*.minimum_level' => 'level minimum',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'requirements.max' => 'Kebutuhan kompetensi maksimal '.self::MAX_REQUIREMENTS.' kompetensi.',
            'requirements.*.competency_id.distinct' => 'Kompetensi yang sama tidak boleh dipilih dua kali.',
            'requirements.*.competency_id.exists' => 'Kompetensi tidak ada di master atau sudah tidak aktif.',
            'requirements.*.minimum_level.between' => 'Level minimum harus antara 1 dan 4.',
        ];
    }
}
