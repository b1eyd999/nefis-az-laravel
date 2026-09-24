<?php

namespace App\Http\Controllers;

use App\Models\Chocolate;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\LiveMaterials;
use App\Models\TextSlot;
use App\Models\Wrapping;
use App\Support\Analytics;
use App\Support\Cart;
use App\Support\Letter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            ->filter(fn (array $item) => $item['product'] !== null || Cart::isExtra($item))
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

        // Gift wrap is a choice, never a must.
        $rules['wrapping_id'] = ['nullable', Rule::exists('wrappings', 'id')->where('is_active', true)];

        // So is a Polaroid letter inside the box: a photo, words, or both.
        $withLetter = Letter::enabled() && $request->boolean('letter_on');
        if ($withLetter) {
            $rules += Letter::rules();
        }

        // And a live photo (AR): the customer's video, played over the box through a phone.
        // The page sends the box's design as the picture, with the camera's data made from it.
        $withAr = LiveMaterials::enabled() && $request->boolean('ar_on');
        if ($withAr) {
            $rules += LiveMaterials::rules(false);
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
            'wrapping_id.exists' => 'Seçdiyiniz qablaşdırma artıq yoxdur, başqasını seçin.',
            'ar_video.required' => 'Canlı video üçün videonu yükləyin, ya da bu seçimi söndürün.',
        ] + LiveMaterials::messages() + Letter::messages(), $this->slotAttributeNames($product));

        if ($withLetter && ! $request->filled('letter_text') && ! $request->hasFile('letter_photo')) {
            throw ValidationException::withMessages(['letter_text' => 'Məktub üçün şəkil və ya mətn əlavə edin, ya da məktubu söndürün.']);
        }

        // The bar as it is now: its name and price stay with the order.
        $chocolate = null;
        if ($request->filled('chocolate_id') && isset($rules['chocolate_id'])) {
            $bar = Chocolate::findOrFail($request->input('chocolate_id'));
            $chocolate = ['id' => $bar->id, 'name' => trim($bar->name . ' ' . ($bar->weightLabel() ? '(' . $bar->weightLabel() . ')' : '')),
                'price' => $bar->price(), 'cost' => $bar->shopPrice()];
        }

        // The wrap as it is now, likewise.
        $wrapping = null;
        if ($request->filled('wrapping_id')) {
            $wrap = Wrapping::findOrFail($request->input('wrapping_id'));
            $wrapping = ['id' => $wrap->id, 'name' => $wrap->name, 'price' => $wrap->price];
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

        $quantity = (int) $request->input('quantity', 1);
        Cart::add($product->id, $paths, $texts, $quantity,
            OrderItem::photoLabelsFor($product), OrderItem::textLabelsFor($product), $chocolate, $wrapping,
            $withLetter ? Letter::fromRequest($request) : null,
            $withAr ? LiveMaterials::fromRequest($request) : null);

        // The line as it was just added — box, bar, paper, letter and video.
        $line = Cart::items()[array_key_last(Cart::items())] ?? [];
        Analytics::addToCart($product, Cart::unitPrice($line, $product), $quantity);

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
