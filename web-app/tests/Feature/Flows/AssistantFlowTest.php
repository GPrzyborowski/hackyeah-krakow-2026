<?php

namespace Tests\Feature\Flows;

use App\Enums\AssistantRole;
use App\Models\CandidateProfile;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssistantFlowTest extends TestCase
{
    use InteractsWithFlows, RefreshDatabase;

    public function test_candidate_asks_about_pregnancy_at_interview_and_gets_an_offline_answer_citing_the_labour_code(): void
    {
        Http::preventStrayRequests();
        $this->seed(ContentSeeder::class);
        $candidate = CandidateProfile::factory()->published()->pregnant()->create();

        $this->actingAs($candidate->user)
            ->get(route('assistant.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('assistant/Index')
                ->has('messages', 0)
                ->where('suggestions', fn ($suggestions): bool => collect($suggestions)->contains('Czy muszę mówić o ciąży na rozmowie?')));

        $this->actingAs($candidate->user)
            ->post(route('assistant.store'), ['question' => 'Czy muszę mówić o ciąży na rozmowie?'])
            ->assertRedirect(route('assistant.index'));

        Http::assertNothingSent();
        $answer = $candidate->user->assistantMessages()->where('role', AssistantRole::Assistant)->sole();
        $this->assertNotSame('', trim($answer->content));
        $legalCitations = collect($answer->citations)->where('type', 'legal');
        $this->assertNotEmpty($legalCitations);
        $this->assertContains('Kodeks pracy, art. 22¹', $legalCitations->pluck('label')->all());

        $this->actingAs($candidate->user)
            ->get(route('assistant.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('messages', 2)
                ->where('messages.0.role', AssistantRole::User->value)
                ->where('messages.0.content', 'Czy muszę mówić o ciąży na rozmowie?')
                ->where('messages.1.role', AssistantRole::Assistant->value)
                ->where('messages.1.citations', fn ($citations): bool => collect($citations)->contains('label', 'Kodeks pracy, art. 22¹')));
    }
}
