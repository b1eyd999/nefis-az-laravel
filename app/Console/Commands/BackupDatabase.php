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
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--keep= : how many copies to keep}';

    protected $description = 'Write a copy of the database and sweep the old ones';

    public function handle(): int
    {
        if (! Backup::enabled() && $this->option('keep') === null) {
            $this->line('Backups are switched off.');

            return self::SUCCESS;
        }

        try {
            $path = Backup::make();
        } catch (\Throwable $e) {
            Log::error('Verilənlər bazasının kopyası alınmadı: ' . $e->getMessage());
            // The owner hears about this one: a backup that quietly stopped
            // working is worse than no backup, because he thinks he has one.
            Telegram::send('🔴 <b>Baza kopyası alınmadı</b>' . "\n\n" . e($e->getMessage()));
            $this->error('Failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $kept = $this->option('keep') !== null ? max(1, (int) $this->option('keep')) : null;
        $swept = Backup::sweep($kept);

        $this->line('Wrote ' . basename($path) . ' (' . number_format(filesize($path) / 1024, 1) . ' KB)'
            . ($swept > 0 ? ', swept ' . $swept . ' old one(s)' : ''));

        return self::SUCCESS;
    }
}
