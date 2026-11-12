<?php

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization
            && ($this->user()?->can('update', $organization) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'required', 'string', 'alpha_dash', 'max:255', Rule::unique('organizations', 'code')->ignore($this->route('organization'))],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'mentor_ids' => ['sometimes', 'nullable', 'array', 'max:5'],
            'mentor_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('is_active', true)],
        ];
    }

    /**
     * Get Indonesian validation messages for organization fields.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode organisasi wajib diisi.',
            'code.alpha_dash' => 'Kode hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'code.unique' => 'Kode organisasi sudah digunakan.',
            'name.required' => 'Nama organisasi wajib diisi.',
            'is_active.required' => 'Status organisasi wajib dipilih.',
            'is_active.boolean' => 'Status organisasi tidak valid.',
            'mentor_ids.max' => 'Maksimal 5 mentor per organisasi.',
            'mentor_ids.*.exists' => 'Mentor harus pengguna aktif.',
        ];
    }
}
