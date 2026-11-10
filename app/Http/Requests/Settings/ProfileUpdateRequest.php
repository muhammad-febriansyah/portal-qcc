<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),
            'position' => ['required', 'string', 'max:255'],
            'work_location' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'regex:/^\+?[0-9][0-9 \-]{7,19}$/'],
            'organization_id' => [
                'required',
                'integer',
                Rule::exists('organizations', 'id')->where('is_active', true),
            ],
            'qcc_experience_batches' => ['array'],
            'qcc_experience_batches.*' => ['string', 'distinct', Rule::in(['QCC Batch 1', 'QCC Batch 2', 'QCC Batch 3'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_number.required' => 'Nomor handphone wajib diisi.',
            'phone_number.regex' => 'Nomor handphone hanya boleh berisi angka, spasi, tanda hubung, dan + di depan (8-20 karakter).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'qcc_experience_batches' => $this->input('qcc_experience_batches', []),
        ]);
    }
}
