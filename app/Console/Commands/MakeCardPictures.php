<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * The catalogue cards are about 160 points wide on a phone and the posters
 * are 1080 across, so a visitor used to download roughly twelve times the
 * pixels his screen paints — some two megabytes on the home page alone.
 *
 * This writes the small copy beside each poster, once. Pages only ever serve
 * a copy that is already there, so nobody waits on a resize; it runs on every
 * deploy and does nothing for a design whose copy exists.
 */
class MakeCardPictures extends Command
{
    protected $signature = 'products:card-pictures {--side=400 : the longest side of the copy}';

    protected $description = 'Write the small catalogue copies of the design posters';

    public function handle(): int
    {
        $side = max(120, (int) $this->option('side'));
        $made = 0;

        foreach (Product::where('is_active', true)->get() as $product) {
            try {
                $before = $product->catalogImageSmall($side);
                $small = $product->catalogImageSmall($side, true);
            } catch (\Throwable $e) {
                $this->warn("Card picture: {$product->name} — {$e->getMessage()}");

                continue;
            }

            if ($small && ! $before) {
                $made++;
                $this->info("Card picture: {$product->name} — {$small}");
            }
        }

        $this->info("Card pictures: {$made} new.");

        return self::SUCCESS;
    }
}
