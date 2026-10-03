<?php

namespace Tests\Feature\Employer;

use App\Enums\CandidateDecisionType;
use App\Enums\InvitationStatus;
use App\Enums\JobSharePairStatus;
use App\Enums\OfferStatus;
use App\Enums\SkillImportance;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\Conversation;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use App\Models\Message;
use App\Models\User;

/**
 * A crafted company whose dashboard numbers are known up front.
 *
 * Published "Rekruterka" (required HR skill, salary, flexible hours): matches Anna + Basia; Basia saved,
 * Anna invited and accepted (unread chat), Celina invited 40 days ago and declined; one submitted job-share pair.
 * Published "Kadrowa" (nice-to-have payroll only, no salary, fixed hours): matches Anna + Celina, nobody reviewed.
 * Dorota starts too late, Ewa hid her profile from the company; a draft and a closed offer and another company's data must not count.
 */
trait BuildsEmployerDashboardDataset
{
    use InteractsWithEmployerFixtures;

    /**
     * @return array{employer: User, recruiterOffer: JobOffer, payrollOffer: JobOffer}
     */
    protected function dashboardDataset(): array
    {
        $hr = $this->skill('Rekrutacja IT');
        $payroll = $this->skill('Kadry i płace');
        $excel = $this->skill('Excel');

        $employer = $this->employer(Company::factory()->create(['name' => 'Zielone Biuro']));
        $company = $employer->company;
        CompanyReview::factory()->for($company)->create();

        $recruiterOffer = $this->publishedOffer($company, [$hr]);
        $recruiterOffer->update(['title' => 'Rekruterka', 'salary_min' => 6000, 'salary_max' => 8000, 'flexible_hours' => true, 'is_job_share' => true]);
        $payrollOffer = $this->publishedOffer($company, [], [$payroll]);
        $payrollOffer->update(['title' => 'Kadrowa', 'salary_min' => null, 'salary_max' => null, 'flexible_hours' => false]);

        $draft = JobOffer::factory()->for($company)->create(['status' => OfferStatus::Draft, 'title' => 'Szkic', 'salary_min' => 5000, 'salary_max' => 6000, 'flexible_hours' => true]);
        $draft->skills()->attach($excel, ['importance' => SkillImportance::Required->value]);
        JobOffer::factory()->for($company)->create(['status' => OfferStatus::Closed, 'title' => 'Zamknięta', 'salary_min' => null, 'flexible_hours' => false]);

        $anna = $this->candidate([$hr, $payroll], name: 'Anna Nowak');
        $basia = $this->candidate([$hr], name: 'Barbara Lis');
        $celina = $this->candidate([$payroll], name: 'Celina Wrona');
        $this->candidate([$hr], ['available_from' => '2028-06-01'], name: 'Dorota Późna');
        $this->candidate([$hr], ['hidden_from_company_id' => $company->id], name: 'Ewa Ukryta');

        $recruiterOffer->decisions()->create(['candidate_profile_id' => $basia->id, 'decision' => CandidateDecisionType::Saved]);

        $accepted = Invitation::factory()->accepted()->for($recruiterOffer)->for($anna)->create();
        $declined = Invitation::factory()->for($recruiterOffer)->for($celina)->create(['status' => InvitationStatus::Declined, 'responded_at' => now()->subDays(39)]);
        $declined->forceFill(['created_at' => now()->subDays(40)])->save();

        $conversation = Conversation::factory()->for($accepted)->create(['last_message_at' => now()]);
        Message::factory()->for($conversation)->create(['user_id' => $anna->user_id, 'body' => 'Dzień dobry, chętnie porozmawiam.']);

        $pair = JobSharePair::factory()->for($recruiterOffer)->create(['status' => JobSharePairStatus::Submitted, 'submitted_at' => now()->addMinute()]);
        $pair->members()->attach([$basia->id => ['is_initiator' => true, 'accepted_at' => now()], $celina->id => ['is_initiator' => false, 'accepted_at' => now()]]);

        $otherOffer = $this->publishedOffer(Company::factory()->create(), [$hr]);
        Invitation::factory()->accepted()->for($otherOffer)->for($basia)->create();

        return ['employer' => $employer, 'recruiterOffer' => $recruiterOffer, 'payrollOffer' => $payrollOffer];
    }
}
