<?php

namespace App\Support;

use App\Models\PhotoSlot;
use App\Models\Product;

/**
 * The place a customer puts on a box: where it is, how close in, and in what
 * colours.
 *
 * Nothing here draws anything — the drawing happens in the browser, in
 * public/js/street-map.js, over a picture of the streets fetched through
 * App\Support\MapImage. What is kept on the order is the question, not the
 * picture, so the sheet can be made again at printing size long after.
 */
class StreetMap
{
    /** How a map looks when nothing has been chosen about it. */
    public const LOOK = ['zoom' => 15, 'pin' => true];

    /** How close in the customer may go: a whole city down to one courtyard. */
    public const ZOOM_MIN = 11;

    public const ZOOM_MAX = 18;

    /** Does this design ask for a map at all? */
    public static function wanted(Product $product): bool
    {
        return $product->photoSlots->contains(fn (PhotoSlot $slot) => $slot->isMap());
    }

    /** The window that holds the map, if the design has one. */
    public static function slot(Product $product): ?PhotoSlot
    {
        return $product->photoSlots->first(fn (PhotoSlot $slot) => $slot->isMap());
    }

    /**
     * What the shop keeps on the order line.
     *
     * The switches are only read where the design offers them; anything else
     * keeps the look the owner set, whatever the page sends.
     *
     * @return array<string, mixed>|null
     */
    public static function fromRequest(\Illuminate\Http\Request $request, ?PhotoSlot $slot = null): ?array
    {
        if (! $request->filled('map_lat') || ! $request->filled('map_lon')) {
            return null;
        }

        $look = $slot ? $slot->mapDefaults() : self::LOOK;

        foreach ($slot ? $slot->mapChoices() : [] as $choice) {
            if ($choice === 'zoom') {
                $look['zoom'] = self::zoom($request->input('map_zoom', $look['zoom']));
            } else {
                $look[$choice] = $request->boolean(self::field($choice));
            }
        }

        $withTime = $request->boolean('map_with_time') && $request->filled('map_time');

        return $look + [
            'lat' => round((float) $request->input('map_lat'), 5),
            'lon' => round((float) $request->input('map_lon'), 5),
            'place' => $request->filled('map_place')
                ? mb_substr(trim((string) $request->input('map_place')), 0, 60)
                : null,
            'date' => $request->filled('map_date') ? (string) $request->input('map_date') : null,
            'time' => (string) ($request->input('map_time') ?: ''),
            'withTime' => $withTime,
            // The look is the owner's, and it is frozen here so a design
            // recoloured next year does not recolour an old order.
            'style' => $slot && in_array($slot->map_style, PhotoSlot::MAP_STYLES, true)
                ? $slot->map_style
                : 'ink',
            'marker' => $slot && in_array($slot->map_marker, PhotoSlot::MAP_MARKERS, true)
                ? $slot->map_marker
                : 'heart',
        ];
    }

    /** Inside the range the shop offers, and a whole number. */
    public static function zoom(mixed $value): int
    {
        return max(self::ZOOM_MIN, min(self::ZOOM_MAX, (int) $value));
    }

    /** The name the switch travels under. */
    public static function field(string $choice): string
    {
        return 'map_' . $choice;
    }

    /** The words on the switches, as the customer reads them. */
    public static function choiceLabels(): array
    {
        return [
            'zoom' => __('Yaxınlığı özüm seçim'),
            'pin' => __('Nöqtə işarələnsin'),
        ];
    }

    /** The words on the styles, as the owner reads them in the editor. */
    public static function styleLabels(): array
    {
        return [
            'ink' => __('Qara fon, ağ küçələr'),
            'paper' => __('Ağ fon, qara küçələr'),
            'sea' => __('Dəniz mavisi'),
            'colour' => __('Rəngli'),
        ];
    }

    /**
     * What an automatic caption says for this place. The same four kinds the
     * star map offers, so a caption slot does not care which of the two the
     * design holds.
     *
     * @param  array<string, mixed>|null  $map
     */
    public static function caption(string $kind, ?array $map): string
    {
        if (! $map) {
            return '';
        }

        $withTime = ! empty($map['withTime']) && ! empty($map['time']);

        return match ($kind) {
            'coords' => Sky::coordinates((float) $map['lat'], (float) $map['lon']),
            'place' => (string) ($map['place'] ?? ''),
            'date', 'date_long' => self::dated($kind, $map, $withTime),
            default => '',
        };
    }

    /** The date line, left empty when the customer did not give one. */
    private static function dated(string $kind, array $map, bool $withTime): string
    {
        if (empty($map['date'])) {
            return '';
        }

        $date = \Illuminate\Support\Carbon::parse($map['date']);

        return $kind === 'date'
            ? $date->format('d.m.Y') . ($withTime ? ', ' . $map['time'] : '')
            : $date->day . ' ' . (Sky::MONTHS[$date->month - 1] ?? '') . ' ' . $date->year
                . ($withTime ? ', ' . $map['time'] : '');
    }

    /**
     * What must be printed beside a map, and shown where it is sold. The
     * street data is OpenStreetMap's, and saying so is the one thing its
     * licence asks in return.
     */
    public static function credit(): string
    {
        return '© OpenStreetMap';
    }

    public static function creditLong(): string
    {
        return __('Xəritə məlumatları: © OpenStreetMap iştirakçıları, ODbL lisenziyası ilə.');
    }
}
