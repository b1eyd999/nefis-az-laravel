<?php

namespace App\Console\Commands;

use App\Support\Backup;
use App\Support\Telegram;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * A copy of the database, made each night.
 *
 * Everything the shop knows — the orders, the designs, the accounts, the
 * figures the owner reorders materials from — is in one database on a shared
 * host. There was no copy of it anywhere.
 *
 * Two things here are deliberate, and both were got wrong first:
 *
 *  - **Nothing outside the try.** Asking whether backups are switched on is
 *    itself a database read (a setting, through the cache table), so on the
 *    night the database is the thing that failed, the question threw before
 *    the guard and the whole job died in silence — cron sends its output to
 *    /dev/null. The failure the alert exists for was the one failure it
 *    could not report.
 *  - **The log first, Telegram after.** Telegram's own token is a setting,
 *    which is another database read; if that throws, the log entry must
 *    already be written.
 *
 * Neither covers a job that never starts at all. That is caught from the
 * other side, by `shop:morning`, which looks at the folder rather than at
 * the database and says so when the newest copy has gone stale.
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--keep= : how many copies to keep}';

    protected $description = 'Write a copy of the database and sweep the old ones';

    public function handle(): int
    {
        try {
            if (! self::switchedOn() && $this->option('keep') === null) {
                $this->line('Backups are switched off.');

                return self::SUCCESS;
            }

            $path = Backup::make();

            // Written, and whole. A truncated file must never be counted as a
            // copy, or the sweep below would let it push a good one out.
            if (! Backup::whole($path)) {
                @unlink($path);

                throw new \RuntimeException('The copy came out unfinished and was thrown away');
            }
        } catch (\Throwable $e) {
            Log::error('Verilənlər bazasının kopyası alınmadı: ' . $e->getMessage());
            // The owner hears about this one: a backup that quietly stopped
            // working is worse than no backup, because he thinks he has one.
            self::shout('🔴 <b>Baza kopyası alınmadı</b>' . "\n\n" . e($e->getMessage()));
            $this->error('Failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        /* The copy is written and whole; everything from here is tidying.
           How many to keep is itself a setting, which is another database
           read — and a database that will not answer must not turn a good
           night into a failed one, nor throw past the guard above. */
        $swept = 0;
        try {
            $kept = $this->option('keep') !== null ? max(1, (int) $this->option('keep')) : null;
            $swept = Backup::sweep($kept);
        } catch (\Throwable $e) {
            Log::warning('Köhnə kopyalar silinmədi: ' . $e->getMessage());
        }

        $this->line('Wrote ' . basename($path) . ' (' . number_format(filesize($path) / 1024, 1) . ' KB)'
            . ($swept > 0 ? ', swept ' . $swept . ' old one(s)' : ''));

        return self::SUCCESS;
    }

    /**
     * Whether the owner wants nightly copies.
     *
     * A database that will not answer is not an answer of "no": the setting
     * ships switched on, and a night when nothing can be read is exactly the
     * night to try.
     */
    private static function switchedOn(): bool
    {
        try {
            return Backup::enabled();
        } catch (\Throwable) {
            return true;
        }
    }

    /** Say it out loud if anything will carry it; never throw trying. */
    private static function shout(string $text): void
    {
        try {
            Telegram::send($text);
        } catch (\Throwable $e) {
            Log::error('Baza xəbərdarlığı göndərilmədi: ' . $e->getMessage());
        }
    }
}
