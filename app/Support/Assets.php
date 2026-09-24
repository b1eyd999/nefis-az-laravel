<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * A stamp on the site's own files, so a browser keeps them until they change
 * and picks the new one up the moment they do.
 *
 * On the hosting the app and its public files live apart: the deploy copies
 * public/ into the web root and drops it from the app directory, so the file
 * cannot be found from here. Everything else is copied at the same moment,
 * so the deploy's own time is the stamp.
 */
class Assets
{
    public static function version(string $path): string
    {
        return Cache::remember('asset:' . $path, now()->addHour(), function () use ($path) {
            $file = public_path($path);

            return (string) (is_file($file) ? filemtime($file) : self::deployedAt());
        });
    }

    private static function deployedAt(): int
    {
        // artisan travels with every release; its date is the release's date.
        return @filemtime(base_path('artisan')) ?: 1;
    }
}
