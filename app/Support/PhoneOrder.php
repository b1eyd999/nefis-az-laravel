<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * An order the owner writes down himself.
 *
 * A customer writes on WhatsApp, sends the photograph and the words, and has
 * no time to sit down at the site. The owner puts the same thing into the
 * panel: a name, a number, the box, the photographs, the captions. What comes
 * out is an ordinary order — the same lines, the same stock taken off the
 * shelf, the same card in Telegram — so nothing downstream has to know it did
 * not come through the checkout.
 *
 * The customer gets an account made from his number, found again the next
 * time he writes from it. It has no e-mail he gave: the address it carries is
 * a placeholder that can never receive anything, and the shop's own letters
 * skip it.
 */
class PhoneOrder
{
    /** Where the photographs the owner uploads are kept: with the checkout's own. */
    public const PHOTO_DIR = 'cart-photos';

    /**
     * The customer for this name and number: the account that already has the
     * number, or a new one made now.
     */
    public static function customer(string $name, string $phone): User
    {
        $number = Contact::az($phone) ?? Contact::clean($phone);

        if ($known = User::samePhone($number)->orderBy('id')->first()) {
            return $known;
        }

        $digits = preg_replace('/\D/', '', $number);

        return User::create([
            'name' => trim($name),
            'email' => 'tel-'.$digits.'-'.Str::lower(Str::random(6)).'@nefis.invalid',
            'phone' => $number,
            'password' => Hash::make(Str::random(40)),
        ]);
    }

    /**
     * Whether an address is one of the placeholders made here, which no letter
     * can reach.
     */
    public static function isPlaceholder(?string $email): bool
    {
        return str_ends_with(Str::lower(trim((string) $email)), '@nefis.invalid');
    }

    /**
     * Write the order down.
     *
     * @param  array{
     *     name: string, phone: string, product_id: int, quantity?: int, price?: ?float,
     *     photos?: array<int, string>, texts?: array<int, string>, note?: ?string,
     *     status?: string, delivery_address?: ?string, delivery_date?: ?string,
     *     delivery_slot?: ?string, delivery_price?: ?float, recipient_name?: ?string
     * }  $data  `photos` are paths already stored, in the order of the design's windows;
     *            `texts` are one per caption of the design, in its order.
     */
    public static function create(array $data): Order
    {
        $product = Product::with(['photoSlots', 'textSlots'])->findOrFail($data['product_id']);
        $customer = self::customer($data['name'], $data['phone']);
        $phone = Contact::az($data['phone']) ?? Contact::clean($data['phone']);
        $status = in_array($data['status'] ?? null, ['awaiting_payment', 'confirmed'], true) ? $data['status'] : 'confirmed';
        $fee = isset($data['delivery_price']) && $data['delivery_price'] !== '' ? max(0, (float) $data['delivery_price']) : null;

        $texts = [];
        foreach ($product->textSlots as $index => $slot) {
            $texts[] = $slot->isGiven() && ! $slot->isAuto()
                ? (string) $slot->default_value
                : trim((string) ($data['texts'][$index] ?? ''));
        }

        $order = DB::transaction(function () use ($data, $product, $customer, $phone, $status, $fee, $texts) {
            $order = Order::create([
                'user_id' => $customer->id,
                'status' => $status,
                'contact_phone' => $phone,
                'recipient_name' => filled($data['recipient_name'] ?? null) ? trim($data['recipient_name']) : null,
                'delivery_address' => filled($data['delivery_address'] ?? null) ? trim($data['delivery_address']) : null,
                'delivery_name' => $fee !== null ? 'Kuryer' : null,
                'delivery_price' => $fee,
                'delivery_date' => $data['delivery_date'] ?? null,
                'delivery_slot' => $data['delivery_slot'] ?? null,
                'note' => trim('WhatsApp ilə sifariş. '.($data['note'] ?? '')),
                'locale' => Locale::DEFAULT,
                // Settled over WhatsApp: there is no payment page to wait for.
                'payment_confirmed_at' => $status === 'confirmed' ? now() : null,
            ]);

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'customer_photos' => array_values($data['photos'] ?? []),
                'custom_texts' => $texts,
                'photo_labels' => OrderItem::photoLabelsFor($product),
                'text_labels' => OrderItem::textLabelsFor($product),
                'quantity' => max(1, (int) ($data['quantity'] ?? 1)),
                'price' => isset($data['price']) && $data['price'] !== '' ? max(0, (float) $data['price']) : $product->price,
            ]);

            return $order;
        });

        // The boxes' materials come out of stock, as for any order.
        Accounting::consume($order);

        /* The owner's card in Telegram. An order that is already settled never
           reaches the hook that sends it (that one fires when the payment is
           confirmed on an existing order), and an unsettled one has not earned
           the card yet — the same rule the checkout follows. */
        if ($status === 'confirmed') {
            defer(fn () => Telegram::order($order->fresh()));
        }

        return $order;
    }
}
