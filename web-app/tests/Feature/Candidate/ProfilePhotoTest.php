<?php

namespace Tests\Feature\Candidate;

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private CandidateProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->withoutVite();
        $this->candidate = User::factory()->create(['name' => 'Marta Kowalska']);
        $this->profile = $this->candidate->candidateProfile()->create(['onboarding_step' => 3]);
    }

    public function test_candidate_uploads_a_photo_to_the_private_disk(): void
    {
        $this->actingAs($this->candidate)
            ->post(route('candidate.onboarding.photo.store'), ['photo' => UploadedFile::fake()->image('ja.jpg', 400, 400)])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $path = $this->profile->refresh()->photo_path;
        $this->assertNotNull($path);
        $this->assertStringStartsWith('photos/', $path);
        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->candidate->fresh())
            ->get(route('candidate.onboarding.show', ['step' => 4]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile.photo_url', $this->profile->photoUrl())
                ->where('auth.user.avatar', $this->profile->photoUrl()));
    }

    public function test_a_new_photo_replaces_and_deletes_the_previous_file(): void
    {
        $this->actingAs($this->candidate)->post(route('candidate.onboarding.photo.store'), ['photo' => UploadedFile::fake()->image('a.png')]);
        $firstPath = (string) $this->profile->refresh()->photo_path;

        $this->actingAs($this->candidate)->post(route('candidate.onboarding.photo.store'), ['photo' => UploadedFile::fake()->image('b.webp')]);

        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists((string) $this->profile->refresh()->photo_path);
    }

    public function test_candidate_removes_her_photo(): void
    {
        Storage::disk('local')->put('photos/marta.jpg', 'image');
        $this->profile->forceFill(['photo_path' => 'photos/marta.jpg'])->save();

        $this->actingAs($this->candidate)
            ->delete(route('candidate.onboarding.photo.destroy'))
            ->assertRedirect();

        $this->assertNull($this->profile->refresh()->photo_path);
        Storage::disk('local')->assertMissing('photos/marta.jpg');
    }

    /**
     * @return array<string, array{0: UploadedFile|string|null}>
     */
    public static function invalidPhotos(): array
    {
        return [
            'missing' => [null],
            'pdf' => [UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')],
            'gif' => [UploadedFile::fake()->image('anim.gif')],
            'too large' => [UploadedFile::fake()->image('big.jpg')->size(3073)],
        ];
    }

    #[DataProvider('invalidPhotos')]
    public function test_photo_must_be_a_jpg_png_or_webp_up_to_3_mb(UploadedFile|string|null $photo): void
    {
        $this->actingAs($this->candidate)
            ->post(route('candidate.onboarding.photo.store'), ['photo' => $photo])
            ->assertSessionHasErrors('photo');

        $this->assertNull($this->profile->refresh()->photo_path);
    }

    public function test_jpeg_metadata_is_stripped_on_upload(): void
    {
        $jpeg = UploadedFile::fake()->image('ja.jpg', 50, 50);
        $withComment = $this->injectJpegComment((string) file_get_contents($jpeg->getRealPath()), 'GPS-SECRET-LOCATION');
        file_put_contents($jpeg->getRealPath(), $withComment);

        $this->actingAs($this->candidate)->post(route('candidate.onboarding.photo.store'), ['photo' => $jpeg]);

        $stored = (string) Storage::disk('local')->get((string) $this->profile->refresh()->photo_path);
        $this->assertStringNotContainsString('GPS-SECRET-LOCATION', $stored);
    }

    public function test_phone_is_normalized_and_saved_with_the_privacy_settings(): void
    {
        $this->actingAs($this->candidate)
            ->patch(route('candidate.onboarding.privacy'), ['phone' => '600-100-200'])
            ->assertSessionHasNoErrors();

        $this->assertSame('+48 600 100 200', $this->profile->refresh()->phone);

        $this->actingAs($this->candidate)->patch(route('candidate.onboarding.privacy'), ['phone' => '']);

        $this->assertNull($this->profile->refresh()->phone);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validPhones(): array
    {
        return [
            'plain' => ['600100200'],
            'with prefix' => ['+48600100200'],
            'spaces' => ['+48 600 100 200'],
        ];
    }

    #[DataProvider('validPhones')]
    public function test_polish_phone_formats_are_accepted(string $phone): void
    {
        $this->actingAs($this->candidate)
            ->patch(route('candidate.onboarding.privacy'), ['phone' => $phone])
            ->assertSessionHasNoErrors();

        $this->assertSame('+48 600 100 200', $this->profile->refresh()->phone);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidPhones(): array
    {
        return [
            'too short' => ['60010020'],
            'too long' => ['6001002001'],
            'foreign prefix' => ['+49 600 100 200'],
            'letters' => ['600 abc 200'],
        ];
    }

    #[DataProvider('invalidPhones')]
    public function test_invalid_phone_is_rejected(string $phone): void
    {
        $this->actingAs($this->candidate)
            ->patch(route('candidate.onboarding.privacy'), ['phone' => $phone])
            ->assertSessionHasErrors('phone');

        $this->assertNull($this->profile->refresh()->phone);
    }

    public function test_deleting_the_account_removes_the_photo_and_cv_files(): void
    {
        Storage::disk('local')->put('photos/marta.jpg', 'image');
        Storage::disk('local')->put('cvs/marta.pdf', 'cv');
        $this->profile->forceFill(['photo_path' => 'photos/marta.jpg', 'cv_path' => 'cvs/marta.pdf'])->save();

        $this->candidate->delete();

        Storage::disk('local')->assertMissing('photos/marta.jpg');
        Storage::disk('local')->assertMissing('cvs/marta.pdf');
    }

    public function test_employers_cannot_upload_a_candidate_photo(): void
    {
        $this->actingAs(User::factory()->employer()->create())
            ->post(route('candidate.onboarding.photo.store'), ['photo' => UploadedFile::fake()->image('a.jpg')])
            ->assertForbidden();
    }

    /**
     * Insert a JPEG COM segment right after the SOI marker (stand-in for EXIF metadata).
     */
    private function injectJpegComment(string $jpeg, string $comment): string
    {
        $segment = "\xFF\xFE".pack('n', strlen($comment) + 2).$comment;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }
}
