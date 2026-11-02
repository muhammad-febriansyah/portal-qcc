<?php

use Illuminate\Foundation\DevCommands;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('qcc:notifications:send-overdue')
    ->dailyAt('08:00')
    ->timezone(config('app.timezone'));

Schedule::command('qcc:audit:prune')
    ->dailyAt('01:00')
    ->timezone(config('app.timezone'));

/*
| `composer run dev` replaces the framework's queue listener, which only listens to the
| default queue. AI team matching and AI scoring run on the `ai` queue (see config/scoring.php),
| so the listener must take both, or those jobs would wait for ever on a development machine.
*/
DevCommands::artisan('queue:listen --queue=ai,default --tries=1 --timeout=0', 'queue');
