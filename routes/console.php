<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Automated Durable Backup & Recovery Schedule
|--------------------------------------------------------------------------
|
| 1. Hourly: Atomic durable backup creation (MySQL + private storage)
| 2. Hourly (at :15): Replicate verified backup to secondary destination
| 3. Daily (02:00): Automated validation of backup archive checksums
| 4. Weekly (Sun 03:00): Isolated non-destructive disaster recovery restore
|
*/
Schedule::command('nsdcrm:backup:create --frequency=hourly')
    ->hourly()
    ->withoutOverlapping(30)
    ->onOneServer()
    ->runInBackground();

Schedule::command('nsdcrm:backup:replicate')
    ->hourlyAt(15)
    ->withoutOverlapping(30)
    ->onOneServer()
    ->runInBackground();

Schedule::command('nsdcrm:backup:validate')
    ->dailyAt('02:00')
    ->withoutOverlapping(30)
    ->onOneServer();

Schedule::command('nsdcrm:verify-recovery')
    ->weeklyOn(0, '03:00')
    ->withoutOverlapping(60)
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Operational Reminders & Low-Stock Alerts Schedule
|--------------------------------------------------------------------------
|
| Scan inventory thresholds, pending requisitions, and payment due dates
| every 4 hours during operational site working hours.
|
*/
Schedule::command('nsdcrm:send-reminders')
    ->everyFourHours()
    ->withoutOverlapping(15)
    ->onOneServer()
    ->runInBackground();
