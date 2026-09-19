<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Brings in catalogue posters whose Yandex Disk link is set but whose photo
 * is not here yet — one the admin could not reach when it was saved, or one
 * a migration filled in. Runs on every deploy; a failure only reports.
 */
class FetchPosters extends Command
{
    protected $signature = 'products:fetch-posters';

    protected $description = 'Download the catalogue posters linked from Yandex Disk that are still missing';

    public function handle(): int
    {
        $products = Product::whereNotNull('poster_url')->where('poster_url', '!=', '')->whereNull('poster_image')->get();

        foreach ($products as $product) {
            try {
                $product->importPoster();
                $this->info("Poster: {$product->name} — {$product->poster_image}");
            } catch (\RuntimeException $e) {
                $this->warn("Poster: {$product->name} — {$e->getMessage()}");
            }
        }

        if ($products->isEmpty()) {
            $this->line('No posters to fetch.');
        }

        return self::SUCCESS;
    }
}
