<?php

namespace App\Support;

use App\Models\PhotoSlot;
use App\Models\Product;

/**
 * The star map a customer orders: where, when, and in what colours.
 *
 * Nothing here draws anything — the drawing happens in the browser, in
 * public/js/star-map.js, from these same four numbers. What is kept is the
 * question, not the answer, so the picture can be made again at printing size
 * long after the order.
 */
class Sky
{
    /** Azerbaijan keeps one offset all year; a place elsewhere is read off its longitude. */
    public const OFFSET = 4;

    /**
     * The towns offered in the list, with the coordinates the sky is worked
     * out for. A place that is not here is typed in as coordinates.
     *
     * @return array<int, array{name: string, lat: float, lon: float}>
     */
    public static function places(): array
    {
        return [
            ['name' => 'Bakı', 'lat' => 40.3777, 'lon' => 49.8920],
            ['name' => 'Gəncə', 'lat' => 40.6828, 'lon' => 46.3606],
            ['name' => 'Sumqayıt', 'lat' => 40.5892, 'lon' => 49.6680],
            ['name' => 'Mingəçevir', 'lat' => 40.7700, 'lon' => 47.0489],
            ['name' => 'Lənkəran', 'lat' => 38.7529, 'lon' => 48.8508],
            ['name' => 'Şirvan', 'lat' => 39.9314, 'lon' => 48.9206],
            ['name' => 'Naxçıvan', 'lat' => 39.2089, 'lon' => 45.4122],
            ['name' => 'Şəki', 'lat' => 41.1919, 'lon' => 47.1706],
            ['name' => 'Yevlax', 'lat' => 40.6194, 'lon' => 47.1500],
            ['name' => 'Xaçmaz', 'lat' => 41.4581, 'lon' => 48.8022],
            ['name' => 'Quba', 'lat' => 41.3606, 'lon' => 48.5125],
            ['name' => 'Qəbələ', 'lat' => 40.9817, 'lon' => 47.8456],
            ['name' => 'Astara', 'lat' => 38.4553, 'lon' => 48.8750],
            ['name' => 'Şuşa', 'lat' => 39.7597, 'lon' => 46.7489],
            ['name' => 'Ağdam', 'lat' => 39.9930, 'lon' => 47.0294],
            ['name' => 'Zaqatala', 'lat' => 41.6314, 'lon' => 46.6444],
            ['name' => 'İsmayıllı', 'lat' => 40.7872, 'lon' => 48.1517],
            ['name' => 'Masallı', 'lat' => 39.0342, 'lon' => 48.6653],
            ['name' => 'Salyan', 'lat' => 39.5961, 'lon' => 48.9847],
            ['name' => 'Bərdə', 'lat' => 40.3744, 'lon' => 47.1264],
        ];
    }

    /** Does this design ask for a star map at all? */
    public static function wanted(Product $product): bool
    {
        return $product->photoSlots->contains(fn (PhotoSlot $slot) => $slot->isSky());
    }

    /** The windows that hold sky rather than a photograph, by their position in the form. */
    public static function slotIndexes(Product $product): array
    {
        return $product->photoSlots->values()
            ->filter(fn (PhotoSlot $slot) => $slot->isSky())
            ->keys()->all();
    }

    /**
     * What the shop keeps on the order line. The hour is the one on the clock
     * in that place, so the offset travels with it.
     *
     * @return array{date: string, time: string, lat: float, lon: float, tz: int, place: ?string}|null
     */
    public static function fromRequest(\Illuminate\Http\Request $request): ?array
    {
        if (! $request->filled('star_date')) {
            return null;
        }

        $lat = round((float) $request->input('star_lat'), 4);
        $lon = round((float) $request->input('star_lon'), 4);

        return [
            'date' => (string) $request->input('star_date'),
            'time' => (string) ($request->input('star_time') ?: '21:00'),
            'lat' => $lat,
            'lon' => $lon,
            // Inside the country the civil offset is +4; further away the
            // longitude is the honest guess, and an hour out turns the sky by
            // fifteen degrees — enough to matter, not enough to spoil a gift.
            'tz' => abs($lon - 49) < 8 && abs($lat - 40) < 4 ? self::OFFSET : (int) round($lon / 15),
            'place' => $request->filled('star_place') ? mb_substr(trim((string) $request->input('star_place')), 0, 60) : null,
        ];
    }

    /** Month names as they are printed on a box, in the shop's own language. */
    public const MONTHS = ['Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'İyun',
                           'İyul', 'Avqust', 'Sentyabr', 'Oktyabr', 'Noyabr', 'Dekabr'];

    /**
     * What an automatic caption says for this night. The date and the place
     * are the customer's own words and numbers, never retyped.
     *
     * @param  array{date: string, time: string, lat: float, lon: float, place: ?string}|null  $star
     */
    public static function caption(string $kind, ?array $star): string
    {
        if (! $star) {
            return '';
        }

        $date = \Illuminate\Support\Carbon::parse($star['date']);

        return match ($kind) {
            'coords' => self::coordinates((float) $star['lat'], (float) $star['lon']),
            'date' => $date->format('d.m.Y'),
            'date_long' => $date->day . ' ' . (self::MONTHS[$date->month - 1] ?? '') . ' ' . $date->year,
            'place' => (string) ($star['place'] ?? ''),
            default => '',
        };
    }

    /** The coordinates as they are printed under the sky: 38°47'33"N 48°28'47"E. */
    public static function coordinates(float $lat, float $lon): string
    {
        return self::degrees($lat, 'N', 'S') . ' ' . self::degrees($lon, 'E', 'W');
    }

    private static function degrees(float $value, string $positive, string $negative): string
    {
        $v = abs($value);
        $d = (int) floor($v);
        $m = (int) floor(($v - $d) * 60);
        $s = (int) round(((($v - $d) * 60) - $m) * 60);
        if ($s === 60) { $s = 0; $m++; }
        if ($m === 60) { $m = 0; $d++; }

        return sprintf('%d°%02d\'%02d"%s', $d, $m, $s, $value < 0 ? $negative : $positive);
    }
}
