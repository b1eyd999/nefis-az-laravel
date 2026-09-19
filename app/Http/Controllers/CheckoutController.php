<?php

namespace App\Http\Controllers;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Cart;
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
            ->filter(fn (array $item) => $item['product'] !== null)
            ->values();

        if ($items->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $itemsTotal = $items->sum(fn (array $i) => Cart::unitPrice($i, $i['product']) * $i['quantity']);
        $methods = DeliveryMethod::shown()->get();

        return view('checkout.index', compact('items', 'itemsTotal', 'methods'));
    }

    public function store(Request $request): RedirectResponse
    {
        // A cart can outlive the designs it was filled from.
        $items = array_filter(Cart::items(), fn (array $item) => Product::whereKey($item['product_id'])->exists());

        if (empty($items)) {
            return redirect()->route('cart.index');
        }

        $delivery = $this->validateDelivery($request);

        $order = Order::create($delivery + [
            'user_id' => $request->user()->id,
            'status' => 'pending',
            'note' => $request->input('note'),
        ]);

        foreach ($items as $item) {
            $product = Product::find($item['product_id']);

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'customer_photos' => $item['photo_paths'],
                'custom_texts' => $item['custom_texts'],
                // Carts filled before labels were kept take them from the design.
                'photo_labels' => $item['photo_labels'] ?? OrderItem::photoLabelsFor($product),
                'text_labels' => $item['text_labels'] ?? OrderItem::textLabelsFor($product),
                'quantity' => $item['quantity'],
                'price' => $product?->price,
                'chocolate_id' => $item['chocolate']['id'] ?? null,
                'chocolate_name' => $item['chocolate']['name'] ?? null,
                'chocolate_price' => $item['chocolate']['price'] ?? null,
            ]);
        }

        Cart::clear();

        return redirect()->route('orders.index')->with('status', 'Sifarişiniz qəbul edildi! Tezliklə sizinlə əlaqə saxlayacağıq.');
    }

    /**
     * The chosen delivery and what it needs: an address for the door, name,
     * phone and post-office index for the post, a station for the metro.
     * Returns the order's delivery columns, with the method's name and price
     * as they are now.
     */
    private function validateDelivery(Request $request): array
    {
        $common = [
            'contact_phone' => ['required', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:500'],
        ];

        // Without any method set up, checkout asks for an address as it always did.
        if (! DeliveryMethod::where('is_active', true)->exists()) {
            $data = $request->validate($common + ['delivery_address' => ['required', 'string', 'max:255']]);

            return ['contact_phone' => $data['contact_phone'], 'delivery_address' => $data['delivery_address']];
        }

        $request->validate(['delivery_method_id' => ['required', Rule::exists('delivery_methods', 'id')->where('is_active', true)]],
            ['delivery_method_id.required' => 'Çatdırılma üsulunu seçin.']);
        $method = DeliveryMethod::findOrFail($request->input('delivery_method_id'));

        $rules = $common + match ($method->type) {
            DeliveryMethod::POST => [
                'recipient_name' => ['required', 'string', 'min:3', 'max:120'],
                'postal_index' => ['required', 'string', 'max:20', function ($attr, $value, $fail) {
                    if (! DeliveryMethod::normalizeIndex((string) $value)) {
                        $fail('Poçt indeksi AZ və 4 rəqəm olmalıdır, məs. AZ1000.');
                    }
                }],
            ],
            DeliveryMethod::METRO => [
                'metro_station' => ['required', Rule::in($method->stations())],
            ],
            default => [
                'delivery_address' => ['required', 'string', 'min:5', 'max:255'],
            ],
        };

        $data = $request->validate($rules, [], [
            'contact_phone' => 'Telefon', 'recipient_name' => 'Ad və soyad', 'postal_index' => 'Poçt indeksi',
            'metro_station' => 'Metro stansiyası', 'delivery_address' => 'Ünvan',
        ]);

        $order = [
            'delivery_method_id' => $method->id,
            'delivery_type' => $method->type,
            'delivery_name' => $method->name,
            'delivery_price' => $method->price,
            'contact_phone' => $data['contact_phone'],
            'recipient_name' => $data['recipient_name'] ?? null,
            'postal_index' => isset($data['postal_index']) ? DeliveryMethod::normalizeIndex($data['postal_index']) : null,
            'metro_station' => $data['metro_station'] ?? null,
            'delivery_address' => $data['delivery_address'] ?? null,
        ];
        // Older screens show the address line; give them something to show.
        $order['delivery_address'] ??= (new Order($order))->deliverySummary();

        return $order;
    }
}
