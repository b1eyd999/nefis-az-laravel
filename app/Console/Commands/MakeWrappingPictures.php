<?php

namespace App\Console\Commands;

use App\Models\Wrapping;
use Illuminate\Console\Command;

/**
 * The wrapping papers are uploaded as print files — the largest of them is
 * seven megabytes — and they were served whole: once into a CSS background on
 * a square the size of a thumbnail, and once more to tile over the box in the
 * preview. Ten papers came to twenty-nine megabytes, which on a phone is the
 * slowest moment in the whole order, and it happens right before paying.
 *
 * This writes the two smaller copies beside each master, once. Pages only ever
 * serve a copy that is already there, so no visitor waits on a resize; it runs
 * on every deploy and does nothing for a paper whose copies exist.
 */
class MakeWrappingPictures extends Command
{
    protected $signature = 'wrappings:pictures';

    protected $description = 'Write the small copies of the wrapping papers';

    public function handle(): int
    {
        $made = 0;
        $saved = 0;

        foreach (Wrapping::whereNotNull('pattern')->get() as $wrap) {
            foreach ([Wrapping::RENDER_SIDE, Wrapping::SWATCH_SIDE] as $side) {
                try {
                    $before = $wrap->patternSmall($side);
                    $small = $wrap->patternSmall($side, true);
                } catch (\Throwable $e) {
                    $this->warn("Wrapping picture: {$wrap->name} @{$side} — {$e->getMessage()}");

                    continue;
                }

                if ($small && ! $before) {
                    $made++;
                    $saved += $this->sizeDrop($wrap->pattern, $small);
                    $this->info("Wrapping picture: {$wrap->name} @{$side} — {$small}");
                }
            }
        }

        $this->info($saved > 0
            ? sprintf('Wrapping pictures: %d new, %s less to download.', $made, $this->human($saved))
            : sprintf('Wrapping pictures: %d new.', $made));

        return self::SUCCESS;
    }

    /** How much lighter the copy is than the master it replaces on a page. */
    private function sizeDrop(string $master, string $small): int
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        try {
            return max(0, $disk->size($master) - $disk->size($small));
        } catch (\Throwable) {
            return 0;
        }
    }

    private function human(int $bytes): string
    {
        return $bytes >= 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : round($bytes / 1024) . ' KB';
    }
}
