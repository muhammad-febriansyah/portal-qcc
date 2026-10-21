<?php

namespace App\Http\Requests;

use App\Enums\CompetencyLevel;
use App\Models\MemberCompetency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberCompetencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MemberCompetency::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'competency_id' => [
                'required',
                'integer',
                Rule::exists('competency_master', 'id')->where('is_active', true),
                Rule::unique('member_competencies', 'competency_id')->where('user_id', $this->user()?->id),
            ],
            'self_level' => ['required', Rule::enum(CompetencyLevel::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'competency_id' => 'kompetensi',
            'self_level' => 'level',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'competency_id.required' => 'Pilih kompetensi.',
            'competency_id.exists' => 'Kompetensi yang dipilih tidak tersedia.',
            'competency_id.unique' => 'Kompetensi ini sudah ada di daftar Anda.',
            'self_level.required' => 'Pilih level kemampuan.',
            'self_level.enum' => 'Level kemampuan harus antara 1 dan 4.',
        ];
    }
}
