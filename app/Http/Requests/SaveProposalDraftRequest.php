<?php

namespace App\Http\Requests;

use App\Enums\BatchStatus;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProposalDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $project = $this->route('project');

        if ($user === null) {
            return false;
        }

        return $project instanceof Project
            ? $user->can('update', $project)
            : $user->can('create', Project::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $rules = [
            'batch_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'theme' => ['nullable', 'string', 'max:5000'],
            'background' => ['nullable', 'string', 'max:20000'],
            'problem_statement' => ['nullable', 'string', 'max:20000'],
            'current_situation' => ['nullable', 'string', 'max:20000'],
            'baseline' => ['nullable', 'string', 'max:20000'],
            'target' => ['nullable', 'string', 'max:20000'],
            'scope' => ['nullable', 'string', 'max:5000'],
            'learning_goals' => ['nullable', 'array', 'max:3'],
            'learning_goals.*' => ['nullable', 'string', 'max:255'],
            'rca' => ['nullable', 'string', 'max:20000'],
            'improvement_plan' => ['nullable', 'string', 'max:20000'],
            'business_benefit' => ['nullable', 'string', 'max:20000'],
            'wizard_step' => ['required', 'integer', 'between:1,5'],
            'advance' => ['required', 'boolean'],
        ];

        $project = $this->route('project');

        if ($project instanceof Project) {
            $rules['batch_id'][] = Rule::exists('qcc_batches', 'id');
        } else {
            $rules['batch_id'][] = Rule::exists('qcc_batches', 'id')
                ->where('status', BatchStatus::Active->value);
        }

        if ($this->boolean('advance')) {
            $stepRules = match ((int) $this->input('wizard_step', 1)) {
                1 => [
                    'title' => ['required', 'string', 'max:255'],
                    'theme' => ['required', 'string', 'max:5000'],
                    'batch_id' => ['required', 'integer', Rule::exists('qcc_batches', 'id')->where('status', BatchStatus::Active->value)],
                ],
                2 => [
                    'background' => ['required', 'string', 'max:20000'],
                    'problem_statement' => ['required', 'string', 'max:20000'],
                    'current_situation' => ['required', 'string', 'max:20000'],
                    'baseline' => ['required', 'string', 'max:20000'],
                ],
                3 => [
                    'target' => ['required', 'string', 'max:20000'],
                    'scope' => ['required', 'string', 'max:5000'],
                    'rca' => ['required', 'string', 'max:20000'],
                    'improvement_plan' => ['required', 'string', 'max:20000'],
                    'business_benefit' => ['required', 'string', 'max:20000'],
                ],
                4 => [
                    'learning_goals' => ['required', 'array', 'size:3'],
                    'learning_goals.*' => ['required', 'string', 'max:255'],
                ],
                default => [],
            };

            $rules = array_replace($rules, $stepRules);
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'batch_id' => 'batch',
            'title' => 'judul proposal',
            'theme' => 'tema',
            'background' => 'latar belakang',
            'problem_statement' => 'pernyataan masalah',
            'current_situation' => 'situasi saat ini',
            'baseline' => 'baseline',
            'target' => 'target',
            'scope' => 'ruang lingkup',
            'learning_goals' => 'hal yang ingin dipelajari',
            'rca' => 'analisis akar masalah',
            'improvement_plan' => 'rencana perbaikan',
            'business_benefit' => 'manfaat bisnis',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi sebelum lanjut.',
            'exists' => 'Pilihan :attribute tidak valid atau sudah tidak aktif.',
            'max' => ':Attribute maksimal :max karakter.',
            'learning_goals.size' => 'Isi tepat 3 hal yang ingin dipelajari.',
            'learning_goals.*.required' => 'Semua hal yang ingin dipelajari wajib diisi.',
            'wizard_step.between' => 'Langkah proposal tidak valid.',
        ];
    }
}
