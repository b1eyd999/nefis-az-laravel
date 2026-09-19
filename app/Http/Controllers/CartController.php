<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TextSlot;
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
        $product = Product::with(['photoSlots', 'textSlots'])->findOrFail($request->input('product_id'));
        abort_unless($product->is_active && $product->isCustomizable(), 404);

        $rules = [
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];

        foreach ($product->photoSlots as $index => $slot) {
            $rules["photos.$index"] = ['required', 'image', 'max:8192'];
        }

        foreach ($product->textSlots as $index => $slot) {
            if ($slot->fixed) {
                continue;   // part of the design; nothing the customer sends counts
            }
            $rules["custom_texts.$index"] = $slot->isTime()
                ? ['required', 'regex:' . TextSlot::TIME_PATTERN]
                : ['nullable', 'string', 'max:' . $slot->max_length];
        }

        $request->validate($rules, [
            'custom_texts.*.regex' => ':attribute dəq:san şəklində olmalıdır, məs. 03:45.',
        ], $this->slotAttributeNames($product));

        $paths = [];
        foreach ($product->photoSlots as $index => $slot) {
            $paths[] = $request->file("photos.$index")->store('cart-photos', 'public');
        }

        $texts = [];
        foreach ($product->textSlots as $index => $slot) {
            $texts[] = $slot->fixed
                ? (string) $slot->default_value
                : trim((string) $request->input("custom_texts.$index"));
        }

        Cart::add($product->id, $paths, $texts, (int) $request->input('quantity', 1),
            OrderItem::photoLabelsFor($product), OrderItem::textLabelsFor($product));

        return redirect()->route('cart.index')->with('status', 'Məhsul səbətə əlavə olundu.');
    }

    private function slotAttributeNames(Product $product): array
    {
        $names = [];

        foreach ($product->photoSlots as $index => $slot) {
            $names["photos.$index"] = $slot->label ?: 'Şəkil ' . ($index + 1);
        }

        foreach ($product->textSlots as $index => $slot) {
            $names["custom_texts.$index"] = $slot->label ?: 'Mətn ' . ($index + 1);
        }

        return $names;
    }

    public function remove(string $id): RedirectResponse
    {
        Cart::remove($id);

        return back()->with('status', 'Məhsul səbətdən silindi.');
    }
}
