<?php

namespace Tests\Feature\Api\Candidate;

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    private CandidateProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->profile = CandidateProfile::factory()->create();
    }

    public function test_candidate_uploads_and_removes_her_photo(): void
    {
        Sanctum::actingAs($this->profile->user);

        $this->post('/api/v1/candidate/profile/photo', ['photo' => UploadedFile::fake()->image('ja.png')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.photo_url', fn (?string $url): bool => str_contains((string) $url, "/api/v1/candidate-photos/{$this->profile->id}"));

        $path = (string) $this->profile->refresh()->photo_path;
        Storage::disk('local')->assertExists($path);

        $this->deleteJson('/api/v1/candidate/profile/photo')
            ->assertOk()
            ->assertJsonPath('data.photo_url', null);

        Storage::disk('local')->assertMissing($path);
        $this->assertNull($this->profile->refresh()->photo_path);
    }

    public function test_photo_validation_errors_are_returned_as_json(): void
    {
        Sanctum::actingAs($this->profile->user);

        $this->postJson('/api/v1/candidate/profile/photo', ['photo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }

    public function test_phone_is_saved_through_the_privacy_endpoint(): void
    {
        Sanctum::actingAs($this->profile->user);

        $this->patchJson('/api/v1/candidate/profile/privacy', ['phone' => '+48 600-100-200'])
            ->assertOk()
            ->assertJsonPath('data.phone', '+48 600 100 200');

        $this->patchJson('/api/v1/candidate/profile/privacy', ['phone' => '12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_only_candidates_manage_a_photo(): void
    {
        $this->postJson('/api/v1/candidate/profile/photo')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->employer()->create());

        $this->deleteJson('/api/v1/candidate/profile/photo')->assertForbidden();
    }
}
