<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The command runs migrate:fresh itself, so this test cannot use RefreshDatabase;
 * it leaves an empty, migrated schema behind for the tests that follow.
 */
class DemoResetCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true]);
        RefreshDatabaseState::$migrated = true;

        parent::tearDown();
    }

    public function test_it_rebuilds_demo_data_without_mail_notifications_or_uploads(): void
    {
        Mail::fake();
        Storage::fake('local');
        Storage::disk('local')->put('cvs/old-upload.pdf', 'cv');
        Storage::disk('local')->put('keep/other.txt', 'keep');

        $this->artisan('momjobs:demo-reset', ['--force' => true])
            ->expectsOutputToContain('marta@momjobs.test')
            ->expectsOutputToContain('hr@zielonebiuro.test')
            ->assertSuccessful();

        $this->assertDatabaseHas(User::class, ['email' => 'marta@momjobs.test']);
        $this->assertDatabaseHas(User::class, ['email' => 'hr@zielonebiuro.test']);
        $this->assertSame(0, DatabaseNotification::query()->count());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Storage::disk('local')->assertMissing('cvs/old-upload.pdf');
        Storage::disk('local')->assertExists('keep/other.txt');
    }

    public function test_it_refuses_to_run_in_production_without_force(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('momjobs:demo-reset', ['--no-interaction' => true])
            ->expectsOutputToContain('Refusing to reset demo data in production')
            ->doesntExpectOutputToContain('Demo data restored')
            ->assertFailed();
    }
}
