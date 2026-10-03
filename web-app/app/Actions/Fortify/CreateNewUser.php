<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'role' => ['required', Rule::enum(UserRole::class)->only([UserRole::Candidate, UserRole::Employer])],
            'company_name' => ['required_if:role,employer', 'nullable', 'string', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $role = UserRole::from($input['role']);

            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'role' => $role,
                'company_id' => $role === UserRole::Employer ? Company::create(['name' => $input['company_name']])->id : null,
            ]);

            if ($role === UserRole::Candidate) {
                $user->candidateProfile()->create();
            }

            return $user;
        });
    }
}
