<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Support\Sky;
use Illuminate\View\View;

/**
 * The star map of one order line, drawn big enough to print.
 *
 * Nothing is stored as a picture: the order keeps the date, the hour and the
 * place, and the same script the customer saw redraws the sky here at
 * whatever size the press wants. That is also why an old order can still be
 * remade years later — the sky has not moved, and neither have the numbers.
 */
class StarMapController extends Controller
{
    public function show(OrderItem $item): View
    {
        abort_unless(filled($item->star_map), 404);

        $slot = $item->product?->photoSlots->first(fn ($s) => $s->isSky());
        $sky = $item->star_map;

        /* The design's own shape, not a disc for everything: a full-bleed or
           rectangular sky printed as a circle is not the box that was sold. */
        $shape = in_array($slot?->shape, ['heart', 'full', 'rectangle'], true) ? $slot->shape : 'circle';
        $boxed = $shape === 'full' || $shape === 'rectangle';

        /* A window that fills its shape is printed at its own proportions;
           a disc on a square. 3000 px on the long side either way. */
        $side = 3000;
        $w = max(1, (int) ($slot?->width ?: $side));
        $h = max(1, (int) ($slot?->height ?: $side));
        $long = max($w, $h);

        return view('admin.star-print', [
            'item' => $item,
            'sky' => $sky,
            'shape' => $shape,
            'style' => $slot?->sky_style ?: 'night',
            // The saved column is always true; the strength is the real setting.
            'ring' => $slot?->sky_ring_kind ?: 'degrees',
            // What the customer actually switched on, not the library's defaults.
            'look' => [
                'lines' => (bool) ($sky['lines'] ?? true),
                'labels' => (bool) ($sky['labels'] ?? false),
                'milky' => (bool) ($sky['milky'] ?? false),
                'heart' => (bool) ($sky['heart'] ?? false),
            ],
            'width' => $boxed ? (int) round($side * $w / $long) : $side,
            'height' => $boxed ? (int) round($side * $h / $long) : $side,
            'coordinates' => Sky::coordinates((float) $sky['lat'], (float) $sky['lon']),
        ]);
    }
}
