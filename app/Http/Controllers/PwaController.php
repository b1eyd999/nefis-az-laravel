<?php

namespace App\Http\Controllers;

use App\Support\Assets;
use App\Support\Locale;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * The shop as an app on the phone.
 *
 * Not from the App Store and not a bookmark: a phone that is told a site is an
 * application installs it as one — its own icon, its own window with no browser
 * bar, its own splash, and the pages it has already seen readable with no
 * signal. On iPhone this is the only way in that does not go through Apple;
 * on Android the browser offers a real "Install" and keeps it among the
 * other apps.
 *
 * The manifest is written per language, because the name, the description and
 * the way in are all different in each.
 */
class PwaController extends Controller
{
    /** What the phone reads to decide the shop is an app. */
    public function manifest(string $lang): JsonResponse
    {
        abort_unless(in_array($lang, Locale::all(), true), 404);

        // The address says which language this manifest is for, and the words
        // inside it follow — the file is asked for outside the language groups.
        app()->setLocale($lang);

        $home = $lang === Locale::DEFAULT ? '/' : '/'.$lang.'/';

        return response()->json([
            'id' => 'nefis.az',
            'name' => __('Nefis — şəkilli şokolad qutuları'),
            'short_name' => 'Nefis',
            'description' => __('Öz şəklinizlə fərdi şokolad qutusu: seçin, şəklinizi yükləyin, qapınıza çatdırırıq.'),
            'lang' => $lang,
            'dir' => 'ltr',
            // Standalone is what makes it an app and not a tab: no address
            // bar, its own place among the phone's other applications.
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation' => 'portrait',
            'start_url' => $home,
            'scope' => '/',
            'background_color' => '#FBF4EA',
            'theme_color' => '#FBF4EA',
            'categories' => ['shopping', 'food', 'lifestyle'],
            'icons' => [
                ['src' => Assets::url('images/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => Assets::url('images/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
                /* The same mark again with room around it: Android cuts an
                   icon into whatever shape the phone's theme uses, and an
                   edge-to-edge logo comes out with its corners shaved. */
                ['src' => Assets::url('images/icon-maskable-512.png'), 'sizes' => '512x512',
                    'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            // The long press on the icon: where people actually go.
            'shortcuts' => [
                [
                    'name' => __('Dizaynlar'),
                    'url' => $this->url($lang, 'designs.index'),
                    'icons' => [['src' => Assets::url('images/icon-192.png'), 'sizes' => '192x192']],
                ],
                [
                    'name' => __('Səbət'),
                    'url' => $this->url($lang, 'cart.index'),
                    'icons' => [['src' => Assets::url('images/icon-192.png'), 'sizes' => '192x192']],
                ],
                [
                    'name' => __('Sifarişlərim'),
                    'url' => $this->url($lang, 'orders.index'),
                    'icons' => [['src' => Assets::url('images/icon-192.png'), 'sizes' => '192x192']],
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * The page the app shows when the phone has no signal and the page asked
     * for was never opened before. Cached by the worker at install time, so it
     * is there precisely when nothing else is.
     */
    public function offline(): View
    {
        return view('offline');
    }

    /** A route of the shop in one particular language. */
    private function url(string $lang, string $name): string
    {
        return route($lang === Locale::DEFAULT ? $name : $lang.'.'.$name);
    }
}
