<?php

namespace App\Http\Requests;

use App\Enums\CompetencyLevel;
use App\Models\MemberCompetency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberCompetencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var MemberCompetency $memberCompetency */
        $memberCompetency = $this->route('memberCompetency');

        return $this->user()?->can('update', $memberCompetency) ?? false;
    }

    /**
     * Only the self-assessed level can change. The competency itself and the
     * verified level are never part of this request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'self_level' => ['required', Rule::enum(CompetencyLevel::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'self_level.required' => 'Pilih level kemampuan.',
            'self_level.enum' => 'Level kemampuan harus antara 1 dan 4.',
        ];
    }
}
