<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'sim_id' => is_string($this->input('sim_id')) ? strtoupper(trim($this->input('sim_id'))) : $this->input('sim_id'),
            'email' => is_string($this->input('email')) ? strtolower(trim($this->input('email'))) : $this->input('email'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sim_id' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9._-]+$/', Rule::unique('users', 'sim_id')],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('is_active', true)],
            'role' => ['required', 'string', Rule::in(Role::query()->pluck('name')->all())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'sim_id' => 'SIM ID',
            'email' => 'email',
            'organization_id' => 'organisasi',
            'role' => 'role',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'max' => ':Attribute maksimal :max karakter.',
            'email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan akun lain.',
            'organization_id.exists' => 'Organisasi yang dipilih tidak tersedia.',
            'sim_id.unique' => 'SIM ID sudah digunakan akun lain.',
            'sim_id.regex' => 'SIM ID hanya boleh berisi huruf, angka, titik, tanda hubung, dan garis bawah.',
            'role.in' => 'Role yang dipilih tidak valid.',
        ];
    }
}
