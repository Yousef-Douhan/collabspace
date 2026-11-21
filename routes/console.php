<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run with `php artisan schedule:work` (dev) or a cron entry calling `schedule:run` every minute.
Schedule::command('meetings:send-reminders')->everyMinute()->withoutOverlapping();
Schedule::command('tasks:notify-overdue')->dailyAt('09:00');
