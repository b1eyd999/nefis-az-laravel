<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nothing here runs until a cPanel cron calls `artisan schedule:run` every minute.
Schedule::command('orders:expire-unpaid')->hourly();
