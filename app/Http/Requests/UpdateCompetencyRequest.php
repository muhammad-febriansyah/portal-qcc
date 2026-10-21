<?php

namespace App\Http\Requests;

use App\Models\Competency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompetencyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $competency = $this->route('competency');

        return $competency instanceof Competency
            && ($this->user()?->can('update', $competency) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $officialCodes = array_map(
            static fn (int $number): string => sprintf('C%03d', $number),
            range(1, 62),
        );

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                Rule::in($officialCodes),
                Rule::unique('competency_master', 'code')->ignore($this->route('competency')),
            ],
            'group_id' => ['sometimes', 'required', 'integer', 'exists:competency_groups,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:65535'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.in' => 'Kode harus menggunakan kode resmi C001–C062.',
            'code.unique' => 'Kode kompetensi sudah digunakan.',
            'group_id.exists' => 'Kelompok kompetensi tidak valid.',
        ];
    }
}
