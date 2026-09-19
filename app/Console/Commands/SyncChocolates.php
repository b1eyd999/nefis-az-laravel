<?php

namespace App\Console\Commands;

use App\Support\ArazMarket;
use Illuminate\Console\Command;

/**
 * Refreshes the chocolate bars and their prices from Araz Market.
 *
 * Runs after every deploy (.cpanel.yml), and the admin can run it any time
 * from the Chocolates page. A failure only reports; the bars already known
 * keep their last prices.
 */
class SyncChocolates extends Command
{
    protected $signature = 'chocolates:sync';

    protected $description = 'Update the 90-105 g chocolate bars and their prices from Araz Market';

    public function handle(): int
    {
        try {
            $r = ArazMarket::sync();
        } catch (\Throwable $e) {
            $this->warn('Chocolates not synced: ' . $e->getMessage());

            return self::SUCCESS;
        }

        $this->info("Chocolates: {$r['found']} bars found, {$r['created']} new, {$r['updated']} updated, {$r['missing']} no longer listed.");

        return self::SUCCESS;
    }
}
