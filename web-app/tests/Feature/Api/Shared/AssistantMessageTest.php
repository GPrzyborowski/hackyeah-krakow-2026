<?php

namespace Tests\Feature\Api\Shared;

use App\Enums\AssistantRole;
use App\Models\Article;
use App\Models\AssistantMessage;
use App\Models\LegalSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.anthropic.key' => null]);
    }

    public function test_guests_get_401(): void
    {
        $this->getJson('/api/v1/assistant/messages')->assertUnauthorized();
    }

    public function test_history_lists_only_own_messages_with_disclaimer(): void
    {
        $user = User::factory()->employer()->create();
        AssistantMessage::factory()->for($user)->create(['content' => 'Moje pytanie?']);
        AssistantMessage::factory()->create(['content' => 'Cudze pytanie?']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/assistant/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.content', 'Moje pytanie?')
            ->assertJsonCount(4, 'meta.suggestions')
            ->assertJsonPath('meta.disclaimer', 'To informacja ogólna, a nie porada prawna. Przy sporze skontaktuj się z prawnikiem.');
    }

    public function test_question_is_answered_with_citations(): void
    {
        LegalSource::factory()->create([
            'article' => '22¹',
            'title' => 'Dane, których pracodawca może żądać od kandydata',
            'content' => 'Pracodawca nie może pytać o ciążę.',
            'keywords' => ['rozmowa kwalifikacyjna', 'ciąża', 'kandydat'],
        ]);
        Article::factory()->create(['title' => 'Rozmowa w ciąży', 'slug' => 'rozmowa-w-ciazy', 'excerpt' => 'Nie musisz mówić o ciąży.']);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/assistant/messages', ['question' => 'Czy muszę mówić o ciąży na rozmowie?'])
            ->assertCreated()
            ->assertJsonPath('data.question.role', 'user')
            ->assertJsonPath('data.answer.role', 'assistant')
            ->assertJsonPath('data.answer.citations.0', ['type' => 'legal', 'label' => 'Kodeks pracy, art. 22¹', 'url' => null])
            ->assertJsonPath('data.answer.citations.1.type', 'article');

        $this->assertSame(1, $user->assistantMessages()->where('role', AssistantRole::Assistant)->count());
    }

    public function test_question_is_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/assistant/messages', ['question' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question');
    }

    public function test_more_than_ten_questions_a_minute_get_429(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        foreach (range(1, 10) as $attempt) {
            $this->postJson('/api/v1/assistant/messages', ['question' => 'Ile trwa urlop rodzicielski?'])->assertCreated();
        }

        $this->postJson('/api/v1/assistant/messages', ['question' => 'Ile trwa urlop rodzicielski?'])->assertTooManyRequests();
        $this->assertSame(20, $user->assistantMessages()->count());
    }
}
