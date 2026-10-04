<?php

namespace Tests\Feature\Flows;

use App\Enums\DayPart;
use App\Enums\InvitationStatus;
use App\Enums\JobSharePairStatus;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\JobSharing\InteractsWithJobSharingFixtures;
use Tests\TestCase;

class JobSharingFlowTest extends TestCase
{
    use InteractsWithFlows, InteractsWithJobSharingFixtures, RefreshDatabase;

    public function test_two_candidates_form_a_pair_split_the_day_apply_together_and_are_invited_as_a_pair(): void
    {
        Notification::fake();
        $recruitment = $this->skill('Rekrutacja IT');
        $payroll = $this->skill('Kadry i płace');
        $company = Company::factory()->create(['name' => 'Zielone Biuro']);
        $recruiter = $this->employer($company);
        $initiator = $this->sharer([$recruitment], 'Marta Zawadzka', DayPart::Morning);
        $partner = $this->sharer([$payroll], 'Anna Kowalczyk', DayPart::Afternoon);
        $outsider = $this->sharer([$recruitment, $payroll], 'Ola Wiśniewska', DayPart::Afternoon);

        $this->actingAs($recruiter)
            ->post(route('employer.offers.store'), [
                'action' => 'publish',
                'title' => 'Specjalistka HR (job sharing)',
                'category' => 'hr',
                'city' => 'Kraków',
                'work_mode' => 'hybrid',
                'start_date' => '2027-09-01',
                'employment_fraction' => '1',
                'flexible_hours' => true,
                'is_job_share' => true,
                'workday_starts_at' => '08:00',
                'workday_ends_at' => '16:00',
                'required_skills' => ['Rekrutacja IT'],
                'nice_to_have_skills' => ['Kadry i płace'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $offer = JobOffer::query()->where('title', 'Specjalistka HR (job sharing)')->sole();
        $this->assertTrue($offer->is_job_share);

        $this->actingAs($initiator->user)
            ->get(route('job-sharing.partners.index', $offer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('job-sharing/Partners')
                ->where('partners', fn ($partners): bool => collect($partners)->contains(fn (array $row): bool => $row['id'] === $partner->id
                    && $row['anonymous_name'] === 'Anna K.'
                    && $row['is_complementary'] === true)));

        $this->actingAs($initiator->user)
            ->post(route('job-sharing.pairs.store', $offer), ['partner_id' => $partner->id])
            ->assertRedirect();
        $pair = JobSharePair::query()->sole();
        $this->assertSame(JobSharePairStatus::Forming, $pair->status);

        $this->actingAs($outsider->user)->get(route('job-sharing.pairs.show', $pair))->assertForbidden();
        $this->actingAs($initiator->user)->post(route('job-sharing.pairs.accept', $pair))->assertForbidden();

        $this->actingAs($partner->user)
            ->get(route('job-sharing.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('job-sharing/Index')
                ->has('invitations', 1)
                ->where('invitations.0.id', $pair->id)
                ->where('invitations.0.partner.display_name', 'Marta Z.'));
        $this->actingAs($partner->user)->post(route('job-sharing.pairs.accept', $pair))->assertRedirect(route('job-sharing.pairs.show', $pair));
        $this->assertSame(JobSharePairStatus::Formed, $pair->refresh()->status);

        $this->actingAs($initiator->user)
            ->post(route('job-sharing.pairs.messages.store', $pair), ['body' => 'Cześć! Biorę poranki, pasuje Ci popołudnie?'])
            ->assertRedirect();
        $this->actingAs($outsider->user)
            ->post(route('job-sharing.pairs.messages.store', $pair), ['body' => 'Mogę dołączyć?'])
            ->assertForbidden();
        $pairPage = $this->actingAs($partner->user)->get(route('job-sharing.pairs.show', $pair));
        $pairPage->assertInertia(fn (Assert $page) => $page
            ->component('job-sharing/Pair')
            ->where('pair.status', 'formed')
            ->has('messages', 1)
            ->where('messages.0.body', 'Cześć! Biorę poranki, pasuje Ci popołudnie?')
            ->where('messages.0.author_name', 'Marta')
            ->where('can.plan_schedule', true));
        $this->assertStringNotContainsString('Zawadzka', $this->pagePropsJson($pairPage));

        $this->actingAs($initiator->user)
            ->put(route('job-sharing.pairs.schedule.update', $pair), ['schedule' => [
                ['candidate_profile_id' => $initiator->id, 'starts_at' => '08:00', 'ends_at' => '11:00'],
                ['candidate_profile_id' => $partner->id, 'starts_at' => '12:00', 'ends_at' => '16:00'],
            ]])
            ->assertSessionHasErrors(['schedule' => 'Nikt nie pracuje między 11:00 a 12:00. Podzielcie cały dzień pracy.']);
        $this->assertNull($pair->refresh()->proposed_schedule);

        $this->actingAs($initiator->user)
            ->put(route('job-sharing.pairs.schedule.update', $pair), ['schedule' => [
                ['candidate_profile_id' => $initiator->id, 'starts_at' => '08:00', 'ends_at' => '12:00'],
                ['candidate_profile_id' => $partner->id, 'starts_at' => '12:00', 'ends_at' => '16:00'],
            ]])
            ->assertSessionHasNoErrors();

        $this->actingAs($initiator->user)->post(route('job-sharing.pairs.schedule.confirm', $pair))->assertSessionHasNoErrors();
        $this->actingAs($initiator->user)->post(route('job-sharing.pairs.submit', $pair))->assertSessionHasErrors('schedule');
        $this->assertSame(JobSharePairStatus::Formed, $pair->refresh()->status);

        $this->actingAs($recruiter)
            ->get(route('employer.offers.job-share-pairs.index', $offer))
            ->assertInertia(fn (Assert $page) => $page->has('pairs', 0));

        $this->actingAs($partner->user)->post(route('job-sharing.pairs.schedule.confirm', $pair))->assertSessionHasNoErrors();
        $this->actingAs($partner->user)->post(route('job-sharing.pairs.submit', $pair))->assertSessionHasNoErrors();
        $this->assertSame(JobSharePairStatus::Submitted, $pair->refresh()->status);
        $this->assertNotNull($pair->submitted_at);

        $pairsPage = $this->actingAs($recruiter)->get(route('employer.offers.job-share-pairs.index', $offer));
        $pairsPage->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('job-sharing/EmployerPairs')
            ->has('pairs', 1)
            ->where('pairs.0.id', $pair->id)
            ->where('pairs.0.status', 'submitted')
            ->has('pairs.0.members', 2)
            ->where('pairs.0.coverage.percent', 100));
        $pairsJson = $this->pagePropsJson($pairsPage);
        $this->assertStringContainsString('Marta Z.', $pairsJson);
        $this->assertStringContainsString('Anna K.', $pairsJson);
        $this->assertStringNotContainsString('Zawadzka', $pairsJson);
        $this->assertStringNotContainsString('Kowalczyk', $pairsJson);
        $this->assertStringNotContainsString($initiator->user->email, $pairsJson);
        $this->assertStringNotContainsString($partner->user->email, $pairsJson);

        $this->actingAs($this->employer())
            ->post(route('employer.job-share-pairs.invitation', $pair), ['message' => 'Zapraszamy na rozmowę.'])
            ->assertNotFound();

        $this->actingAs($recruiter)
            ->post(route('employer.job-share-pairs.invitation', $pair), ['message' => 'Zapraszamy Was obie na wspólną rozmowę o stanowisku.'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('employer.offers.job-share-pairs.index', $offer));

        $this->assertSame(JobSharePairStatus::Invited, $pair->refresh()->status);
        $invitations = Invitation::query()->where('job_share_pair_id', $pair->id)->get();
        $this->assertCount(2, $invitations);
        $this->assertEqualsCanonicalizing([$initiator->id, $partner->id], $invitations->pluck('candidate_profile_id')->all());
        $this->assertTrue($invitations->every(fn (Invitation $invitation): bool => $invitation->status === InvitationStatus::Pending));

        foreach ([$initiator, $partner] as $member) {
            $invitation = $invitations->firstWhere('candidate_profile_id', $member->id);

            $this->actingAs($member->user)
                ->get(route('candidate.invitations.index'))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('invitations', 1)
                    ->where('invitations.0.id', $invitation->id)
                    ->where('invitations.0.job_share_pair.id', $pair->id));

            $this->actingAs($member->user)->post(route('candidate.invitations.accept', $invitation))->assertRedirect();
            $this->assertSame(InvitationStatus::Accepted, $invitation->refresh()->status);
        }

        $this->assertSame(2, Conversation::query()->whereNotNull('invitation_id')->count());
        $this->assertSame(1, Conversation::query()->where('job_share_pair_id', $pair->id)->count());
        $this->actingAs($recruiter)
            ->get(route('employer.invitations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('invitations', 2)
                ->where('invitations', fn ($rows): bool => collect($rows)->pluck('candidate.full_name')->sort()->values()->all() === ['Anna Kowalczyk', 'Marta Zawadzka']));
    }
}
