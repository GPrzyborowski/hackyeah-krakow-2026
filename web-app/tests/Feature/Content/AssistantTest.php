<?php

namespace Tests\Feature\Content;

use App\Enums\AssistantRole;
use App\Models\Article;
use App\Models\AssistantMessage;
use App\Models\LegalSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private LegalSource $interviewSource;

    private Article $article;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->interviewSource = LegalSource::factory()->create([
            'article' => '22¹',
            'title' => 'Dane, których pracodawca może żądać od kandydata',
            'content' => 'Pracodawca może żądać od kandydata imienia, nazwiska, daty urodzenia i danych kontaktowych. Nie może pytać o ciążę.',
            'keywords' => ['rozmowa kwalifikacyjna', 'ciąża', 'kandydat'],
        ]);
        LegalSource::factory()->create([
            'article' => '187',
            'title' => 'Przerwy na karmienie piersią',
            'content' => 'Pracownica karmiąca dziecko piersią ma prawo do przerw.',
            'keywords' => ['karmienie piersią'],
        ]);
        $this->article = Article::factory()->create([
            'title' => 'Rozmowa w ciąży: co musisz powiedzieć, a czego nie',
            'slug' => 'rozmowa-w-ciazy',
            'excerpt' => 'Na rozmowie nie musisz mówić o ciąży.',
        ]);
    }

    public function test_guests_cannot_use_the_assistant(): void
    {
        $this->get(route('assistant.index'))->assertRedirect(route('login'));
    }

    public function test_index_shows_only_own_history(): void
    {
        $user = User::factory()->create();
        AssistantMessage::factory()->for($user)->create(['content' => 'Moje pytanie?']);
        AssistantMessage::factory()->create(['content' => 'Cudze pytanie?']);

        $this->actingAs($user)
            ->get(route('assistant.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('assistant/Index')
                ->has('messages', 1)
                ->where('messages.0.content', 'Moje pytanie?')
                ->has('suggestions', 4));
    }

    public function test_fallback_answer_quotes_best_source_and_stores_citations(): void
    {
        config(['services.anthropic.key' => null]);
        Http::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('assistant.store'), ['question' => 'Czy muszę mówić o ciąży na rozmowie?'])
            ->assertRedirect(route('assistant.index'));

        Http::assertNothingSent();

        $answer = $user->assistantMessages()->where('role', AssistantRole::Assistant)->sole();
        $this->assertStringContainsString($this->interviewSource->content, $answer->content);
        $this->assertEquals([
            ['type' => 'legal', 'label' => 'Kodeks pracy, art. 22¹', 'url' => null],
            ['type' => 'article', 'label' => 'Blog: '.$this->article->title, 'url' => '/blog/rozmowa-w-ciazy'],
        ], $answer->citations);
        $this->assertDatabaseHas('assistant_messages', [
            'user_id' => $user->id,
            'role' => AssistantRole::User->value,
            'content' => 'Czy muszę mówić o ciąży na rozmowie?',
        ]);
    }

    public function test_claude_answer_keeps_only_citations_of_provided_sources(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => json_encode([
                    'answer' => 'Nie musisz. Pracodawca nie może wymagać takiej informacji.',
                    'citations' => ['L'.$this->interviewSource->id, 'L99999', 'A'.$this->article->id],
                ])]],
            ]),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('assistant.store'), ['question' => 'Czy muszę mówić o ciąży na rozmowie?'])
            ->assertRedirect(route('assistant.index'));

        $answer = $user->assistantMessages()->where('role', AssistantRole::Assistant)->sole();
        $this->assertSame('Nie musisz. Pracodawca nie może wymagać takiej informacji.', $answer->content);
        $this->assertEquals([
            ['type' => 'legal', 'label' => 'Kodeks pracy, art. 22¹', 'url' => null],
            ['type' => 'article', 'label' => 'Blog: '.$this->article->title, 'url' => '/blog/rozmowa-w-ciazy'],
        ], $answer->citations);
    }

    public function test_claude_failure_falls_back_to_extractive_answer(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error'], 500)]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('assistant.store'), ['question' => 'Czy muszę mówić o ciąży na rozmowie?']);

        $answer = $user->assistantMessages()->where('role', AssistantRole::Assistant)->sole();
        $this->assertStringContainsString($this->interviewSource->content, $answer->content);
    }

    public function test_question_is_required(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('assistant.store'), ['question' => ''])
            ->assertSessionHasErrors('question');

        $this->assertDatabaseCount('assistant_messages', 0);
    }
}
