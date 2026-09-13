<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(): View
    {
        $items = collect(Cart::items())
            ->map(function (array $item) {
                $item['product'] = Product::find($item['product_id']);

                return $item;
            })
            ->filter(fn (array $item) => $item['product'] !== null)
            ->values();

        return view('cart.index', compact('items'));
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'photo' => ['required', 'image', 'max:8192'],
            'custom_text' => ['nullable', 'string', 'max:120'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $path = $request->file('photo')->store('cart-photos', 'public');

        Cart::add(
            (int) $data['product_id'],
            $path,
            $data['custom_text'] ?? null,
            (int) ($data['quantity'] ?? 1)
        );

        return redirect()->route('cart.index')->with('status', 'Məhsul səbətə əlavə olundu.');
    }

    public function remove(string $id): RedirectResponse
    {
        Cart::remove($id);

        return back()->with('status', 'Məhsul səbətdən silindi.');
    }
}
