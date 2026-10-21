<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\Chocolate;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\LiveMaterials;
use App\Models\TextSlot;
use App\Models\Wrapping;
use App\Support\Analytics;
use App\Support\Cart;
use App\Support\DeliveryTime;
use App\Support\Letter;
use App\Support\Sky;
use App\Support\StreetMap;
use App\Support\SpotifyCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    /** The most a photo may weigh; the design page shrinks anything heavier before sending. */
    public const PHOTO_MAX_KB = 8192;

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
        /* One design, named by its number. Given an array, findOrFail()
           hands back a collection and everything below it breaks on a
           500 instead of saying what was wrong. */
        $request->validate(['product_id' => ['required', 'integer']]);

        $product = Product::with(['photoSlots', 'textSlots'])->findOrFail($request->integer('product_id'));
        abort_unless($product->openTo($request->user()) && $product->isCustomizable(), 404);

        /* A design the owner has not priced says «Qiymət sorğu ilə» on its
           card, and used to go into the basket all the same with the box
           counted as nothing: the customer paid for the chocolate bar and
           got the box for free, and the order looked ordinary in the admin.
           It is not for sale until it has a price. */
        if (! $product->price) {
            return redirect(lroute('products.customize', $product))
                ->withErrors(['product_id' => __('Bu dizaynın qiyməti hələ təyin olunmayıb — bizə yazın, sizin üçün hesablayaq.')]);
        }

        /* A ready package. What is in it is the owner's answer, not the
           form's: the price was agreed for that exact box, that bar and that
           paper, so the parts are written over whatever was posted. The page
           shows them locked, but a locked control is a courtesy to an honest
           customer and nothing more — this is where it is actually decided. */
        $bundle = null;
        if ($request->filled('bundle_id')) {
            $bundle = Bundle::offered()->whereKey($request->integer('bundle_id'))->first();
            abort_unless($bundle && $bundle->product_id === $product->id, 404);

            $request->merge([
                'chocolate_id' => $bundle->chocolate_id,
                'wrapping_id' => $bundle->wrapping_id,
                'letter_on' => $bundle->letter_on ? 1 : 0,
                'ar_on' => $bundle->ar_on ? 1 : 0,
            ]);
        }

        $rules = [
            'quantity' => ['nullable', 'integer', 'min:1', 'max:' . Cart::most()],
            'rush' => ['nullable', 'boolean'],
        ];

        // A bar has to be picked whenever there are bars to pick from.
        if (Chocolate::where('is_active', true)->exists()) {
            $rules['chocolate_id'] = [
                /* Unless a package says there is no bar in it: then there is
                   nothing for the customer to pick and nothing to require. */
                $bundle && ! $bundle->chocolate_id ? 'nullable' : 'required',
                Rule::exists('chocolates', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ];
        }

        foreach ($product->photoSlots as $index => $slot) {
            if (! $slot->needsUpload()) {
                continue;               // the sky and the map fill themselves
            }
            $rules["photos.$index"] = ['required', 'image', 'max:' . self::PHOTO_MAX_KB];
            // How the photo sits in its window, as the page's own JSON; a
            // browser without the script simply sends none.
            $rules["photo_frames.$index"] = ['nullable', 'string', 'max:300', 'json'];
        }

        // Gift wrap is a choice, never a must.
        $rules['wrapping_id'] = ['nullable', Rule::exists('wrappings', 'id')->where('is_active', true)];

        if (Sky::wanted($product)) {
            $rules['star_date'] = ['required', 'date', 'after:1899-12-31', 'before:' . now()->addYears(2)->toDateString()];
            $rules['star_time'] = ['required', 'date_format:H:i'];
            $rules['star_lat'] = ['required', 'numeric', 'between:-90,90'];
            $rules['star_lon'] = ['required', 'numeric', 'between:-180,180'];
            $rules['star_place'] = ['nullable', 'string', 'max:60'];
            foreach (\App\Models\PhotoSlot::SKY_CHOICES as $choice) {
                $rules[Sky::field($choice)] = ['nullable', 'boolean'];
            }
        }

        if (StreetMap::wanted($product)) {
            /* Either a point he tapped on the map, or just the name he typed.
               They used to be required outright, and the only thing that ever
               filled them was tapping a suggestion in the dropdown — so a
               customer who typed «Sahil» and pressed his keyboard's done key
               was refused by two hidden fields, and told to upload a
               photograph this design does not have. */
            $rules['map_lat'] = ['required_without:map_place', 'nullable', 'numeric', 'between:-85,85'];
            $rules['map_lon'] = ['required_without:map_place', 'nullable', 'numeric', 'between:-180,180'];
            $rules['map_place'] = ['required_without_all:map_lat,map_lon', 'nullable', 'string', 'min:2', 'max:60'];
            $rules['map_zoom'] = ['nullable', 'integer', 'between:' . StreetMap::ZOOM_MIN . ',' . StreetMap::ZOOM_MAX];
            $rules['map_date'] = ['nullable', 'date', 'after:1899-12-31', 'before:' . now()->addYears(2)->toDateString()];
            $rules['map_time'] = ['nullable', 'date_format:H:i'];
            $rules['map_with_time'] = ['nullable', 'boolean'];
            foreach (\App\Models\PhotoSlot::MAP_CHOICES as $choice) {
                $rules[StreetMap::field($choice)] = ['nullable'];
            }
        }

        // The song, on the designs built around one. Never required: the box
        // is worth ordering without it. Checked here as well as in the page,
        // because a link that is not Spotify's draws no code at the press.
        $song = null;
        if ($product->spotify_code) {
            $rules['spotify_uri'] = ['nullable', 'string', 'max:300', function (string $attribute, $value, $fail) {
                if (filled($value) && ! SpotifyCode::isValid($value)) {
                    $fail(__('Spotify linki tanınmadı. Spotify-da mahnını açın → Paylaş → Linki kopyala.'));
                }
            }];
        }

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
            if ($slot->isGiven()) {
                continue;   // set in the design, or worked out here; nothing sent counts
            }
            $rules["custom_texts.$index"] = $slot->isTime()
                ? ['required', 'regex:' . TextSlot::TIME_PATTERN]
                : ['nullable', 'string', 'max:' . $slot->limit()];
        }

        $request->validate($rules, [
            'custom_texts.*.regex' => __(':attribute dəq:san şəklində olmalıdır, məs. 03:45.'),
            'custom_texts.*.max' => __(':attribute :max simvoldan uzun ola bilməz.'),
            'custom_texts.*.required' => __(':attribute boş ola bilməz.'),
            'photos.*.required' => __(':attribute üçün şəkil yükləyin.'),
            'photos.*.image' => __(':attribute şəkil olmalıdır (JPG, PNG və s.).'),
            'photos.*.max' => __(':attribute 8 MB-dan böyük ola bilməz.'),
            'quantity.integer' => __('Say tam ədəd olmalıdır.'),
            'quantity.min' => __('Say 1 ilə :most arasında olmalıdır.', ['most' => Cart::most()]),
            'quantity.max' => __('Say 1 ilə :most arasında olmalıdır.', ['most' => Cart::most()]),
            'map_place.required_without_all' => __('Hansı yerin küçələri olsun? Yeri yazın.'),
            'map_lat.required_without' => __('Hansı yerin küçələri olsun? Yeri yazın.'),
            'map_lon.required_without' => __('Hansı yerin küçələri olsun? Yeri yazın.'),
            'chocolate_id.required' => __('Qutunun içinə şokolad seçin.'),
            'chocolate_id.exists' => __('Seçdiyiniz şokolad artıq yoxdur, başqasını seçin.'),
            'wrapping_id.exists' => __('Seçdiyiniz qablaşdırma artıq yoxdur, başqasını seçin.'),
            'ar_video.required' => __('Canlı video üçün videonu yükləyin, ya da bu seçimi söndürün.'),
        ] + LiveMaterials::messages() + Letter::messages(), $this->slotAttributeNames($product));

        if ($withLetter && ! $request->filled('letter_text') && ! $request->hasFile('letter_photo')) {
            throw ValidationException::withMessages(['letter_text' => __('Məktub üçün şəkil və ya mətn əlavə edin, ya da məktubu söndürün.')]);
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
        $frames = [];
        foreach ($product->photoSlots as $index => $slot) {
            if (! $slot->needsUpload()) {
                continue;
            }
            $paths[] = $request->file("photos.$index")->store('cart-photos', 'public');
            $frames[] = OrderItem::frameOrNull(json_decode((string) $request->input("photo_frames.$index"), true));
        }

        $skySlot = $product->photoSlots->first(fn ($slot) => $slot->isSky());
        $star = $skySlot ? Sky::fromRequest($request, $skySlot) : null;

        $mapSlot = StreetMap::slot($product);
        /* He may have simply typed the name rather than tapped a suggestion.
           The shop looks it up with the same geocoder the dropdown uses, so
           what he typed is enough — and if the place cannot be found he is
           told that, beside the field, rather than being refused by two
           hidden ones. */
        if ($mapSlot && ! $request->filled('map_lat')) {
            StreetMap::resolveTyped($request);
        }
        $spot = $mapSlot ? StreetMap::fromRequest($request, $mapSlot) : null;

        $texts = [];
        foreach ($product->textSlots as $index => $slot) {
            $texts[] = match (true) {
                /* A design holds the sky or a place, never both, and the
                   same four captions answer for either. */
                $slot->isAuto() => $spot ? StreetMap::caption($slot->auto, $spot) : Sky::caption($slot->auto, $star),
                (bool) $slot->fixed => (string) $slot->default_value,
                default => trim((string) $request->input("custom_texts.$index")),
            };
        }

        // Asked for here, paid once at the end: the whole order is hurried,
        // not this one box, so the box's own tick sets it for the order — and
        // unticking it clears it, or three manat the customer said no to
        // would be charged from the checkout page's pre-ticked box. A page
        // rendered without the control (no fee offered) touches nothing.
        if (DeliveryTime::rushFee() > 0) {
            Cart::setRush($request->boolean('rush'));
        }

        if ($product->spotify_code) {
            $song = SpotifyCode::uri($request->input('spotify_uri'));
        }

        $quantity = (int) $request->input('quantity', 1);
        Cart::add($product->id, $paths, $texts, $quantity,
            OrderItem::photoLabelsFor($product), OrderItem::textLabelsFor($product), $chocolate, $wrapping,
            $withLetter ? Letter::fromRequest($request) : null,
            $withAr ? LiveMaterials::fromRequest($request) : null, $song, $frames, $star, $spot,
            $bundle?->forCart());

        // The line as it was just added — box, bar, paper, letter and video.
        $line = Cart::items()[array_key_last(Cart::items())] ?? [];
        Analytics::addToCart($product, Cart::unitPrice($line, $product), $quantity);

        return redirect(lroute('cart.index'))->with('status', __('Məhsul səbətə əlavə olundu.'));
    }

    private function slotAttributeNames(Product $product): array
    {
        $names = [];

        /* Named the way the page names them. The messages around these are
           translated, and they were being filled with an Azerbaijani field
           name — «Şəkil 1 шəkil olmalıdır» for a Russian customer. */
        foreach ($product->photoSlots as $index => $slot) {
            $names["photos.$index"] = $slot->label ? __($slot->tr('label')) : __('Şəkil') . ' ' . ($index + 1);
        }

        foreach ($product->textSlots as $index => $slot) {
            $names["custom_texts.$index"] = $slot->label ? __($slot->tr('label')) : __('Mətn') . ' ' . ($index + 1);
        }

        return $names;
    }

    /**
     * More or fewer of one line.
     *
     * Until now the basket printed «1 ədəd» and that was that: wanting two
     * of the same box meant going back through the whole design again. The
     * number is read here and nowhere else, so a figure typed into the page
     * cannot ask for four hundred boxes.
     */
    public function quantity(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:' . Cart::most()],
        ], [
            'quantity.max' => __('Bir dizayndan ən çox :most ədəd sifariş etmək olar. Daha çoxu üçün bizə yazın.',
                ['most' => Cart::most()]),
        ]);

        Cart::setQuantity($id, (int) $data['quantity']);

        return back()->with('status', __('Say dəyişdirildi.'));
    }

    public function remove(string $id): RedirectResponse
    {
        Cart::remove($id);

        return back()->with('status', __('Məhsul səbətdən silindi.'));
    }
}
