<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The picture of the streets behind a map window.
 *
 * The streets come from OpenStreetMap, drawn for us by Geoapify. The key that
 * asks for them is the shop's own and never leaves the server: the browser
 * asks this site, this site asks Geoapify once, and the answer is kept on disk
 * so the same corner is never bought twice.
 */
class MapImage
{
    /** Where the fetched pictures are kept, under storage/app. */
    public const DIRECTORY = 'maps';

    /** The shop's four looks, and the Geoapify style each one is drawn in. */
    public const STYLES = [
        'ink' => 'dark-matter',
        'paper' => 'toner',
        'sea' => 'positron-blue',
        'colour' => 'osm-bright',
    ];

    /** The biggest picture Geoapify will draw in one go. */
    public const MAX_SIDE = 2000;

    /** The key the owner pasted into the admin, or null while there is none. */
    public static function key(): ?string
    {
        $stored = Setting::get(Setting::GEOAPIFY_KEY);
        if (blank($stored)) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null;
        }
    }

    public static function saveKey(?string $key): void
    {
        $key = self::clean($key);
        Setting::put(Setting::GEOAPIFY_KEY, $key === '' ? '' : Crypt::encryptString($key));
    }

    /**
     * The key out of whatever was pasted.
     *
     * Geoapify's own page offers the key inside a whole sample address, and
     * that address is what lands in the field — so the key is taken out of it
     * rather than the owner being told to do it by hand.
     */
    public static function clean(?string $value): string
    {
        $value = trim((string) $value, " \t\n\r\0\x0B\"'");

        if (preg_match('/apikey=([A-Za-z0-9_-]+)/i', $value, $found)) {
            return $found[1];
        }

        return $value;
    }

    public static function ready(): bool
    {
        return self::key() !== null;
    }

    /**
     * The picture of one place, as a file on disk. Returns the path under
     * storage/app, or null when it could not be fetched.
     */
    public static function fetch(float $lat, float $lon, int $zoom, string $style, int $width, int $height, int $scale = 1): ?string
    {
        $style = isset(self::STYLES[$style]) ? $style : 'ink';
        $width = max(80, min(self::MAX_SIDE, $width));
        $height = max(80, min(self::MAX_SIDE, $height));
        $scale = max(1, min(2, $scale));
        $zoom = StreetMap::zoom($zoom);
        $lat = round($lat, 5);
        $lon = round($lon, 5);

        $name = self::DIRECTORY . '/' . implode('-', [
            $style, $zoom, str_replace('.', '_', (string) $lat), str_replace('.', '_', (string) $lon),
            $width, $height, $scale,
        ]) . '.png';

        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        if ($disk->exists($name)) {
            return $name;
        }

        $key = self::key();
        if ($key === null) {
            return null;
        }

        try {
            $answer = Http::timeout(25)->get('https://maps.geoapify.com/v1/staticmap', [
                'style' => self::STYLES[$style],
                'width' => $width,
                'height' => $height,
                'center' => 'lonlat:' . $lon . ',' . $lat,
                'zoom' => $zoom,
                'scaleFactor' => $scale,
                'format' => 'png',
                'apiKey' => $key,
            ]);
        } catch (Throwable) {
            return null;
        }

        if (! $answer->successful() || ! str_starts_with((string) $answer->header('Content-Type'), 'image/')) {
            return null;
        }

        $disk->put($name, $answer->body());

        return $name;
    }

    /**
     * Ask the service one question and report exactly what came back.
     *
     * The shop itself swallows failures — a customer must not meet a stack
     * trace — so this is the one place that keeps the reason, for the owner's
     * own page.
     *
     * @return array{ok: bool, why: string, detail: string}
     */
    public static function probe(): array
    {
        $key = self::key();
        if ($key === null) {
            return ['ok' => false, 'why' => 'no-key', 'detail' => ''];
        }

        /* How the key is shaped, said without printing it: a pasted address or
           a half-copied key is the usual reason the service says no. */
        $shape = mb_strlen($key) . ' simvol, son 4: ' . mb_substr($key, -4);
        if (str_contains($key, '/') || str_contains($key, '?') || str_contains($key, '=')) {
            return ['ok' => false, 'why' => 'not-a-key', 'detail' => $shape];
        }

        try {
            $answer = Http::timeout(15)->get('https://api.geoapify.com/v1/geocode/autocomplete', [
                'text' => 'Bakı', 'limit' => 1, 'format' => 'json', 'apiKey' => $key,
            ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'why' => 'unreachable', 'detail' => mb_substr($e->getMessage(), 0, 160)];
        }

        if (! $answer->successful()) {
            return [
                'ok' => false,
                'why' => 'refused',
                'detail' => 'HTTP ' . $answer->status() . ' — ' . mb_substr(trim((string) $answer->body()), 0, 160),
            ];
        }

        $found = $answer->json('results.0.formatted');

        return $found
            ? ['ok' => true, 'why' => 'ok', 'detail' => (string) $found]
            : ['ok' => false, 'why' => 'empty', 'detail' => $shape . ' — ' . mb_substr((string) $answer->body(), 0, 160)];
    }

    /**
     * Places that match what the customer typed, anywhere in the world.
     *
     * @return array<int, array{name: string, lat: float, lon: float}>
     */
    public static function search(string $text, string $language = 'az'): array
    {
        $key = self::key();
        if ($key === null || mb_strlen($text) < 2) {
            return [];
        }

        try {
            $answer = Http::timeout(12)->get('https://api.geoapify.com/v1/geocode/autocomplete', [
                'text' => $text,
                'limit' => 8,
                'lang' => in_array($language, ['az', 'ru', 'en'], true) ? $language : 'az',
                'format' => 'json',
                'apiKey' => $key,
            ]);
        } catch (Throwable) {
            return [];
        }

        if (! $answer->successful()) {
            return [];
        }

        return collect($answer->json('results') ?? [])
            ->map(fn (array $hit) => [
                'name' => (string) ($hit['formatted'] ?? $hit['address_line1'] ?? ''),
                'lat' => (float) ($hit['lat'] ?? 0),
                'lon' => (float) ($hit['lon'] ?? 0),
            ])
            ->filter(fn (array $hit) => $hit['name'] !== '' && $hit['lat'] !== 0.0)
            ->values()->all();
    }
}
