<?php

namespace App\Notifications;

use App\Models\Company;
use App\Notifications\Concerns\SendsPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the company's members that a MomJobs administrator verified their company (NIP checked).
 */
class CompanyVerified extends Notification
{
    use Queueable, SendsPush;

    public function __construct(public Company $company) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->withPush(['database'], $notifiable);
    }

    /**
     * @return array{kind: string, title: string, body: string|null, url: string, company_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'company_verified',
            'title' => 'Twoja firma została zweryfikowana',
            'body' => "MomJobs potwierdził dane firmy {$this->company->name}. Kandydatki zobaczą przy Waszych ofertach odznakę „Zweryfikowana firma”.",
            'url' => route('employer.company.edit', absolute: false),
            'company_id' => $this->company->id,
        ];
    }
}
