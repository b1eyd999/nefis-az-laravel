<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Whether the shop's own timed work is running.
 *
 * There is no SSH on this hosting and nothing in the application can start
 * itself: every scheduled job — an abandoned order giving its materials back,
 * anything added later — waits on one cPanel cron entry calling
 * `artisan schedule:run`. If that entry is missing, or the account's PHP path
 * changes, nothing fails loudly: the jobs simply never happen.
 *
 * So the scheduler writes the time each minute, and the admin reads it here.
 */
class Scheduler
{
    /** How long the scheduler may stay silent before it is treated as stopped. */
    public const QUIET_MINUTES = 15;

    public static function lastRun(): ?CarbonInterface
    {
        $seen = Setting::get(Setting::SCHEDULE_SEEN);

        if (blank($seen)) {
            return null;
        }

        try {
            return Carbon::parse($seen);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function isRunning(): bool
    {
        return (bool) self::lastRun()?->gt(now()->subMinutes(self::QUIET_MINUTES));
    }

    /**
     * When the owner wants the morning note, as HH:MM.
     *
     * Read here rather than in routes/console.php, because that file is
     * loaded on every console boot — including the very first `migrate`,
     * when there is no settings table yet to ask. Asked badly, it took the
     * whole console down with "no such table: settings".
     */
    public static function morningAt(): string
    {
        $default = '09:00';

        try {
            $at = (string) Setting::get(Setting::MORNING_NOTE_AT);
        } catch (\Throwable) {
            return $default;
        }

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $at) ? $at : $default;
    }

    /**
     * The line to paste into cPanel → Cron Jobs, built from this very
     * installation: its own PHP version and its own path, so it cannot be
     * copied wrong. cPanel's own help on that page names the binary this way.
     */
    public static function cronCommand(): string
    {
        return sprintf('/usr/local/bin/ea-php%d%d %s schedule:run >/dev/null 2>&1',
            PHP_MAJOR_VERSION, PHP_MINOR_VERSION, base_path('artisan'));
    }
}
