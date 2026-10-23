<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveProjectStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user() !== null
            && $this->user()->can('updateStep', $project);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'activity_description' => ['nullable', 'string', 'max:20000'],
            'plan_text' => ['nullable', 'string', 'max:20000'],
            'actual_text' => ['nullable', 'string', 'max:20000'],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
            'progress_pct' => ['nullable', 'integer', 'between:0,100'],
            'submitter_note' => ['nullable', 'string', 'max:5000'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'activity_description' => 'deskripsi aktivitas',
            'plan_text' => 'rencana',
            'actual_text' => 'aktual',
            'target_date' => 'tanggal target',
            'progress_pct' => 'progress',
            'submitter_note' => 'catatan pengaju',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'progress_pct.between' => 'Progress harus antara 0 dan 100.',
            'target_date.date_format' => 'Tanggal target tidak valid.',
        ];
    }
}
