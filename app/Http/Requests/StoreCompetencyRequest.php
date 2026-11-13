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
                'regex:'.Competency::CODE_PATTERN,
                Rule::unique('competency_master', 'code'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => Competency::CODE_HINT,
            'code.unique' => 'Kode kompetensi sudah digunakan.',
        ];
    }
}
