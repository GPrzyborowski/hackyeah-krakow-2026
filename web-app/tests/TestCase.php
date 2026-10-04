<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Turn employer e-mail verification back on (it is switched off for the demo) so its flow can be tested.
     */
    protected function requireEmployerEmailVerification(): void
    {
        config()->set('auth.verify_employer_emails', true);
    }

    /**
     * Turn the 2FA login challenge back on (it is switched off for the demo) so its flow can be tested.
     */
    protected function enableTwoFactorChallenge(): void
    {
        config()->set('fortify.two_factor_challenge', true);
        config()->set('fortify.pipelines.login', null);
    }
}
