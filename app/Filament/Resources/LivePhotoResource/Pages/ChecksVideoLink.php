<?php

namespace App\Filament\Resources\LivePhotoResource\Pages;

use App\Support\YandexDisk;
use Filament\Notifications\Notification;

/** Makes sure the Yandex Disk link is a video before the live photo is saved. */
trait ChecksVideoLink
{
    protected function checkVideoLink(): void
    {
        $link = trim((string) ($this->data['video_url'] ?? ''));
        try {
            $meta = YandexDisk::meta($link);
            $problem = match (true) {
                ($meta['type'] ?? null) === 'dir' => 'Bu qovluğun linkidir. Qovluğu açın və videonun öz linkini göndərin.',
                ! str_starts_with((string) ($meta['mime_type'] ?? ''), 'video/') => 'Linkdəki fayl video deyil (MP4, MOV və ya WEBM lazımdır).',
                default => null,
            };
        } catch (\RuntimeException $e) {
            $problem = $e->getMessage();
        }

        if ($problem) {
            Notification::make()->danger()->title('Video linki yoxlanmadı')->body($problem)->persistent()->send();
            $this->halt();
        }
    }
}
