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

        return view('admin.star-print', [
            'item' => $item,
            'sky' => $item->star_map,
            'shape' => $slot?->shape === 'heart' ? 'heart' : 'circle',
            'style' => $slot?->sky_style ?: 'night',
            'ring' => $slot ? (bool) $slot->sky_ring : true,
            'coordinates' => Sky::coordinates((float) $item->star_map['lat'], (float) $item->star_map['lon']),
        ]);
    }
}
