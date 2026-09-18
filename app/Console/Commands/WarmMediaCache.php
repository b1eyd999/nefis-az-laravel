<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductAngle;
use App\Support\Media;
use Illuminate\Console\Command;

/**
 * Resolves every remote image once, right after a deploy.
 *
 * Without this the first visitor pays for one Yandex lookup per image on the
 * page, and on shared hosting that many simultaneous lookups tie up the PHP
 * workers. Warming them costs nothing because nobody is waiting.
 *
 * Links are looked up afresh rather than kept: a deploy is usually the
 * moment artwork was replaced on Yandex, and a cached link can go on serving
 * the old file for hours.
 */
class WarmMediaCache extends Command
{
    protected $signature = 'media:warm';

    protected $description = 'Resolve the Yandex Disk links for every design image';

    public function handle(): int
    {
        $paths = collect()
            ->concat(Product::query()->pluck('preview_image'))
            ->concat(Product::query()->pluck('template_image'))
            ->concat(Product::query()->pluck('overlay_image'))
            ->concat(Product::query()->pluck('background_image'))
            ->concat(ProductAngle::query()->pluck('template_image'))
            ->concat(ProductAngle::query()->pluck('overlay_image'))
            ->concat(ProductAngle::query()->pluck('background_image'))
            ->filter()
            ->unique()
            ->filter(fn (string $path) => Media::isRemote($path))
            ->values();

        if ($paths->isEmpty()) {
            $this->info('No remote images to warm.');

            return self::SUCCESS;
        }

        $failed = 0;
        $bar = $this->output->createProgressBar($paths->count());

        foreach ($paths as $path) {
            Media::forget($path);
            if (Media::resolve($path) === null) {
                $failed++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info(sprintf('Warmed %d image(s), %d failed.', $paths->count() - $failed, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
