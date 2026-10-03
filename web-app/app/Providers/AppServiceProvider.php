<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Models\Invitation;
use App\Models\JobSharePair;
use App\Models\Message;
use App\Observers\ConversationObserver;
use App\Observers\InvitationObserver;
use App\Observers\JobSharePairObserver;
use App\Observers\MessageObserver;
use App\Services\Ai\ClaudeCvAnalyzer;
use App\Services\Ai\ClaudeMessageModerator;
use App\Services\Ai\CvAnalyzer;
use App\Services\Ai\KeywordCvAnalyzer;
use App\Services\Ai\KeywordMessageModerator;
use App\Services\Ai\MessageModerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CvAnalyzer::class, fn ($app) => $app->make(filled(config('services.anthropic.key')) ? ClaudeCvAnalyzer::class : KeywordCvAnalyzer::class));
        $this->app->bind(MessageModerator::class, fn ($app) => $app->make(filled(config('services.anthropic.key')) ? ClaudeMessageModerator::class : KeywordMessageModerator::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerObservers();
    }

    /**
     * Model observers that send in-app and e-mail notifications.
     */
    protected function registerObservers(): void
    {
        Invitation::observe(InvitationObserver::class);
        Conversation::observe(ConversationObserver::class);
        Message::observe(MessageObserver::class);
        JobSharePair::observe(JobSharePairObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
