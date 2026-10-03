<?php

namespace App\Http\Requests\Employer;

use App\Models\Company;
use App\Services\Employer\CompanyTeam;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Invite a recruiter by e-mail. Whether the address already has a mumjobs account is never revealed here
 * (it could expose candidates); the acceptance page explains when an account cannot join.
 */
class InviteTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->user()->company;

        return $company instanceof Company && $this->user()->can('manageTeam', $company);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('email')) {
                    return;
                }

                $team = app(CompanyTeam::class);
                $company = $this->user()->company;
                $email = (string) $this->input('email');

                if ($team->isMember($company, $email)) {
                    $validator->errors()->add('email', 'Ta osoba już należy do Twojego zespołu.');
                } elseif ($team->hasUsableInvitation($company, $email)) {
                    $validator->errors()->add('email', 'Zaproszenie na ten adres już czeka na akceptację.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }
}
