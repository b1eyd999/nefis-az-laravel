<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Resolves the paths stored on products and orders to a URL.
 *
 * The box artwork runs to hundreds of megabytes, which the shared hosting
 * should not have to carry, so it is published from a Yandex Disk share
 * instead. Pages still link to a route on this site; that route redirects to
 * Yandex, so the bytes never pass through the server.
 */
class Media
{
    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return self::isRemote($path)
            ? route('media', ['path' => $path])
            : asset('storage/' . $path);
    }

    public static function isRemote(string $path): bool
    {
        if (blank(config('media.yandex_public_key')) || ! self::isSafe($path)) {
            return false;
        }

        // A copy on disk always wins: that is how development works, and how
        // anything uploaded through the admin keeps working.
        if (Storage::disk('public')->exists($path)) {
            return false;
        }

        foreach (config('media.remote_prefixes') as $prefix) {
            if (Str::startsWith($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The download link for a remote path, or null when Yandex cannot serve it.
     */
    public static function resolve(string $path): ?string
    {
        return Cache::remember(
            'media:' . sha1($path),
            config('media.cache_seconds'),
            function () use ($path) {
                $response = Http::timeout(8)->retry(2, 200)->get(
                    'https://cloud-api.yandex.net/v1/disk/public/resources/download',
                    [
                        'public_key' => config('media.yandex_public_key'),
                        'path' => rtrim(config('media.yandex_root'), '/') . '/' . $path,
                    ]
                );

                if (! $response->successful()) {
                    Log::warning('Yandex media lookup failed', [
                        'path' => $path,
                        'status' => $response->status(),
                    ]);

                    return null;
                }

                return $response->json('href');
            }
        );
    }

    /**
     * Guards the route against traversal and against being used as an open
     * redirect for arbitrary files in the share.
     */
    public static function isSafe(string $path): bool
    {
        return (bool) preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#', $path)
            && ! Str::contains($path, '..');
    }
}
