<?php

namespace App\Http\Controllers;

use App\Models\Chocolate;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TextSlot;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        // A bar has to be picked whenever there are bars to pick from.
        if (Chocolate::where('is_active', true)->exists()) {
            $rules['chocolate_id'] = ['required', Rule::exists('chocolates', 'id')->where('is_active', true)->whereNull('deleted_at')];
        }

        foreach ($product->photoSlots as $index => $slot) {
            $rules["photos.$index"] = ['required', 'image', 'max:8192'];
        }

        // A textarea sends CRLF; a line break is one character, as the customer counted it.
        $request->merge(['custom_texts' => array_map(
            fn ($t) => is_string($t) ? str_replace("\r\n", "\n", $t) : $t,
            (array) $request->input('custom_texts', [])
        )]);

        foreach ($product->textSlots as $index => $slot) {
            if ($slot->fixed) {
                continue;   // part of the design; nothing the customer sends counts
            }
            $rules["custom_texts.$index"] = $slot->isTime()
                ? ['required', 'regex:' . TextSlot::TIME_PATTERN]
                : ['nullable', 'string', 'max:' . $slot->limit()];
        }

        $request->validate($rules, [
            'custom_texts.*.regex' => ':attribute dəq:san şəklində olmalıdır, məs. 03:45.',
            'custom_texts.*.max' => ':attribute :max simvoldan uzun ola bilməz.',
            'custom_texts.*.required' => ':attribute boş ola bilməz.',
            'photos.*.required' => ':attribute üçün şəkil yükləyin.',
            'photos.*.image' => ':attribute şəkil olmalıdır (JPG, PNG və s.).',
            'photos.*.max' => ':attribute 8 MB-dan böyük ola bilməz.',
            'quantity.integer' => 'Say tam ədəd olmalıdır.',
            'quantity.min' => 'Say 1 ilə 20 arasında olmalıdır.',
            'quantity.max' => 'Say 1 ilə 20 arasında olmalıdır.',
            'chocolate_id.required' => 'Qutunun içinə şokolad seçin.',
            'chocolate_id.exists' => 'Seçdiyiniz şokolad artıq yoxdur, başqasını seçin.',
        ], $this->slotAttributeNames($product));

        // The bar as it is now: its name and price stay with the order.
        $chocolate = null;
        if ($request->filled('chocolate_id') && isset($rules['chocolate_id'])) {
            $bar = Chocolate::findOrFail($request->input('chocolate_id'));
            $chocolate = ['id' => $bar->id, 'name' => trim($bar->name . ' ' . ($bar->weightLabel() ? '(' . $bar->weightLabel() . ')' : '')),
                'price' => $bar->price(), 'cost' => $bar->shopPrice()];
        }

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
            OrderItem::photoLabelsFor($product), OrderItem::textLabelsFor($product), $chocolate);

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
