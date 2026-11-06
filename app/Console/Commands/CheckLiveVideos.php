<?php

namespace App\Console\Commands;

use App\Models\LivePhoto;
use App\Support\Telegram;
use Illuminate\Console\Command;

/**
 * Asks whether every printed box still has a video behind its code.
 *
 * The videos live on the owner's own Yandex Disk, which he tidies, moves
 * and renames like any other folder of his — and a public link does not
 * survive that. All six of the shop's live photos stopped working at once
 * and nothing said so: the boxes were already in people's hands, and the
 * first anybody knew was a customer pointing a telephone at one.
 *
 * So the links are asked once a day, the way a customer's telephone asks.
 * The owner is told the moment one stops answering, and told again when it
 * comes back — once each way, not every morning, because a message that
 * arrives daily stops being read by the second week.
 */
class CheckLiveVideos extends Command
{
    protected $signature = 'live:check {--quiet-telegram : Check and write down, but send nothing}';

    protected $description = 'Check that every live photo still has a video behind its link';

    public function handle(): int
    {
        $all = LivePhoto::where('is_active', true)
            ->where(fn ($q) => $q->whereNotNull('video_url')->orWhereNotNull('video_path'))
            ->get();

        if ($all->isEmpty()) {
            $this->line('No live photos to check.');

            return self::SUCCESS;
        }

        $broke = [];        // answering yesterday, silent today
        $mended = [];       // the other way round
        $bad = 0;

        foreach ($all as $live) {
            $was = $live->video_broken_at !== null;
            $ok = $live->checkVideo();

            if (! $ok) {
                $bad++;
            }
            if (! $ok && ! $was) {
                $broke[] = $live;
            }
            if ($ok && $was) {
                $mended[] = $live;
            }
        }

        $this->info("Live photos checked: {$all->count()}, not playable: {$bad}.");

        foreach ($broke as $live) {
            $this->warn("Live photo #{$live->id} ({$live->code}) stopped playing.");
        }

        if (! $this->option('quiet-telegram')) {
            $this->tell($broke, $mended);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, LivePhoto>  $broke
     * @param  array<int, LivePhoto>  $mended
     */
    private function tell(array $broke, array $mended): void
    {
        if (! Telegram::on() || ($broke === [] && $mended === [])) {
            return;
        }

        $lines = [];

        if ($broke !== []) {
            $lines[] = '⚠️ Canlı şəkil: ' . count($broke) . ' videonun linki cavab vermir.';
            $lines[] = '';
            foreach ($broke as $live) {
                $name = $live->title ?: 'Canlı şəkil #' . $live->id;
                $order = $live->orderItem?->order_id;
                /* The order number only where the name does not already
                   carry it: a live photo made with an order is titled
                   «Sifariş #17, …», and saying it twice reads as a fault. */
                $also = $order && ! str_contains($name, '#' . $order) ? ' (sifariş #' . $order . ')' : '';
                $lines[] = '• ' . $name . $also . ' — ' . $live->url();
            }
            $lines[] = '';
            /* Said plainly, because the natural guess is the token - and the
               token has nothing to do with playing a video. */
            $lines[] = 'Yandex Diskdə fayl silinib, köçürülüb, adı dəyişib və ya "Paylaş" sönüb.';
            $lines[] = 'Diskdə tapın, yenidən paylaşın və yeni linki admin paneldə yapışdırın:';
            $lines[] = route('filament.admin.resources.live-photos.index');
        }

        if ($mended !== []) {
            if ($lines !== []) {
                $lines[] = '';
            }
            $lines[] = '✅ Canlı şəkil: ' . count($mended) . ' video yenidən işləyir.';
        }

        Telegram::send(implode("\n", $lines));
    }
}
