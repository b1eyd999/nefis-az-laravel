<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Brings in catalogue posters whose Yandex Disk link is set but whose photo
 * is not here yet — one the admin could not reach when it was saved, one a
 * migration filled in, or one whose file has since gone missing while the
 * database went on pointing at it. That last case is the one that shows: the
 * catalogue serves a broken picture to everyone whose browser has not cached
 * the old one, and nothing says so, because the column is filled in.
 * Runs on every deploy; a failure only reports.
 */
class FetchPosters extends Command
{
    protected $signature = 'products:fetch-posters';

    protected $description = 'Download the catalogue posters linked from Yandex Disk that are still missing';

    public function handle(): int
    {
        $disk = Storage::disk('public');

        $products = Product::whereNotNull('poster_url')->where('poster_url', '!=', '')->get()
            ->filter(fn (Product $p) => blank($p->poster_image) || ! $disk->exists($p->poster_image))
            ->values();

        $gone = 0;
        foreach ($products as $product) {
            $missing = filled($product->poster_image);
            try {
                $product->importPoster();
                $gone += $missing ? 1 : 0;
                $this->info(($missing ? 'Poster back: ' : 'Poster: ') . $product->name . ' — ' . $product->poster_image);
            } catch (\RuntimeException $e) {
                $this->warn("Poster: {$product->name} — {$e->getMessage()}");
            }
        }

        if ($products->isEmpty()) {
            $this->line('No posters to fetch.');
        } elseif ($gone > 0) {
            $this->line($gone . ' poster(s) had gone missing from the disk and were downloaded again.');
        }

        // A design whose poster is gone and whose link is gone with it cannot
        // be mended here — the owner has to paste the link again, so say which.
        $orphans = Product::where(fn ($q) => $q->whereNull('poster_url')->orWhere('poster_url', ''))
            ->whereNotNull('poster_image')->get()
            ->filter(fn (Product $p) => ! $disk->exists($p->poster_image));

        foreach ($orphans as $orphan) {
            $this->warn("Poster missing and no link: {$orphan->name} (#{$orphan->id}) — paste the Yandex link again.");
        }

        return self::SUCCESS;
    }
}
