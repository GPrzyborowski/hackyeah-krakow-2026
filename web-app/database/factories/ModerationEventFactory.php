<?php

namespace Database\Factories;

use App\Enums\ModerationContext;
use App\Models\Company;
use App\Models\ModerationEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModerationEvent>
 */
class ModerationEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employer(),
            'company_id' => fn (array $attributes): ?int => User::query()->find($attributes['user_id'])?->company_id,
            'context' => ModerationContext::Invitation,
            'excerpt' => 'Czy planuje Pani kolejne dziecko?',
            'reason' => 'Pytania o plany rodzinne są niedozwolone.',
            'suggestion' => 'Zapytaj o dostępność.',
            'moderator' => ModerationEvent::MODERATOR_KEYWORD,
            'created_at' => now(),
        ];
    }

    /**
     * Indicate that the event belongs to the given company.
     */
    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory()->employer($company),
            'company_id' => $company->id,
        ]);
    }

    /**
     * Indicate the context of the blocked text.
     */
    public function context(ModerationContext $context): static
    {
        return $this->state(fn (array $attributes) => [
            'context' => $context,
        ]);
    }
}
