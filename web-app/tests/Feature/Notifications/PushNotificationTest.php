<?php

namespace Tests\Feature\Notifications;

use App\Models\Company;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\CompanyVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const string TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const string SEND_URL = 'https://fcm.googleapis.com/v1/projects/momjobs-test/messages:send';

    private string $publicKey;

    private Company $company;

    private User $employer;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $this->publicKey = openssl_pkey_get_details($key)['key'];

        config([
            'services.fcm.project_id' => null,
            'services.fcm.credentials' => json_encode([
                'type' => 'service_account',
                'project_id' => 'momjobs-test',
                'client_email' => 'push@momjobs-test.iam.gserviceaccount.com',
                'private_key' => $privateKey,
                'token_uri' => self::TOKEN_URL,
            ]),
        ]);

        Http::preventStrayRequests();

        $this->company = Company::factory()->create(['name' => 'Zielone Biuro']);
        $this->employer = User::factory()->employer($this->company)->create();
    }

    public function test_push_is_sent_to_every_device_with_a_signed_token_request_and_a_cached_access_token(): void
    {
        DeviceToken::factory()->for($this->employer)->create(['token' => 'device-a', 'platform' => 'android']);
        DeviceToken::factory()->for($this->employer)->create(['token' => 'device-b', 'platform' => 'ios']);
        $this->fakeFcm();

        $this->employer->notify(new CompanyVerified($this->company));
        $this->employer->notify(new CompanyVerified($this->company));

        Http::assertSentCount(5);
        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== self::TOKEN_URL) {
                return false;
            }

            [$header, $claims, $signature] = explode('.', $request['assertion']);
            $payload = json_decode($this->base64UrlDecode($claims), true);

            return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && json_decode($this->base64UrlDecode($header), true)['alg'] === 'RS256'
                && $payload['iss'] === 'push@momjobs-test.iam.gserviceaccount.com'
                && $payload['scope'] === 'https://www.googleapis.com/auth/firebase.messaging'
                && $payload['aud'] === self::TOKEN_URL
                && openssl_verify("{$header}.{$claims}", $this->base64UrlDecode($signature), $this->publicKey, OPENSSL_ALGO_SHA256) === 1;
        });
        $this->assertSame(1, Http::recorded(fn (Request $request): bool => $request->url() === self::TOKEN_URL)->count());
        $this->assertSame(
            ['device-a', 'device-b', 'device-a', 'device-b'],
            Http::recorded(fn (Request $request): bool => $request->url() === self::SEND_URL)
                ->map(fn (array $pair): string => $pair[0]['message']['token'])
                ->all(),
        );
    }

    public function test_push_payload_has_polish_title_body_and_string_data(): void
    {
        DeviceToken::factory()->for($this->employer)->create(['token' => 'device-a']);
        $this->fakeFcm();

        $this->employer->notify(new CompanyVerified($this->company));

        $notificationId = $this->employer->notifications()->sole()->id;
        Http::assertSent(fn (Request $request): bool => $request->url() === self::SEND_URL
            && $request->hasHeader('Authorization', 'Bearer access-123')
            && $request['message']['token'] === 'device-a'
            && $request['message']['notification'] === [
                'title' => 'Twoja firma została zweryfikowana',
                'body' => 'MomJobs potwierdził dane firmy Zielone Biuro. Kandydatki zobaczą przy Waszych ofertach odznakę „Zweryfikowana firma”.',
            ]
            && $request['message']['data'] === [
                'kind' => 'company_verified',
                'notification_id' => $notificationId,
                'company_id' => (string) $this->company->id,
                'url' => route('employer.company.edit', absolute: false),
            ]);
    }

    public function test_tokens_reported_as_unregistered_or_invalid_are_deleted(): void
    {
        DeviceToken::factory()->for($this->employer)->create(['token' => 'gone']);
        DeviceToken::factory()->for($this->employer)->create(['token' => 'malformed']);
        DeviceToken::factory()->for($this->employer)->create(['token' => 'healthy']);
        DeviceToken::factory()->for($this->employer)->create(['token' => 'throttled']);

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'access-123', 'expires_in' => 3599]),
            self::SEND_URL => function (Request $request) {
                return match ($request['message']['token']) {
                    'gone' => $this->fcmError(404, 'NOT_FOUND', 'UNREGISTERED'),
                    'malformed' => $this->fcmError(400, 'INVALID_ARGUMENT', 'INVALID_ARGUMENT'),
                    'throttled' => $this->fcmError(429, 'RESOURCE_EXHAUSTED', 'QUOTA_EXCEEDED'),
                    default => Http::response(['name' => 'projects/momjobs-test/messages/1']),
                };
            },
        ]);

        $this->employer->notify(new CompanyVerified($this->company));

        $this->assertEqualsCanonicalizing(['healthy', 'throttled'], DeviceToken::pluck('token')->all());
    }

    public function test_channel_is_a_no_op_without_fcm_configuration(): void
    {
        config(['services.fcm.credentials' => null]);
        DeviceToken::factory()->for($this->employer)->create();

        $this->employer->notify(new CompanyVerified($this->company));

        Http::assertNothingSent();
        $this->assertSame(1, $this->employer->notifications()->count());
    }

    public function test_push_is_skipped_when_the_user_turned_it_off(): void
    {
        $this->employer->forceFill(['push_enabled' => false])->save();
        DeviceToken::factory()->for($this->employer)->create();

        $notification = new CompanyVerified($this->company);
        $this->employer->notify($notification);

        $this->assertSame(['database'], $notification->via($this->employer));
        Http::assertNothingSent();
    }

    public function test_push_channel_is_added_only_for_users_with_a_device(): void
    {
        $withoutDevice = User::factory()->employer($this->company)->create();
        DeviceToken::factory()->for($this->employer)->create();
        $notification = new CompanyVerified($this->company);

        $this->assertSame(['database', FcmChannel::class], $notification->via($this->employer));
        $this->assertSame(['database'], $notification->via($withoutDevice));
    }

    private function fakeFcm(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'access-123', 'expires_in' => 3599, 'token_type' => 'Bearer']),
            self::SEND_URL => Http::response(['name' => 'projects/momjobs-test/messages/1']),
        ]);
    }

    private function fcmError(int $status, string $googleStatus, string $fcmErrorCode): mixed
    {
        return Http::response([
            'error' => [
                'code' => $status,
                'message' => 'Error',
                'status' => $googleStatus,
                'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => $fcmErrorCode]],
            ],
        ], $status);
    }

    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}
