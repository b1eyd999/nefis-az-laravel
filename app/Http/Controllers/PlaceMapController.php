<?php

namespace App\Http\Controllers;

use App\Support\MapImage;
use App\Support\StreetMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The streets behind a map box, and the search that finds a place.
 *
 * Both go through the site rather than straight from the browser, so the key
 * stays on the server and the same corner is fetched once and then read off
 * the disk.
 */
class PlaceMapController extends Controller
{
    /** The picture of one place, drawn in one of the shop's four looks. */
    public function image(Request $request): Response
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-85,85'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
            'zoom' => ['nullable', 'integer', 'between:' . StreetMap::ZOOM_MIN . ',' . StreetMap::ZOOM_MAX],
            'style' => ['nullable', 'string', 'in:' . implode(',', array_keys(MapImage::STYLES))],
            'w' => ['nullable', 'integer', 'between:80,' . MapImage::MAX_SIDE],
            'h' => ['nullable', 'integer', 'between:80,' . MapImage::MAX_SIDE],
            'scale' => ['nullable', 'integer', 'between:1,2'],
        ]);

        $path = MapImage::fetch(
            (float) $data['lat'],
            (float) $data['lon'],
            (int) ($data['zoom'] ?? 15),
            (string) ($data['style'] ?? 'ink'),
            (int) ($data['w'] ?? 600),
            (int) ($data['h'] ?? 600),
            (int) ($data['scale'] ?? 1),
        );

        if ($path === null) {
            // No key yet, or the service would not answer. The window draws
            // its own placeholder rather than showing a broken picture.
            return response('', 404);
        }

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /** Places anywhere in the world matching what the customer typed. */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:120']])['q']);
        $language = \App\Support\Locale::current();

        $found = Cache::remember(
            'place:search:' . $language . ':' . md5(mb_strtolower($q)),
            now()->addDays(7),
            fn () => MapImage::search($q, $language),
        );

        return response()->json(['results' => $found]);
    }
}
