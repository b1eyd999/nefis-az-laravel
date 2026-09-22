<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Brings in a picture the owner shares from Yandex Disk by its public link
 * ("https://disk.yandex.ru/i/…"). The failure messages are shown to him as
 * they are, so they are written in Azerbaijani.
 */
class YandexDisk
{
    private const API = 'https://cloud-api.yandex.net/v1/disk/public/resources';

    public const MAX_BYTES = 30 * 1024 * 1024;

    /** Bigger pictures would not fit in the hosting's memory while being shrunk. */
    public const MAX_SIDE = 6000;

    private const TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public static function isPublicLink(?string $link): bool
    {
        return (bool) preg_match('#^https?://(disk\.yandex\.[a-z.]+|yadi\.sk)/(i|d)/[\w-]+#i', trim((string) $link));
    }

    /**
     * Downloads the linked picture into a temporary file, which the caller
     * deletes once it has kept what it needs.
     *
     * @throws RuntimeException with a message for the owner
     */
    public static function downloadImage(string $link): UploadedFile
    {
        $link = trim($link);
        if (! self::isPublicLink($link)) {
            throw new RuntimeException('Bu Yandex Disk linki deyil. Link belə görünməlidir: https://disk.yandex.ru/i/…');
        }

        $meta = self::api('', ['public_key' => $link, 'fields' => 'type,name,size,mime_type']);
        if (($meta['type'] ?? null) === 'dir') {
            throw new RuntimeException('Bu qovluğun linkidir. Qovluğu açın və şəklin öz linkini göndərin.');
        }
        if (! isset(self::TYPES[$meta['mime_type'] ?? ''])) {
            throw new RuntimeException('Linkdəki fayl şəkil deyil — PNG, JPG və ya WEBP lazımdır.');
        }
        if (($meta['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException('Şəkil çox böyükdür (' . round($meta['size'] / 1048576) . ' MB). 30 MB-dan kiçik olmalıdır.');
        }

        $href = self::api('/download', ['public_key' => $link])['href'] ?? null;
        if (! $href) {
            throw new RuntimeException('Yandex Disk bu faylı vermədi. Bir az sonra yenidən yoxlayın.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ydx');
        try {
            $response = Http::timeout(60)->get($href);
            $ok = $response->successful() && file_put_contents($tmp, $response->body()) > 0;
        } catch (\Throwable $e) {
            $ok = false;
        }
        $info = $ok ? @getimagesize($tmp) : false;
        $ext = $info ? (self::TYPES[$info['mime']] ?? null) : null;
        if (! $ext || max($info[0], $info[1]) > self::MAX_SIDE) {
            @unlink($tmp);
            throw new RuntimeException(match (true) {
                ! $ok => 'Şəkli Yandex Diskdən yükləmək alınmadı. Bir az sonra yenidən yoxlayın.',
                ! $ext => 'Yüklənən fayl şəkil kimi açılmadı.',
                default => "Şəkil çox böyükdür ({$info[0]}×{$info[1]}). Tərəfləri " . self::MAX_SIDE . ' pikseldən kiçik olmalıdır.',
            });
        }

        return new UploadedFile($tmp, 'poster.' . $ext, $info['mime'], null, true);
    }

    /**
     * What a public link points at: its type, name, size and MIME type.
     *
     * @throws RuntimeException with a message for the owner
     */
    public static function meta(string $link): array
    {
        if (! self::isPublicLink($link)) {
            throw new RuntimeException('Bu Yandex Disk linki deyil. Link belə görünməlidir: https://disk.yandex.ru/i/…');
        }

        return self::api('', ['public_key' => trim($link), 'fields' => 'type,name,size,mime_type,media_type']);
    }

    /**
     * A download address for the file behind a public link, good for a while,
     * so a browser can stream it from Yandex without this site carrying it.
     */
    public static function href(string $link): ?string
    {
        return \Illuminate\Support\Facades\Cache::remember('ydx:href:' . sha1($link), now()->addMinutes(30), function () use ($link) {
            try {
                return self::api('/download', ['public_key' => trim($link)])['href'] ?? null;
            } catch (RuntimeException $e) {
                return null;
            }
        });
    }

    private static function api(string $path, array $query): array
    {
        try {
            $response = Http::timeout(15)->acceptJson()->get(self::API . $path, $query);
        } catch (\Throwable $e) {
            throw new RuntimeException('Yandex Disk cavab vermədi. Bir az sonra yenidən yoxlayın.');
        }

        if ($response->status() === 404) {
            throw new RuntimeException('Bu linkdə fayl tapılmadı. Faylın paylaşıldığını (link açıqdır) yoxlayın.');
        }
        if (! $response->successful()) {
            throw new RuntimeException('Yandex Disk cavab vermədi (' . $response->status() . '). Bir az sonra yenidən yoxlayın.');
        }

        return $response->json() ?? [];
    }
}
