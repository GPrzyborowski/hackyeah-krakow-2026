<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function legalPages(): array
    {
        return [
            'terms' => ['public.legal.terms', 'public/legal/Terms'],
            'privacy' => ['public.legal.privacy', 'public/legal/Privacy'],
            'contact' => ['public.legal.contact', 'public/legal/Contact'],
        ];
    }

    #[DataProvider('legalPages')]
    public function test_guest_can_view_legal_page(string $routeName, string $component): void
    {
        $this->get(route($routeName))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->whereType('contactEmail', 'string'),
            );
    }
}
