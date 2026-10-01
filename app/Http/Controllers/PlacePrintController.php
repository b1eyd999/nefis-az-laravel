<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Support\Sky;
use App\Support\StreetMap;
use Illuminate\View\View;

/**
 * The map of one order line, drawn big enough to print.
 *
 * Like the star map next door, nothing is stored as a picture: the order keeps
 * the place, the closeness and the look, and the same script the customer saw
 * draws it here at whatever size the press wants.
 */
class PlacePrintController extends Controller
{
    public function show(OrderItem $item): View
    {
        abort_unless(filled($item->street_map), 404);

        $slot = $item->product?->photoSlots->first(fn ($s) => $s->isMap());
        $spot = $item->street_map;

        /* The design's own shape, not a disc for everything. */
        $shape = in_array($slot?->shape, ['heart', 'home', 'full', 'rectangle'], true) ? $slot->shape : 'circle';
        $boxed = in_array($shape, ['full', 'rectangle', 'home'], true);

        $side = 3000;
        $w = max(1, (int) ($slot?->width ?: $side));
        $h = max(1, (int) ($slot?->height ?: $side));
        $long = max($w, $h);

        return view('admin.place-print', [
            'item' => $item,
            'spot' => $spot,
            'shape' => $shape,
            /* The look is frozen on the order, so a design recoloured since
               does not recolour what was bought. */
            'style' => in_array($spot['style'] ?? null, \App\Models\PhotoSlot::MAP_STYLES, true)
                ? $spot['style']
                : ($slot?->map_style ?: 'ink'),
            'marker' => in_array($spot['marker'] ?? null, \App\Models\PhotoSlot::MAP_MARKERS, true)
                ? $spot['marker']
                : ($slot?->map_marker ?: 'heart'),
            'pin' => (bool) ($spot['pin'] ?? true),
            'zoom' => StreetMap::zoom($spot['zoom'] ?? 15),
            'width' => $boxed ? (int) round($side * $w / $long) : $side,
            'height' => $boxed ? (int) round($side * $h / $long) : $side,
            // Only the window is read off the design as it stands today.
            'stale' => $slot === null,
            'coordinates' => Sky::coordinates((float) $spot['lat'], (float) $spot['lon']),
        ]);
    }
}
