<?php

use App\Support\Media;

return [

    /*
    |--------------------------------------------------------------------------
    | Remote artwork
    |--------------------------------------------------------------------------
    |
    | The design artwork is far too heavy to keep in the repository, so it
    | lives in a public Yandex Disk folder and the site only hands out
    | redirects to it. Leave the key empty and everything falls back to
    | local storage, which is what a fresh checkout does.
    |
    */

    'yandex_public_key' => env('YANDEX_MEDIA_KEY') ?: Media::keyFile(),

    // Folder inside the shared Yandex Disk link that mirrors storage/app/public.
    'yandex_root' => env('YANDEX_MEDIA_ROOT', ''),

    // Only these folders are served remotely. Fonts stay local: a webfont is
    // loaded through CORS, and Yandex Disk sends no CORS headers.
    'remote_prefixes' => ['products/', 'designs/', 'backgrounds/'],

    // Yandex hands out download links that stay valid for days; re-resolving
    // a few times a day keeps the API traffic negligible.
    'cache_seconds' => (int) env('YANDEX_MEDIA_CACHE', 6 * 3600),

];
