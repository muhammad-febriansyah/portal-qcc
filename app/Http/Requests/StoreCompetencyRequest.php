<?php

namespace App\Http\Requests;

use App\Models\Competency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompetencyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Competency::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:10',
                Rule::in(self::officialCodes()),
                Rule::unique('competency_master', 'code'),
            ],
            'group_id' => ['required', 'integer', 'exists:competency_groups,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return list<string>
     */
    private static function officialCodes(): array
    {
        return array_map(
            static fn (int $number): string => sprintf('C%03d', $number),
            range(1, 62),
        );
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
