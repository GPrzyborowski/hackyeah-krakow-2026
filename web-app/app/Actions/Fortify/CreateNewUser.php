<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Rules\ValidNip;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
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
            'company_nip' => ['required_if:role,employer', 'nullable', 'string', new ValidNip],
        ])->after(function (ValidatorContract $validator) use ($input): void {
            if (($input['role'] ?? null) === UserRole::Employer->value
                && is_string($input['company_nip'] ?? null)
                && Company::where('nip', ValidNip::normalize($input['company_nip']))->exists()) {
                $validator->errors()->add('company_nip', 'Firma z tym NIP-em ma już konto w mumjobs. Poproś osobę z Twojej firmy o zaproszenie do zespołu w mumjobs.');
            }
        })->validate();

        return DB::transaction(function () use ($input): User {
            $role = UserRole::from($input['role']);

            $user = new User([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $user->forceFill([
                'role' => $role,
                'company_id' => $role === UserRole::Employer
                    ? Company::create(['name' => $input['company_name'], 'nip' => ValidNip::normalize($input['company_nip'])])->id
                    : null,
            ])->save();

            if ($role === UserRole::Candidate) {
                $user->candidateProfile()->create();
            }

            return $user;
        });
    }
}
