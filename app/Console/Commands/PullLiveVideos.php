<?php

namespace App\Console\Commands;

use App\Models\LivePhoto;
use App\Support\YandexDisk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Brings customers' live-photo videos back from Yandex Disk onto this site.
 *
 * The videos were kept only as public Yandex links, and a public link is
 * rationed: every telephone pointed at a printed box is one more download
 * off that ration. The shop's six boxes spent it, every link began answering
 * «Превышен лимит скачивания», and all six went dark at once — with the
 * files sitting safely on the Disk the whole time, and nothing the owner
 * could do about it from his side.
 *
 * A box printed last spring has to work next spring. So the video lives
 * here, where nothing counts the scans, and the Disk keeps the archive.
 *
 * The files come down with the owner's own token, which is the whole trick:
 * the ration is on the public side. Yandex says as much itself when a link
 * runs out — «save the file to your Disk and download it from there».
 */
class PullLiveVideos extends Command
{
    protected $signature = 'live:pull {--force : Fetch again even where this site already has a copy}';

    protected $description = "Copy customers' live-photo videos from Yandex Disk onto the hosting";

    public function handle(): int
    {
        $wanted = LivePhoto::whereNotNull('video_url')
            ->when(! $this->option('force'), fn ($q) => $q->whereNull('video_path'))
            ->get();

        if ($wanted->isEmpty()) {
            $this->line('No live-photo videos to bring back.');

            return self::SUCCESS;
        }
        if (! YandexDisk::hasToken()) {
            $this->warn($wanted->count() . ' video(s) are only on Yandex Disk, and it is not connected.');

            return self::SUCCESS;
        }

        try {
            // One listing for the lot, rather than a lookup per video.
            $published = YandexDisk::published();
        } catch (\RuntimeException $e) {
            /* At error level so it reaches «Xəta jurnalı»: this runs in the
               deploy, whose own log nobody here can read. */
            Log::error('live:pull could not list the published files', ['error' => $e->getMessage()]);
            $this->error('Yandex Disk did not list the published files: ' . $e->getMessage());

            return self::FAILURE;
        }

        Log::error('live:pull listing', [
            'published' => count($published),
            'wanted' => $wanted->count(),
            'keys' => array_slice(array_keys($published), 0, 10),
            'looking_for' => $wanted->map(fn ($l) => YandexDisk::linkKey((string) $l->video_url))->all(),
        ]);

        @set_time_limit(0);
        $disk = Storage::disk('public');
        $got = 0;

        foreach ($wanted as $live) {
            $path = $published[YandexDisk::linkKey((string) $live->video_url)] ?? null;

            if ($path === null) {
                Log::error('live:pull: link not among the published files', [
                    'live_photo' => $live->id, 'link' => $live->video_url,
                ]);
                $this->warn("Live photo #{$live->id} ({$live->code}): its link is not among the published files.");

                continue;
            }

            $name = 'live/' . $live->code . '.' . (pathinfo($path, PATHINFO_EXTENSION) ?: 'mp4');
            $disk->makeDirectory('live');

            try {
                YandexDisk::fetch($path, $disk->path($name));
            } catch (\RuntimeException $e) {
                Log::error('live:pull could not fetch a video', [
                    'live_photo' => $live->id, 'path' => $path, 'error' => $e->getMessage(),
                ]);
                $this->warn("Live photo #{$live->id} ({$live->code}): " . $e->getMessage());

                continue;
            }

            /* The link stays on the record: it is the archive now, and the
               page reads the hosting's copy first. */
            $live->forceFill([
                'video_path' => $name,
                'video_broken_at' => null,
                'video_checked_at' => now(),
            ])->saveQuietly();

            $this->info("Live photo #{$live->id} ({$live->code}): "
                . round($disk->size($name) / 1048576, 1) . ' MB on the hosting.');
            $got++;
        }

        $this->info("Live-photo videos brought back: {$got} of {$wanted->count()}.");

        return self::SUCCESS;
    }
}
