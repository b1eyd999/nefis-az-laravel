<?php

namespace App\Console\Commands;

use App\Models\LivePhoto;
use App\Support\R2;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Puts the live-photo videos this site holds into Cloudflare R2.
 *
 * The hosting keeps them and works perfectly well; R2 is for not having to
 * think about the hosting's disk and traffic as the shop grows, and for
 * being read from a place that charges nothing for being read. The copy
 * here stays: three places is not extravagance when the last outage took
 * every printed box down at once.
 *
 * A video only on Yandex has to come here first — `live:pull` does that,
 * and it cannot while the owner's Disk is over its quota.
 */
class MoveLiveVideosToR2 extends Command
{
    protected $signature = 'live:r2 {--force : Upload again even where the bucket already has it}';

    protected $description = 'Copy live-photo videos from the hosting into Cloudflare R2';

    public function handle(): int
    {
        if (! R2::configured()) {
            $this->line('Cloudflare R2 is not connected.');

            return self::SUCCESS;
        }

        $waiting = LivePhoto::whereNotNull('video_path')
            ->when(! $this->option('force'), fn ($q) => $q->whereNull('video_r2'))
            ->get();

        if ($waiting->isEmpty()) {
            $this->line('No live-photo videos waiting for R2.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $disk = Storage::disk('public');
        $moved = 0;

        foreach ($waiting as $live) {
            if (! $disk->exists($live->video_path)) {
                $this->warn("Live photo #{$live->id}: {$live->video_path} is not on the hosting.");

                continue;
            }

            $key = 'live/' . $live->code . '.' . (pathinfo($live->video_path, PATHINFO_EXTENSION) ?: 'mp4');

            try {
                R2::put($disk->path($live->video_path), $key, 'video/mp4');
            } catch (\RuntimeException $e) {
                Log::error('live:r2 could not upload a video', [
                    'live_photo' => $live->id, 'key' => $key, 'error' => $e->getMessage(),
                ]);
                $this->warn("Live photo #{$live->id}: " . $e->getMessage());

                continue;
            }

            $live->forceFill([
                'video_r2' => $key,
                'video_broken_at' => null,
                'video_checked_at' => now(),
            ])->saveQuietly();

            $this->info("Live photo #{$live->id} ({$live->code}) → {$key}");
            $moved++;
        }

        $this->info("Live-photo videos in R2: {$moved} of {$waiting->count()}.");

        return self::SUCCESS;
    }
}
