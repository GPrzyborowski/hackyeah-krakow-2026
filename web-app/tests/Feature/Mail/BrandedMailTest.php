<?php

namespace Tests\Feature\Mail;

use App\Models\CandidateProfile;
use App\Models\Conversation;
use App\Models\Invitation;
use App\Models\JobSharePair;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Notifications\InvitationAccepted;
use App\Notifications\InvitationReceived;
use App\Notifications\NewsletterConfirmation;
use App\Notifications\PairInvitationReceived;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class BrandedMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_notifications_render_in_polish_with_mumjobs_branding(): void
    {
        $user = User::factory()->create();
        $mails = [
            (new InvitationReceived(Invitation::factory()->create()))->toMail($user),
            (new InvitationAccepted(Conversation::factory()->create()))->toMail($user),
            (new PairInvitationReceived(JobSharePair::factory()->create(), CandidateProfile::factory()->create()))->toMail($user),
            (new NewsletterConfirmation)->toMail(NewsletterSubscriber::factory()->create()),
        ];

        foreach ($mails as $mail) {
            $this->assertBrandedPolishMail($mail);
        }
    }

    public function test_verify_email_mail_is_polish_and_branded(): void
    {
        $mail = (new VerifyEmail)->toMail(User::factory()->unverified()->create());

        $this->assertSame('Potwierdź swój adres e-mail w mumjobs', $mail->subject);
        $this->assertSame('Potwierdź adres e-mail', $mail->actionText);
        $this->assertBrandedPolishMail($mail);
    }

    public function test_reset_password_mail_is_polish_and_branded(): void
    {
        $mail = (new ResetPassword('token'))->toMail(User::factory()->create());

        $this->assertSame('Ustaw nowe hasło w mumjobs', $mail->subject);
        $this->assertSame('Ustaw nowe hasło', $mail->actionText);
        $this->assertStringContainsString('Link do zmiany hasła będzie ważny przez 60 minut.', (string) $mail->render());
        $this->assertBrandedPolishMail($mail);
    }

    private function assertBrandedPolishMail(MailMessage $mail): void
    {
        $html = (string) $mail->render();

        $this->assertStringContainsString('Cześć!', $html);
        $this->assertStringContainsString('Pozdrawiamy,', $html);
        $this->assertStringContainsString('zespół mumjobs', $html);
        $this->assertStringContainsString('praca dla przyszłych i obecnych mam', $html);
        $this->assertStringContainsString(config('app.url').'/images/logo.png', $html);
        $this->assertStringContainsString('#143f3b', $html);
        $this->assertStringContainsString('skopiuj poniższy adres URL', $html);
        $this->assertStringNotContainsString('Laravel', $html);
        $this->assertStringNotContainsString('Hello!', $html);
        $this->assertStringNotContainsString('Regards', $html);
    }
}
