<?php

namespace App\Http\Controllers;

use App\Models\DeliveryMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Address lookups for the checkout map when it runs on OpenStreetMap (no
 * Google key set). Asked through this site rather than from the browser, so
 * OpenStreetMap's Nominatim sees who is asking, as its usage policy
 * requires, and repeated lookups come from the cache instead.
 */
class MapController extends Controller
{
    private const NOMINATIM = 'https://nominatim.openstreetmap.org';

    /** The street address at a point in Baku. */
    public function reverse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $lat = round((float) $data['lat'], 5);
        $lng = round((float) $data['lng'], 5);
        if (! DeliveryMethod::inBaku($lat, $lng)) {
            return response()->json(['address' => null, 'outside' => true]);
        }

        $address = Cache::remember("map:rev:{$lat},{$lng}", now()->addDays(7), function () use ($lat, $lng) {
            $r = $this->nominatim('/reverse', ['lat' => $lat, 'lon' => $lng, 'zoom' => 18, 'addressdetails' => 1]);

            return $r ? self::format($r) : null;
        });

        return response()->json(['address' => $address, 'outside' => false]);
    }

    /** Places in Baku matching what the customer typed. */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->validate(['q' => ['required', 'string', 'min:3', 'max:120']])['q']);
        $b = DeliveryMethod::BAKU_BOUNDS;

        $results = Cache::remember('map:search:v2:' . md5(mb_strtolower($q)), now()->addDays(7), function () use ($q, $b) {
            $list = $this->nominatim('/search', [
                'q' => $q, 'countrycodes' => 'az', 'limit' => 6, 'addressdetails' => 1,
                'viewbox' => "{$b['west']},{$b['north']},{$b['east']},{$b['south']}", 'bounded' => 1,
            ]);

            return collect($list ?: [])->map(function ($r) {
                // A shop or square leads with its own name: "28 Mall — Azadlıq prospekti 15a, …".
                $label = self::format($r) ?? $r['display_name'];
                $name = trim((string) ($r['name'] ?? ''));
                if ($name !== '' && ! str_contains($label, $name)) {
                    $label = $name . ' — ' . $label;
                }

                return ['lat' => round((float) $r['lat'], 7), 'lng' => round((float) $r['lon'], 7), 'label' => $label];
            })->values()->all();
        });

        return response()->json(['results' => $results]);
    }

    private function nominatim(string $path, array $query): ?array
    {
        try {
            return Http::timeout(8)
                ->withHeaders(['User-Agent' => 'NefisShokoladEvi/1.0 (+https://nefis.az)', 'Accept-Language' => 'az,en'])
                ->get(self::NOMINATIM . $path, $query + ['format' => 'jsonv2'])
                ->throw()
                ->json();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * "Street 12, District, Bakı" from Nominatim's address parts. Door
     * delivery is Baku only, so the city is always Baku — some of OSM's Baku
     * points name a district as their city ("Səbail rayonu"), which would
     * otherwise read as a second district.
     */
    public static function format(array $r): ?string
    {
        $a = $r['address'] ?? [];
        $street = trim(implode(' ', array_filter([$a['road'] ?? $a['pedestrian'] ?? $a['street'] ?? $a['residential'] ?? null, $a['house_number'] ?? null])));
        $cityIsDistrict = isset($a['city']) && str_contains(mb_strtolower($a['city']), 'rayon');
        $area = $a['city_district'] ?? $a['suburb'] ?? ($cityIsDistrict ? $a['city'] : null) ?? $a['neighbourhood'] ?? $a['quarter'] ?? null;
        $town = $a['town'] ?? $a['village'] ?? null;   // Absheron settlements
        $parts = array_values(array_unique(array_filter([$street ?: ($r['name'] ?? null), $town ?: $area, 'Bakı'])));

        return count($parts) > 1 ? implode(', ', $parts) : ($r['display_name'] ?? null);
    }
}
