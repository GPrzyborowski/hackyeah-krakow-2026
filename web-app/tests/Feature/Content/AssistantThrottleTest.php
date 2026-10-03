<?php

namespace Tests\Feature\Content;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_too_many_questions_return_a_friendly_validation_error_instead_of_429(): void
    {
        config(['services.anthropic.key' => null]);
        $user = User::factory()->create();

        foreach (range(1, 10) as $attempt) {
            $this->actingAs($user)->post(route('assistant.store'), ['question' => 'Ile trwa urlop rodzicielski?']);
        }

        $this->actingAs($user)
            ->from(route('assistant.index'))
            ->post(route('assistant.store'), ['question' => 'Ile trwa urlop rodzicielski?'])
            ->assertRedirect(route('assistant.index'))
            ->assertSessionHasErrors(['question' => 'Za dużo pytań naraz. Spróbuj ponownie za minutę.']);

        $this->assertSame(20, $user->assistantMessages()->count());
    }
}
