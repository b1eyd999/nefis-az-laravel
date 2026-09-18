<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $items = collect(Cart::items())
            ->map(function (array $item) {
                $item['product'] = Product::find($item['product_id']);

                return $item;
            })
            ->filter(fn (array $item) => $item['product'] !== null)
            ->values();

        if ($items->isEmpty()) {
            return redirect()->route('cart.index');
        }

        return view('checkout.index', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        // A cart can outlive the designs it was filled from.
        $items = array_filter(Cart::items(), fn (array $item) => Product::whereKey($item['product_id'])->exists());

        if (empty($items)) {
            return redirect()->route('cart.index');
        }

        $data = $request->validate([
            'contact_phone' => ['required', 'string', 'max:30'],
            'delivery_address' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $order = Order::create([
            'user_id' => $request->user()->id,
            'status' => 'pending',
            'contact_phone' => $data['contact_phone'],
            'delivery_address' => $data['delivery_address'],
            'note' => $data['note'] ?? null,
        ]);

        foreach ($items as $item) {
            $product = Product::find($item['product_id']);

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'customer_photos' => $item['photo_paths'],
                'custom_texts' => $item['custom_texts'],
                'quantity' => $item['quantity'],
                'price' => $product?->price,
            ]);
        }

        Cart::clear();

        return redirect()->route('orders.index')->with('status', 'Sifarişiniz qəbul edildi! Tezliklə sizinlə əlaqə saxlayacağıq.');
    }
}
