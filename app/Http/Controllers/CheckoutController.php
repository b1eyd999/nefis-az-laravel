<?php

namespace App\Http\Controllers;

use App\Models\DeliveryMethod;
use App\Models\LivePhoto;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\PromoCode;
use App\Support\Accounting;
use App\Support\Analytics;
use App\Support\Cart;
use App\Support\DeliveryTime;
use App\Support\Epoint;
use App\Support\Telegram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            ->filter(fn (array $item) => $item['product'] !== null || Cart::isExtra($item))
            ->values();

        if ($items->isEmpty()) {
            return redirect(lroute('cart.index'));
        }

        $itemsTotal = $items->sum(fn (array $i) => Cart::unitPrice($i, $i['product']) * $i['quantity']);
        $methods = DeliveryMethod::shown()->get();
        Analytics::beginCheckout($itemsTotal);

        $rushFee = DeliveryTime::rushFee();

        return view('checkout.index', compact('items', 'itemsTotal', 'methods', 'rushFee'));
    }

    /**
     * The page asking whether a code works, before the order is sent. The
     * answer is only what the customer is shown; what he is charged is worked
     * out again in store().
     */
    public function promo(Request $request): \Illuminate\Http\JsonResponse
    {
        $goods = self::goodsOf(Cart::items());

        $promo = PromoCode::byCode($request->string('code')->toString());

        if (! $promo) {
            return response()->json(['ok' => false, 'message' => __('Belə promokod yoxdur.')]);
        }

        if ($why = $promo->refusal($goods)) {
            return response()->json(['ok' => false, 'message' => $why]);
        }

        $discount = $promo->discountOn($goods);

        if ($discount <= 0) {
            return response()->json(['ok' => false, 'message' => __('Bu promokod bu səbətə heç nə tutmur.')]);
        }

        return response()->json([
            'ok' => true,
            'code' => $promo->code,
            'percent' => $promo->percent,
            'discount' => $discount,
            'message' => __(':percent% endirim tətbiq olundu', ['percent' => rtrim(rtrim(number_format($promo->percent, 2, '.', ''), '0'), '.')]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // A cart can outlive the designs it was filled from.
        $items = array_filter(Cart::items(), fn (array $item) => Cart::isExtra($item)
            || Product::whereKey($item['product_id'])->exists());

        if (empty($items)) {
            return redirect(lroute('cart.index'));
        }

        $delivery = $this->validateDelivery($request);

        // Two ways to pay: a transfer to one of the owner's accounts, or a
        // card through ePoint. Either one means the order waits for money and
        // the customer goes to the payment page. With neither — the owner has
        // switched everything off — the order goes through as before and he
        // rings the customer himself.
        $account = PaymentAccount::pick();
        $payable = $account !== null || Epoint::enabled();

        // The code is read again here, whatever the page worked out: the page
        // can be edited, the shop's own answer cannot.
        $goods = self::goodsOf($items);
        $promo = PromoCode::byCode($request->string('promo_code')->toString());
        $discount = $promo && $promo->refusal((float) $goods) === null
            ? $promo->discountOn((float) $goods)
            : 0.0;

        $order = Order::create($delivery + [
            'promo_code' => $discount > 0 ? $promo->code : null,
            'promo_percent' => $discount > 0 ? $promo->percent : null,
            'discount' => $discount,
            'user_id' => $request->user()->id,
            'locale' => \App\Support\Locale::current(),
            'status' => $payable ? 'awaiting_payment' : 'pending',
            'payment_account_id' => $account?->id,
            'note' => $request->input('note'),
        ]);

        $lives = [];
        foreach ($items as $item) {
            if (Cart::isLive($item)) {
                $line = $order->items()->create([
                    'product_id' => null,
                    'product_name' => 'Canlı şəkil',
                    'customer_photos' => [],
                    'custom_texts' => [],
                    'quantity' => 1,
                    'ar_price' => $item['ar']['price'] ?? 0,
                ]);
                $lives[] = LivePhoto::makeFor($line, $item['ar']);

                continue;
            }

            if (Cart::isLetter($item)) {
                $order->items()->create([
                    'product_id' => null,
                    'product_name' => 'Polaroid məktub',
                    'customer_photos' => [],
                    'custom_texts' => [],
                    'quantity' => $item['quantity'],
                    'letter_text' => $item['letter']['text'] ?? null,
                    'letter_photo' => $item['letter']['photo'] ?? null,
                    'letter_price' => $item['letter']['price'] ?? 0,
                ]);

                continue;
            }

            $product = Product::find($item['product_id']);

            $line = $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'customer_photos' => $item['photo_paths'],
                'custom_texts' => $item['custom_texts'],
                // Carts filled before labels were kept take them from the design.
                'photo_labels' => $item['photo_labels'] ?? OrderItem::photoLabelsFor($product),
                'text_labels' => $item['text_labels'] ?? OrderItem::textLabelsFor($product),
                'photo_frames' => array_filter($item['photo_frames'] ?? []) ? $item['photo_frames'] : null,
                'star_map' => $item['star'] ?? null,
                'street_map' => $item['spot'] ?? null,
                // The song, kept as the canonical uri so the code can be
                // redrawn at print size whenever the box is made.
                'spotify_uri' => $item['spotify'] ?? null,
                'quantity' => $item['quantity'],
                'price' => $product?->price,
                'chocolate_id' => $item['chocolate']['id'] ?? null,
                'chocolate_name' => $item['chocolate']['name'] ?? null,
                'chocolate_price' => $item['chocolate']['price'] ?? null,
                // What the bar costs the owner — for the books.
                'chocolate_cost' => $item['chocolate']['cost'] ?? null,
                'wrapping_id' => $item['wrapping']['id'] ?? null,
                'wrapping_name' => $item['wrapping']['name'] ?? null,
                'wrapping_price' => $item['wrapping']['price'] ?? null,
                'letter_text' => $item['letter']['text'] ?? null,
                'letter_photo' => $item['letter']['photo'] ?? null,
                'letter_price' => isset($item['letter']) ? ($item['letter']['price'] ?? 0) : null,
                'ar_price' => isset($item['ar']) ? ($item['ar']['price'] ?? 0) : null,
            ]);
            if (! empty($item['ar'])) {
                $lives[] = LivePhoto::makeFor($line, $item['ar']);
            }
        }

        // The code is not spent here: an order nobody pays for would eat the
        // only use of it. Order::booted() counts it when the money lands.

        // The customers' videos go on to Yandex Disk once the page has been answered.
        if ($lives) {
            defer(function () use ($lives) {
                @set_time_limit(600);
                foreach ($lives as $live) {
                    $live->pushVideo();
                }
            });
        }

        // The boxes' materials come out of stock now.
        Accounting::consume($order);

        // Google counts the order on the next page, once.
        Analytics::purchase($order);

        /* …and the owner hears about it in Telegram — but only once the
           money is in. An order written down is not an order: the customer
           can close the payment page and never come back, and the owner was
           starting boxes for orders nobody had paid for. When there is
           nothing to pay through the site, there is nothing to wait for, so
           it goes out now; otherwise Order::booted() sends it the moment
           payment is confirmed, whichever way it is confirmed. */
        if (! $payable) {
            defer(fn () => Telegram::order($order));
        }

        Cart::clear();

        return $payable
            ? redirect(lroute('orders.pay', $order))
            : redirect(lroute('orders.index'))->with('status', __('Sifarişiniz qəbul edildi! Tezliklə sizinlə əlaqə saxlayacağıq.'));
    }

    /** The day and the part of the day the box is wanted, or the earliest the shop can do. */
    private static function when(array $data): array
    {
        $slots = DeliveryTime::slots();

        return [
            'delivery_date' => $data['delivery_date'] ?? DeliveryTime::earliest()->toDateString(),
            'delivery_slot' => $data['delivery_slot'] ?? $slots[0],
        ];
    }

    /** What the customer pays for jumping the queue, or nothing if he did not ask. */
    private static function rushFee(array $data): ?float
    {
        $fee = DeliveryTime::rushFee();

        return ! empty($data['rush']) && $fee > 0 ? $fee : null;
    }

    /**
     * The chosen delivery and what it needs: an address for the door, name,
     * phone and post-office index for the post, a station for the metro.
     * Returns the order's delivery columns, with the method's name and price
     * as they are now.
     */
    /**
     * What the goods on a basket come to — the same basket the order will
     * hold. The quote and the charge must count the same things, or a design
     * deleted while the customer shopped makes the two disagree.
     */
    private static function goodsOf(array $items): float
    {
        return (float) collect($items)
            ->filter(fn (array $i) => Cart::isExtra($i) || Product::whereKey($i['product_id'] ?? null)->exists())
            ->sum(fn (array $i) => Cart::unitPrice($i, Product::find($i['product_id'] ?? null)) * $i['quantity']);
    }

    private function validateDelivery(Request $request): array
    {
        $common = [
            'contact_phone' => ['required', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:500'],
            // Anything but a string here used to take the checkout down with it.
            'promo_code' => ['nullable', 'string', 'max:32'],
            // Nothing is ready before the shop has had its days. The page always
            // sends a day; without one the earliest possible is taken.
            'delivery_date' => ['nullable', 'date_format:Y-m-d',
                'after_or_equal:' . DeliveryTime::earliest()->toDateString(),
                'before_or_equal:' . DeliveryTime::latest()->toDateString()],
            'delivery_slot' => ['nullable', Rule::in(DeliveryTime::slots())],
            // Made before the others, for the fee the owner asks today.
            'rush' => ['nullable', 'boolean'],
        ];

        // Without any method set up, checkout asks for an address as it always did.
        if (! DeliveryMethod::where('is_active', true)->exists()) {
            $data = $request->validate($common + ['delivery_address' => ['required', 'string', 'max:255']]);

            return ['contact_phone' => $data['contact_phone'], 'delivery_address' => $data['delivery_address']]
                + self::when($data) + ['rush_fee' => self::rushFee($data)];
        }

        $request->validate(['delivery_method_id' => ['required', Rule::exists('delivery_methods', 'id')->where('is_active', true)]],
            ['delivery_method_id.required' => __('Çatdırılma üsulunu seçin.')]);
        $method = DeliveryMethod::findOrFail($request->input('delivery_method_id'));

        $rules = $common + match ($method->type) {
            DeliveryMethod::POST => [
                'recipient_name' => ['required', 'string', 'min:3', 'max:120'],
                'postal_index' => ['required', 'string', 'max:20', function ($attr, $value, $fail) {
                    if (! DeliveryMethod::normalizeIndex((string) $value)) {
                        $fail(__('Poçt indeksi AZ və 4 rəqəm olmalıdır, məs. AZ1000.'));
                    }
                }],
            ],
            DeliveryMethod::METRO => [
                'metro_station' => ['required', Rule::in($method->stations())],
            ],
            default => [
                'delivery_address' => ['required', 'string', 'min:5', 'max:255'],
                // The point picked on the map: only inside Baku.
                'delivery_lat' => ['nullable', 'required_with:delivery_lng', 'numeric'],
                'delivery_lng' => ['nullable', 'required_with:delivery_lat', 'numeric', function ($attr, $value, $fail) use ($request) {
                    if (! DeliveryMethod::inBaku((float) $request->input('delivery_lat'), (float) $value)) {
                        $fail(__('Seçdiyiniz yer Bakıdan kənardadır, qapıya çatdırılma yalnız Bakı daxilindədir.'));
                    }
                }],
            ],
        };

        $data = $request->validate($rules, [], [
            'contact_phone' => 'Telefon', 'recipient_name' => __('Ad və soyad'), 'postal_index' => __('Poçt indeksi'),
            'metro_station' => __('Metro stansiyası'), 'delivery_address' => 'Ünvan',
            'delivery_date' => __('Çatdırılma tarixi'), 'delivery_slot' => __('Çatdırılma vaxtı'),
        ]);

        $order = self::when($data) + [
            'rush_fee' => self::rushFee($data),
            'delivery_method_id' => $method->id,
            'delivery_type' => $method->type,
            'delivery_name' => $method->name,
            'delivery_price' => $method->price,
            'contact_phone' => $data['contact_phone'],
            'recipient_name' => $data['recipient_name'] ?? null,
            'postal_index' => isset($data['postal_index']) ? DeliveryMethod::normalizeIndex($data['postal_index']) : null,
            'metro_station' => $data['metro_station'] ?? null,
            'delivery_address' => $data['delivery_address'] ?? null,
            'delivery_lat' => $data['delivery_lat'] ?? null,
            'delivery_lng' => $data['delivery_lng'] ?? null,
        ];
        // Older screens show the address line; give them something to show.
        $order['delivery_address'] ??= (new Order($order))->deliverySummary();

        return $order;
    }
}
