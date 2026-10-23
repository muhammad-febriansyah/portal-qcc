<?php

namespace App\Http\Requests\Admin;

use App\Enums\CompetencyLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyMemberCompetencyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('competencies.verify') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'verified_level' => ['required', Rule::enum(CompetencyLevel::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['verified_level' => 'level verifikasi', 'note' => 'catatan'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'verified_level.required' => 'Pilih level hasil verifikasi.',
            'verified_level.enum' => 'Level verifikasi harus antara 1 dan 4.',
            'note.max' => 'Catatan maksimal 2.000 karakter.',
        ];
    }
}
