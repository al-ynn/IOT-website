<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('automation:run-schedules')->everyMinute()->withoutOverlapping();
Schedule::command('provisioning:expire')->everyMinute()->withoutOverlapping();
Schedule::command('revisions:process-reminders --limit=100')->everyMinute()->withoutOverlapping();
Schedule::command('platform:apply-retention')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('notifications:process-outbox --limit=100')->everyMinute()->withoutOverlapping();
Schedule::command('revisions:prune-idempotency --limit=1000')->dailyAt('03:10')->withoutOverlapping();
