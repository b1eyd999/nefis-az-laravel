<?php

use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/* Nothing below runs on its own: a cPanel cron has to call
   `artisan schedule:run` every minute. This first line is the only way to tell
   from the admin whether that cron exists — see "Avtomatik işlər" in
   Tənzimləmələr, which also prints the line to paste. */
Schedule::call(fn () => Setting::put(Setting::SCHEDULE_SEEN, now()->toDateTimeString()))
    ->everyMinute()
    ->name('scheduler-heartbeat');

// An order that went to the bank and never came back: cancelled, and its
// paper, ribbon and card go back to the stock figures the owner reorders from.
Schedule::command('orders:expire-unpaid')->hourly();

/* The photographs of boxes nobody ordered. A customer uploads his picture,
   changes his mind and leaves, and nothing ever points at that file again —
   so it is swept, but only after a month and only when no order, basket or
   link mentions it anywhere. */
Schedule::command('uploads:sweep')->dailyAt('04:20');
