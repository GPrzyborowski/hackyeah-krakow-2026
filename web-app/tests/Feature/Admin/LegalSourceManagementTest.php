<?php

namespace Tests\Feature\Admin;

use App\Models\LegalSource;
use App\Models\User;
use App\Services\Ai\KnowledgeRetriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegalSourceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_guests_are_redirected_and_non_admins_are_forbidden(): void
    {
        $source = LegalSource::factory()->create();

        $this->get(route('admin.legal-sources.index'))->assertRedirect(route('login'));
        $this->delete(route('admin.legal-sources.destroy', $source))->assertRedirect(route('login'));

        foreach ([User::factory()->create(), User::factory()->employer()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.legal-sources.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.legal-sources.edit', $source))->assertForbidden();
            $this->actingAs($user)->post(route('admin.legal-sources.store'), $this->validPayload())->assertForbidden();
            $this->actingAs($user)->put(route('admin.legal-sources.update', $source), $this->validPayload())->assertForbidden();
            $this->actingAs($user)->delete(route('admin.legal-sources.destroy', $source))->assertForbidden();
        }

        $this->assertModelExists($source);
        $this->assertDatabaseCount('legal_sources', 1);
    }

    public function test_index_groups_sources_by_act_in_natural_article_order(): void
    {
        LegalSource::factory()->create(['act' => 'Kodeks pracy', 'article' => '183a']);
        LegalSource::factory()->create(['act' => 'Kodeks pracy', 'article' => '22¹']);
        LegalSource::factory()->create(['act' => 'Ustawa o świadczeniach', 'article' => '29']);

        $this->actingAs($this->admin)
            ->get(route('admin.legal-sources.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/legal-sources/Index')
                ->has('groups', 2)
                ->where('groups.0.act', 'Kodeks pracy')
                ->where('groups.0.sources.0.article', '22¹')
                ->where('groups.0.sources.1.article', '183a')
                ->where('groups.1.act', 'Ustawa o świadczeniach'));
    }

    public function test_admin_creates_a_source_from_comma_separated_keywords(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.legal-sources.store'), $this->validPayload([
                'keywords' => ' pytanie o ciążę, rozmowa kwalifikacyjna,, Pytanie o ciążę ,dyskryminacja ',
            ]))
            ->assertRedirect(route('admin.legal-sources.index'));

        $source = LegalSource::query()->sole();

        $this->assertSame('22¹', $source->article);
        $this->assertSame(['pytanie o ciążę', 'rozmowa kwalifikacyjna', 'dyskryminacja'], $source->keywords);
    }

    public function test_keywords_sent_as_a_list_are_trimmed(): void
    {
        $source = LegalSource::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.legal-sources.update', $source), $this->validPayload([
                'title' => 'Nowy tytuł',
                'keywords' => [' urlop rodzicielski ', ''],
            ]))
            ->assertRedirect(route('admin.legal-sources.index'));

        $source->refresh();
        $this->assertSame('Nowy tytuł', $source->title);
        $this->assertSame(['urlop rodzicielski'], $source->keywords);
    }

    public function test_required_fields_and_keyword_types_are_validated(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.legal-sources.store'), [
                'act' => '',
                'article' => '',
                'title' => '',
                'content' => '',
                'keywords' => [['nested']],
            ])
            ->assertSessionHasErrors(['act', 'article', 'title', 'content', 'keywords.0']);

        $this->assertDatabaseCount('legal_sources', 0);
    }

    public function test_admin_deletes_a_source(): void
    {
        $source = LegalSource::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.legal-sources.destroy', $source))
            ->assertRedirect(route('admin.legal-sources.index'));

        $this->assertModelMissing($source);
    }

    public function test_a_created_source_is_used_by_the_assistant_retrieval(): void
    {
        LegalSource::factory()->create([
            'act' => 'Kodeks cywilny',
            'article' => '1',
            'title' => 'Zakres ustawy',
            'content' => 'Ustawa reguluje stosunki cywilnoprawne.',
            'keywords' => ['umowa sprzedaży'],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.legal-sources.store'), $this->validPayload([
                'act' => 'Kodeks pracy',
                'article' => '1867',
                'title' => 'Praca zdalna okazjonalna',
                'content' => 'Pracownik może wykonywać pracę zdalną okazjonalnie do 24 dni w roku.',
                'keywords' => 'praca zdalna okazjonalna, home office',
            ]))
            ->assertRedirect();

        $sources = app(KnowledgeRetriever::class)->legalSources('Ile dni w roku przysługuje mi praca zdalna okazjonalna?');

        $this->assertSame('Praca zdalna okazjonalna', $sources->first()?->title);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'act' => 'Kodeks pracy',
            'article' => '22¹',
            'title' => 'Dane osobowe kandydata',
            'content' => 'Pracodawca może żądać od kandydata wyłącznie określonych danych.',
            'keywords' => ['dane osobowe'],
            ...$overrides,
        ];
    }
}
