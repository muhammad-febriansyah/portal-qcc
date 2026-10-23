<?php

namespace App\Http\Requests;

use App\Enums\RiskLevel;
use App\Enums\RiskStatus;
use App\Enums\RiskType;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $this->user()?->can('manageRisks', $project) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'type' => ['required', Rule::enum(RiskType::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'impact' => ['required', 'string', 'max:5000'],
            'likelihood' => ['required', Rule::in(array_map(fn (RiskLevel $level): string => $level->value, RiskLevel::likelihoods()))],
            'priority' => ['required', Rule::enum(RiskLevel::class)],
            'mitigation' => ['nullable', 'string', 'max:10000'],
            'pic_id' => ['nullable', 'integer', Rule::exists('project_members', 'user_id')
                ->where('project_id', $project->id)->where('status', 'ACTIVE')->whereNull('left_at')],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['required', Rule::enum(RiskStatus::class)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'type' => 'jenis', 'title' => 'judul', 'description' => 'deskripsi', 'impact' => 'dampak',
            'likelihood' => 'kemungkinan', 'priority' => 'prioritas', 'mitigation' => 'mitigasi',
            'pic_id' => 'PIC', 'due_date' => 'tenggat', 'status' => 'status',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['pic_id.exists' => 'PIC harus anggota aktif tim proyek ini.'];
    }
}
