<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sanctum:prune-expired --hours=24')->daily();

Schedule::command('mumjobs:send-job-alerts')
    ->weeklyOn(1, '8:00')
    ->timezone('Europe/Warsaw')
    ->withoutOverlapping();

Schedule::command('mumjobs:send-newsletter')
    ->weeklyOn(1, '9:00')
    ->timezone('Europe/Warsaw')
    ->withoutOverlapping();

Schedule::command('mumjobs:prune-moderation-events')
    ->dailyAt('3:00')
    ->timezone('Europe/Warsaw')
    ->withoutOverlapping();
