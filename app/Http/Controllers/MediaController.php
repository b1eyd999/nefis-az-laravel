<?php

namespace App\Http\Controllers;

use App\Support\Media;
use Illuminate\Http\RedirectResponse;

/**
 * Hands the browser straight to Yandex Disk for the heavy artwork.
 *
 * Yandex refuses requests that carry a referer from another site, so every
 * page that shows these images declares `no-referrer`; see the layout head.
 */
class MediaController extends Controller
{
    public function show(string $path): RedirectResponse
    {
        abort_unless(Media::isRemote($path), 404);

        $href = Media::resolve($path);

        abort_if($href === null, 503, __('Şəkil hazırda əlçatan deyil.'));

        return redirect()->away($href, 302, [
            'Cache-Control' => 'public, max-age=600',
        ]);
    }
}
