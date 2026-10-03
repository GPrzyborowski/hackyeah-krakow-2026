<?php

namespace Tests\Feature;

use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CandidatePhotoAccessTest extends TestCase
{
    use RefreshDatabase;

    private CandidateProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::disk('local')->put('photos/marta.jpg', 'jpeg-bytes');
        $this->profile = CandidateProfile::factory()->published()->create(['photo_path' => 'photos/marta.jpg']);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function viewers(): array
    {
        return [
            'the candidate herself' => ['self', 200],
            'company with an accepted invitation' => ['accepted company', 200],
            'company with a pending invitation' => ['pending company', 404],
            'company with a declined invitation' => ['declined company', 404],
            'unrelated company' => ['other company', 404],
            'another candidate' => ['other candidate', 404],
        ];
    }

    #[DataProvider('viewers')]
    public function test_web_photo_endpoint_access(string $viewer, int $expectedStatus): void
    {
        $response = $this->actingAs($this->viewer($viewer))->get(route('candidate-photos.show', $this->profile));

        $response->assertStatus($expectedStatus);

        if ($expectedStatus === 200) {
            $this->assertSame('jpeg-bytes', $response->streamedContent());
            $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        }
    }

    #[DataProvider('viewers')]
    public function test_api_photo_endpoint_access(string $viewer, int $expectedStatus): void
    {
        Sanctum::actingAs($this->viewer($viewer));

        $this->getJson("/api/v1/candidate-photos/{$this->profile->id}")->assertStatus($expectedStatus);
    }

    public function test_guests_are_redirected_on_web_and_get_401_on_the_api(): void
    {
        $this->get(route('candidate-photos.show', $this->profile))->assertRedirect(route('login'));
        $this->getJson("/api/v1/candidate-photos/{$this->profile->id}")->assertUnauthorized();
    }

    public function test_profile_without_photo_returns_404_even_to_its_owner(): void
    {
        $this->profile->forceFill(['photo_path' => null])->save();

        $this->actingAs($this->profile->user)->get(route('candidate-photos.show', $this->profile))->assertNotFound();
    }

    private function viewer(string $viewer): User
    {
        return match ($viewer) {
            'self' => $this->profile->user,
            'accepted company' => $this->companyMemberWithInvitation(accept: true),
            'pending company' => $this->companyMemberWithInvitation(),
            'declined company' => $this->companyMemberWithInvitation(decline: true),
            'other company' => User::factory()->employer()->create(),
            'other candidate' => CandidateProfile::factory()->create()->user,
        };
    }

    private function companyMemberWithInvitation(bool $accept = false, bool $decline = false): User
    {
        $company = Company::factory()->create();
        $invitation = Invitation::factory()
            ->for(JobOffer::factory()->published()->for($company))
            ->for($this->profile)
            ->create();

        if ($accept) {
            $invitation->accept();
        }

        if ($decline) {
            $invitation->decline();
        }

        return User::factory()->employer($company)->create();
    }
}
