<?php

namespace App\Console\Commands;

use App\Models\Market;
use Illuminate\Console\Command;

/**
 * Refreshes the chocolate bars and their prices from every shop whose
 * website can be read (Araz Market, …).
 *
 * Runs after every deploy (.cpanel.yml), and the admin can run it any time
 * from the Chocolates page. A failure only reports; the bars already known
 * keep their last prices.
 */
class SyncChocolates extends Command
{
    protected $signature = 'chocolates:sync';

    protected $description = 'Update the chocolate bars and their prices from the shops\' websites';

    public function handle(): int
    {
        // A fresh install has no rows yet; the importers make their shops.
        foreach (Market::IMPORTERS as $importer => $class) {
            try {
                $r = $class::sync();
                $this->info("Chocolates ({$importer}): {$r['found']} bars found, {$r['created']} new, {$r['updated']} updated, {$r['missing']} no longer listed.");
            } catch (\Throwable $e) {
                $this->warn("Chocolates ({$importer}) not synced: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
