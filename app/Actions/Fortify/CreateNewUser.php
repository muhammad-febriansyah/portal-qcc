<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Models\Role;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $input['name'] = is_string($input['name'] ?? null) ? trim($input['name']) : $input['name'] ?? null;
        $input['sim_id'] = is_string($input['sim_id'] ?? null) ? strtoupper(trim($input['sim_id'])) : $input['sim_id'] ?? null;
        $input['email'] = is_string($input['email'] ?? null) ? strtolower(trim($input['email'])) : $input['email'] ?? null;

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'sim_id' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9._-]+$/', Rule::unique('users', 'sim_id')],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => $this->passwordRules(),
        ], [
            'sim_id.unique' => 'SIM ID sudah terdaftar.',
            'sim_id.regex' => 'SIM ID hanya boleh berisi huruf, angka, titik, garis bawah, atau strip.',
            'email.unique' => 'Email sudah terdaftar.',
        ])->validate();

        return DB::transaction(function () use ($validated): User {
            $user = User::create([
                ...$validated,
                'is_active' => true,
                'needs_profile_setup' => true,
            ]);

            $user->syncRoles([Role::findOrCreate('SIMer / Member', 'web')]);

            return $user;
        });
    }
}
