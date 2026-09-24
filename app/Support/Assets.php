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
        $file = public_path($path);

        // On the hosting there is nothing to look at, and the answer would
        // only go stale in the cache; a single stat is cheaper than that.
        if (! is_file($file)) {
            return (string) self::deployedAt();
        }

        return Cache::remember('asset:' . $path, now()->addHour(), fn () => (string) filemtime($file));
    }

    /** The file's address with its stamp on the end. */
    public static function url(string $path): string
    {
        return asset($path) . '?v=' . self::version($path);
    }

    private static function deployedAt(): int
    {
        // artisan travels with every release; its date is the release's date.
        return @filemtime(base_path('artisan')) ?: 1;
    }
}
