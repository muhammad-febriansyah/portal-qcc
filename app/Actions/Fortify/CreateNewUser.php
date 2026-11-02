<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        try {
            return DB::transaction(function () use ($validated): User {
                $user = new User([
                    ...$validated,
                    'is_active' => true,
                    'needs_profile_setup' => true,
                ]);
                $user->saveQuietly();

                $user->syncRoles([Role::findOrCreate('SIMer / Member', 'web')]);

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // Two people can pass the check above at the same moment. The unique index in the
            // database has the last word, and the person who lost gets the same message as a duplicate.
            // Only the message of the database driver names the index; the full message also lists every column of the insert.
            $driverMessage = (string) ($exception->errorInfo[2] ?? $exception->getPrevious()?->getMessage() ?? '');
            // The index is named "users.users_sim_id_unique" on MySQL and "users.sim_id" on SQLite.
            $field = preg_match('/users[._](sim_id|email)/', $driverMessage, $match) === 1 ? $match[1] : null;

            if ($field === null) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                $field => $field === 'sim_id' ? 'SIM ID sudah terdaftar.' : 'Email sudah terdaftar.',
            ]);
        }
    }
}
