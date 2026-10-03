<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('momjobs:demo-reset {--force : Skip the confirmation and allow running in production}')]
#[Description('Rebuild the database with fresh demo data, clear demo uploads and print demo logins')]
class DemoResetCommand extends Command
{
    private const string DEMO_PASSWORD = 'password';

    private const string CV_DIRECTORY = 'cvs';

    private const string JOB_SHARING_CANDIDATE_EMAIL = 'marta@momjobs.test';

    private const string JOB_SHARING_EMPLOYER_EMAIL = 'hr@zielonebiuro.test';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isForced = (bool) $this->option('force');

        if (app()->isProduction() && ! $isForced) {
            $this->components->error('Refusing to reset demo data in production. Pass --force if you really mean it.');

            return self::FAILURE;
        }

        if (! $isForced && $this->input->isInteractive()
            && ! $this->confirm('This wipes the whole database and demo uploads. Continue?')) {
            $this->components->info('Demo reset cancelled.');

            return self::FAILURE;
        }

        $this->rebuildDatabaseWithoutSendingMail();

        Storage::disk('local')->deleteDirectory(self::CV_DIRECTORY);
        $this->call('cache:clear');

        $this->printDemoLogins();
        $this->printJobSharingClickPath();

        $this->components->info('Demo data restored.');

        return self::SUCCESS;
    }

    /**
     * Seeding triggers observers that notify by mail, so mail goes to the array
     * transport for the duration of the rebuild (no Mailpit spam, no failure when it is down).
     */
    private function rebuildDatabaseWithoutSendingMail(): void
    {
        $previousMailer = config('mail.default');
        config(['mail.default' => 'array']);

        try {
            $this->call('migrate:fresh', [
                '--seed' => true,
                '--seeder' => DatabaseSeeder::class,
                '--force' => true,
            ]);
        } finally {
            config(['mail.default' => $previousMailer]);
        }
    }

    private function printDemoLogins(): void
    {
        $demoUsers = User::query()
            ->where(fn ($query) => $query
                ->whereIn('role', [UserRole::Admin, UserRole::Employer])
                ->orWhere('email', 'like', '%@momjobs.test'))
            ->orderBy('role')
            ->orderBy('id')
            ->get(['name', 'email', 'role']);

        $this->newLine();
        $this->table(
            ['Role', 'Name', 'Email', 'Password'],
            $demoUsers->map(fn (User $user): array => [
                $user->role->value,
                $user->name,
                $user->email,
                self::DEMO_PASSWORD,
            ])->all(),
        );
    }

    private function printJobSharingClickPath(): void
    {
        $candidateEmail = self::JOB_SHARING_CANDIDATE_EMAIL;
        $employerEmail = self::JOB_SHARING_EMPLOYER_EMAIL;

        $this->components->twoColumnDetail('<fg=green;options=bold>Job-sharing demo path</>');
        $this->components->twoColumnDetail("1. {$candidateEmail}", 'Job sharing → para → Akceptuję podział → Wyślij pracodawcy');
        $this->components->twoColumnDetail("2. {$employerEmail}", 'Ogłoszenia → Pary job-sharing → Zaproś parę');
        $this->components->twoColumnDetail("3. {$candidateEmail}", 'Zaproszenia → Przyjmij');
        $this->newLine();
    }
}
