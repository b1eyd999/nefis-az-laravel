<?php

namespace App\Support;

use App\Models\Setting;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
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
            throw new RuntimeException('Linkdəki fayl şəkil deyil, PNG, JPG və ya WEBP lazımdır.');
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

    /* ---------- the owner's own disk: where customers' videos are put ---------- */

    private const DISK = 'https://cloud-api.yandex.net/v1/disk';

    public static function hasToken(): bool
    {
        return self::token() !== null;
    }

    /** The owner's OAuth token, kept encrypted in the settings. */
    public static function token(): ?string
    {
        $stored = Setting::get(Setting::YANDEX_TOKEN);
        if (blank($stored)) {
            return null;
        }
        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function saveToken(?string $token): void
    {
        $token = trim((string) $token);
        Setting::put(Setting::YANDEX_TOKEN, $token === '' ? '' : Crypt::encryptString($token));
    }

    /**
     * Whose disk the token opens, and how much room is left — to show the owner it works.
     *
     * @return array{name: string, free_gb: float}
     *
     * @throws RuntimeException with a message for the owner
     */
    public static function account(?string $token = null): array
    {
        $info = self::disk('get', '/', [], $token);

        return [
            'name' => (string) ($info['user']['display_name'] ?? $info['user']['login'] ?? '—'),
            'free_gb' => round(max(0, ($info['total_space'] ?? 0) - ($info['used_space'] ?? 0)) / 1073741824, 1),
        ];
    }

    /**
     * Puts a file on the owner's disk, in the videos' folder, shares it and
     * returns its public link ("https://disk.yandex.ru/i/…").
     *
     * @throws RuntimeException with a message for the owner
     */
    public static function upload(string $localPath, string $name): string
    {
        if (! is_file($localPath)) {
            throw new RuntimeException('Fayl hostinqdə tapılmadı.');
        }
        $folder = trim(str_replace(['\\', '/'], ' ', (string) Setting::get(Setting::YANDEX_FOLDER)));
        $folder = 'disk:/' . ($folder !== '' ? $folder : 'Nefis');
        $path = $folder . '/' . $name;

        $size = filesize($localPath);
        self::disk('put', '/resources', ['path' => $folder], null, [409]);   // 409: the folder is already there

        // Already there from an earlier try (the upload went through, the sharing did not): only share it.
        $there = self::disk('get', '/resources', ['path' => $path, 'fields' => 'size,public_url'], null, [404]);
        if ((int) ($there['size'] ?? -1) !== $size) {
            self::put($path, $localPath);
            // A big file is still being taken in when the upload answers (202): wait until it is on the disk.
            for ($try = 0; $try < 45; $try++) {
                $there = self::disk('get', '/resources', ['path' => $path, 'fields' => 'size,public_url'], null, [404]);
                if ((int) ($there['size'] ?? -1) === $size) {
                    break;
                }
                Sleep::for(2)->seconds();
            }
            if ((int) ($there['size'] ?? -1) !== $size) {
                throw new RuntimeException('Yandex Disk videonu hələ emal edir, bir az sonra yenidən köçürün.');
            }
        }

        if (filled($there['public_url'] ?? null)) {
            return $there['public_url'];
        }
        self::disk('put', '/resources/publish', ['path' => $path]);
        $link = self::disk('get', '/resources', ['path' => $path, 'fields' => 'public_url'])['public_url'] ?? null;
        if (! $link) {
            throw new RuntimeException('Yandex Disk video üçün link vermədi.');
        }

        return $link;
    }

    /** Sends the file itself to the address Yandex Disk hands out for it. */
    private static function put(string $path, string $localPath): void
    {
        $href = self::disk('get', '/resources/upload', ['path' => $path, 'overwrite' => 'true'])['href'] ?? null;
        if (! $href) {
            throw new RuntimeException('Yandex Disk yükləmə ünvanı vermədi.');
        }

        $stream = fopen($localPath, 'rb');
        try {
            $sent = Http::timeout(600)->withBody(Utils::streamFor($stream), 'application/octet-stream')->put($href);
        } catch (\Throwable $e) {
            throw new RuntimeException('Videonu Yandex Diskə yükləmək alınmadı: ' . $e->getMessage());
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        if (! in_array($sent->status(), [201, 202], true)) {
            throw new RuntimeException('Yandex Disk videonu qəbul etmədi (' . $sent->status() . ').');
        }
    }

    private static function disk(string $method, string $path, array $query, ?string $token = null, array $fine = []): array
    {
        $token ??= self::token();
        if (! $token) {
            throw new RuntimeException('Yandex Disk qoşulmayıb: tokeni "Canlı şəkillər" bölməsində əlavə edin.');
        }
        try {
            $request = Http::timeout(20)->acceptJson()->withHeaders(['Authorization' => 'OAuth ' . $token]);
            $url = self::DISK . $path . ($query ? '?' . http_build_query($query) : '');
            // Yandex refuses a PUT that carries a body (Laravel's put() would send "[]").
            $response = $method === 'put' ? $request->send('PUT', $url) : $request->get($url);
        } catch (\Throwable $e) {
            throw new RuntimeException('Yandex Disk cavab vermədi. Bir az sonra yenidən yoxlayın.');
        }

        if (in_array($response->status(), $fine, true)) {
            return [];
        }
        if ($response->status() === 401) {
            throw new RuntimeException('Yandex Disk tokeni qəbul etmədi, yeni token alıb yenidən əlavə edin.');
        }
        if ($response->status() === 507) {
            throw new RuntimeException('Yandex Diskdə yer qalmayıb.');
        }
        if (! $response->successful()) {
            throw new RuntimeException('Yandex Disk cavab vermədi (' . $response->status() . ': ' . $path . ', '
                . Str::limit((string) ($response->json('message') ?? $response->json('error') ?? ''), 120) . ').');
        }

        return $response->json() ?? [];
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
