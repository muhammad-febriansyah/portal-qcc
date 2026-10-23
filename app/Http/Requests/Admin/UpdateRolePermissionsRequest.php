<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateRolePermissionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $this->user()?->can('roles.manage') === true
            && $role instanceof Role
            && in_array($role->name, [
                'Super Admin',
                'Owner QCC',
                'Mentor',
                'Team Leader',
                'SIMer / Member',
            ], true)
            && $role->guard_name === 'web';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.present' => 'Kirim daftar permission untuk disimpan.',
            'permissions.array' => 'Daftar permission tidak valid.',
            'permissions.*.exists' => 'Salah satu akses menu tidak tersedia.',
            'permissions.*.distinct' => 'Permission tidak boleh dipilih lebih dari sekali.',
        ];
    }
}
