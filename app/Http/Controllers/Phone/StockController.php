<?php

namespace App\Http\Controllers\Phone;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Support\Accounting;
use App\Support\Price;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The shelves, on a phone — because this is the screen he wants while
 * standing in a shop deciding whether to buy more paper.
 *
 * Buying and counting go through Accounting, never straight into the stock
 * column: a purchase is also an expense, and a stocktake is a movement the
 * books have to see. What a pack costs and how much of it a box eats is set
 * once, on the computer, and is read-only here — a wrong number there quietly
 * spoils every cost figure afterwards.
 */
class StockController extends Controller
{
    public function index(): View
    {
        return view('phone.stock', [
            'materials' => Material::where('is_active', true)->orderBy('stock')->orderBy('sort_order')->get(),
            'perBox' => Material::costOfOneBox(),
        ]);
    }

    public function purchase(Request $request, Material $material): RedirectResponse
    {
        $data = $request->validate([
            'packs' => ['required', 'numeric', 'min:0.001'],
            'pack_price' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'packs.required' => 'Neçə paçka aldığınızı yazın.',
            'packs.min' => 'Paçka sayı sıfırdan böyük olmalıdır.',
            'pack_price.required' => 'Bir paçkanın qiymətini yazın.',
        ]);

        $movement = Accounting::purchase($material, (float) $data['packs'], (float) $data['pack_price'], $data['note'] ?? null);

        return back()->with('phone.flash', [
            'title' => $material->name,
            'body' => '+' . self::qty((float) $data['packs'] * $material->pack_size) . ' ' . $material->unit
                . ' · xərc ' . Price::format((float) $movement->amount),
        ]);
    }

    public function adjust(Request $request, Material $material): RedirectResponse
    {
        $data = $request->validate([
            'counted' => ['required', 'numeric', 'min:0'],
        ], ['counted.required' => 'Anbarda neçə olduğunu yazın.']);

        $movement = Accounting::adjust($material, (float) $data['counted']);

        // A stocktake that matches the books writes nothing, and saying
        // "saved" then would be a small lie.
        return back()->with('phone.flash', $movement === null
            ? ['title' => 'Dəyişiklik yoxdur', 'body' => $material->name . ' — sayım anbardakı ilə eynidir.']
            : ['title' => 'Sayım yazıldı', 'body' => $material->name . ' — ' . self::qty((float) $data['counted']) . ' ' . $material->unit]);
    }

    /**
     * Quantities as a person writes them: 1 rather than 1.000, and 0.196
     * rather than what a float really holds.
     */
    public static function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ' '), '0'), '.');
    }
}
