<?php

namespace App\Console\Commands;

use App\Models\LivePhoto;
use App\Support\YandexDisk;
use Illuminate\Console\Command;

/**
 * Customers' live-photo videos still on the hosting (Yandex Disk was not
 * connected, or did not answer when the order came in), moved to Yandex Disk.
 */
class PushLiveVideos extends Command
{
    protected $signature = 'live:push';

    protected $description = "Move customers' live-photo videos from the hosting to Yandex Disk";

    public function handle(): int
    {
        $waiting = LivePhoto::whereNotNull('video_path')->whereNull('video_url')->get();
        if ($waiting->isEmpty()) {
            $this->line('No live-photo videos waiting for Yandex Disk.');

            return self::SUCCESS;
        }
        if (! YandexDisk::hasToken()) {
            $this->warn($waiting->count() . ' live-photo video(s) wait on the hosting: Yandex Disk is not connected.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $moved = $waiting->filter(fn (LivePhoto $live) => $live->pushVideo())->count();
        $this->info("Live-photo videos moved to Yandex Disk: {$moved} of {$waiting->count()}.");

        return self::SUCCESS;
    }
}
