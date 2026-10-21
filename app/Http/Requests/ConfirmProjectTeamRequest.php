<?php

namespace App\Http\Requests;

use App\Actions\Teams\TeamRuleValidator;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmProjectTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user() !== null
            && $this->user()->can('findCandidates', $project);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'run_id' => ['required', 'integer'],
            'members' => ['required', 'array', 'max:'.(TeamRuleValidator::MAX_MEMBERS + 5)],
            'members.*.user_id' => ['required', 'integer'],
            'members.*.role_in_project' => ['required', Rule::in(TeamRuleValidator::ROLES)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'members.required' => 'Pilih minimal satu anggota tim.',
            'members.max' => 'Terlalu banyak anggota dipilih.',
            'members.*.role_in_project.in' => 'Peran anggota hanya boleh Leader atau Anggota.',
        ];
    }
}
