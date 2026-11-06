<?php

namespace App\Http\Requests;

use App\Enums\BatchStatus;
use App\Enums\QccStep;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProposalDraftRequest extends FormRequest
{
    public const LAST_STEP = 7;

    public const REQUIREMENTS_STEP = 6;

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
        $timelineSize = count(QccStep::cases());

        $rules = [
            'batch_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'theme' => ['nullable', 'string', 'max:5000'],
            'background' => ['nullable', 'string', 'max:20000'],
            'current_situation' => ['nullable', 'string', 'max:20000'],
            'improvement_plan' => ['nullable', 'string', 'max:20000'],
            'target' => ['nullable', 'string', 'max:20000'],
            'scope' => ['nullable', 'string', 'max:5000'],
            'supplementary_information' => ['nullable', 'string', 'max:20000'],
            'cost_projection' => ['nullable', 'string', 'max:20000'],
            'learning_goals' => ['nullable', 'array', 'max:3'],
            'learning_goals.*' => ['nullable', 'string', 'max:255'],
            'step_start_dates' => ['nullable', 'array', "max:{$timelineSize}"],
            'step_start_dates.*' => ['nullable', 'date_format:Y-m-d'],
            'step_timeline' => ['nullable', 'array', "max:{$timelineSize}"],
            'step_timeline.*' => ['nullable', 'date_format:Y-m-d'],
            'step_deliverables' => ['nullable', 'array', "max:{$timelineSize}"],
            'step_deliverables.*' => ['nullable', 'string', 'max:500'],
            'wizard_step' => ['required', 'integer', 'between:1,'.self::LAST_STEP],
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
                    'current_situation' => ['required', 'string', 'max:20000'],
                ],
                3 => [
                    'improvement_plan' => ['required', 'string', 'max:20000'],
                    'target' => ['required', 'string', 'max:20000'],
                    'scope' => ['required', 'string', 'max:5000'],
                ],
                4 => [
                    'cost_projection' => ['required', 'string', 'max:20000'],
                ],
                5 => [
                    'learning_goals' => ['required', 'array', 'size:3'],
                    'learning_goals.*' => ['required', 'string', 'max:255'],
                    'step_start_dates' => ['required', 'array', "size:{$timelineSize}"],
                    'step_start_dates.*' => ['required', 'date_format:Y-m-d'],
                    'step_timeline' => ['required', 'array', "size:{$timelineSize}"],
                    'step_timeline.*' => ['required', 'date_format:Y-m-d'],
                ],
                default => [],
            };

            $rules = array_replace($rules, $stepRules);
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateTimelineOrder($validator);
                $this->validateRequirementsBeforeAdvancing($validator);
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $attributes = [
            'batch_id' => 'batch',
            'title' => 'judul proposal',
            'theme' => 'tema improvement',
            'background' => 'latar belakang',
            'current_situation' => 'situasi saat ini & baseline',
            'improvement_plan' => 'rencana perbaikan',
            'target' => 'target/tujuan',
            'scope' => 'ruang lingkup proyek',
            'supplementary_information' => 'informasi dan data pelengkap',
            'cost_projection' => 'proyeksi kebutuhan biaya proyek',
            'learning_goals' => 'tiga hal yang akan dipelajari',
            'step_start_dates' => 'tanggal mulai timeline',
            'step_timeline' => 'timeline',
        ];

        foreach (QccStep::cases() as $index => $step) {
            $attributes["step_start_dates.{$index}"] = "tanggal mulai step {$step->value}";
            $attributes["step_timeline.{$index}"] = "target selesai step {$step->value}";
            $attributes["step_deliverables.{$index}"] = "deliverable step {$step->value}";
        }

        return $attributes;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'exists' => 'Pilihan :attribute tidak valid atau sudah tidak aktif.',
            'max' => ':Attribute maksimal :max karakter.',
            'date_format' => ':Attribute harus berupa tanggal yang valid.',
            'learning_goals.size' => 'Isi tepat 3 hal yang akan dipelajari.',
            'learning_goals.*.required' => 'Semua hal yang akan dipelajari wajib diisi.',
            'step_start_dates.size' => 'Isi tanggal mulai untuk semua 8 step QCC.',
            'step_start_dates.*.required' => ':Attribute wajib diisi.',
            'step_timeline.size' => 'Isi target selesai untuk semua 8 step QCC.',
            'step_timeline.*.required' => ':Attribute wajib diisi.',
            'wizard_step.between' => 'Langkah proposal tidak valid.',
        ];
    }

    /**
     * Each QCC step must finish on or after the day it starts. Steps may overlap,
     * since several steps can run at the same time.
     */
    private function validateTimelineOrder(Validator $validator): void
    {
        $startDates = $this->input('step_start_dates');
        $targetDates = $this->input('step_timeline');

        if (! is_array($startDates) || ! is_array($targetDates)) {
            return;
        }

        $startDates = array_values($startDates);

        foreach (array_values($targetDates) as $index => $targetDate) {
            $startDate = $startDates[$index] ?? null;

            if (! $this->isDateValue($startDate) || ! $this->isDateValue($targetDate)) {
                continue;
            }

            if ($targetDate < $startDate) {
                $step = $index + 1;
                $validator->errors()->add("step_timeline.{$index}", "Target selesai step {$step} tidak boleh lebih awal dari tanggal mulainya.");
            }
        }
    }

    private function isDateValue(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    private function validateRequirementsBeforeAdvancing(Validator $validator): void
    {
        $project = $this->route('project');

        if (! $this->boolean('advance')
            || (int) $this->input('wizard_step') !== self::REQUIREMENTS_STEP
            || ! $project instanceof Project) {
            return;
        }

        if (! $project->requirements()->exists()) {
            $validator->errors()->add('requirements', 'Tambahkan minimal satu kebutuhan kompetensi proyek.');
        }
    }
}
