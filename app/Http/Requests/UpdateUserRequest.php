<?php

namespace App\Http\Requests;

use App\Actions\Users\GuardLastSuperAdmin;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends StoreUserRequest
{
    public function authorize(): bool
    {
        /** @var User $target */
        $target = $this->route('user');

        return $this->user()?->can('update', $target) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            ...parent::rules(),
            'sim_id' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9._-]+$/', Rule::unique('users', 'sim_id')->ignore($target)],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($target)],
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User $target */
                $target = $this->route('user');

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $organizationId = (int) $this->input('organization_id');

                if ($organizationId !== $target->organization_id
                    && ! Organization::query()->whereKey($organizationId)->where('is_active', true)->exists()) {
                    $validator->errors()->add('organization_id', 'Organisasi yang dipilih tidak tersedia.');
                }

                if (app(GuardLastSuperAdmin::class)->wouldRemoveLastAdmin($target, true, [(string) $this->input('role')])) {
                    $validator->errors()->add('role', 'Role Super Admin tidak dapat dilepas dari Super Admin aktif terakhir.');
                }
            },
        ];
    }
}
