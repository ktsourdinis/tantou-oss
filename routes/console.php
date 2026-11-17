<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tantou:prune-pending-registrations')->everyFifteenMinutes();
Schedule::command('tantou:telegram-poll --once')->everyFiveSeconds()->withoutOverlapping(10);
