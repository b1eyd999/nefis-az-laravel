<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * A stamp on the site's own files, so browsers keep them until they change.
 */
class Assets
{
    public static function version(string $path): string
    {
        return Cache::remember('asset:' . $path, now()->addHour(), function () use ($path) {
            $file = public_path($path);

            return is_file($file) ? (string) filemtime($file) : '1';
        });
    }
}
