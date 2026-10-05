<?php

namespace App\Models;

use App\Support\Accounting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    /** Where an order stands, from the customer's payment to the parcel. */
    public const STATUSES = [
        'awaiting_payment' => 'Ödəniş gözlənilir',
        'payment_check' => 'Çek yoxlanılır',
        'pending' => 'Gözləmədə',
        'confirmed' => 'Təsdiqləndi',
        'ready' => 'Hazırdır',
        'completed' => 'Tamamlandı',
        'cancelled' => 'Ləğv edildi',
        'refunded' => 'Vəsait qaytarıldı',
    ];

    /**
     * The two endings that are not a sale: the order was called off, or the
     * money was given back. Neither counts as income, and the materials of
     * both go back on the shelf.
     */
    public const OFF_THE_BOOKS = ['cancelled', 'refunded'];

    protected $fillable = [
        'user_id',
        'status',
        'contact_phone',
        'delivery_address',
        'note',
        // How it is delivered, as chosen at checkout (kept even if the
        // method's name or price changes later).
        'delivery_method_id',
        'delivery_type',
        'delivery_name',
        'delivery_price',
        'rush_fee',
        'delivery_date',
        'delivery_slot',
        'recipient_name',
        'postal_index',
        'metro_station',
        'delivery_lat',
        'delivery_lng',
        'locale',
        // The promo code as it was typed, what it was worth then, and the
        // manats it took off — all frozen, so the order still adds up after
        // the code itself is edited or deleted.
        'promo_code',
        'promo_percent',
        'discount',
        'materials_cost',
        // Which courier took it, and the message in the group he took it from.
        'courier_name',
        'courier_taken_at',
        'courier_chat_id',
        'courier_message_id',
        // Paying by transfer: which account the money goes to, and the receipt.
        'payment_account_id',
        'payment_started_at',
        'payment_asked_for',
        // Card payments through ePoint: which way it was paid, the reference
        // the gateway was given and the gateway's own transaction id.
        'payment_method',
        'epoint_ref',
        'epoint_transaction',
        'payment_receipt',
        'receipt_at',
        'payment_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_price' => 'float',
            'rush_fee' => 'float',
            'promo_percent' => 'float',
            'discount' => 'float',
            'delivery_date' => 'date',
            'delivery_lat' => 'float',
            'delivery_lng' => 'float',
            'materials_cost' => 'float',
            'courier_taken_at' => 'datetime',
            'receipt_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'payment_started_at' => 'datetime',
            'payment_asked_for' => 'float',
        ];
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Still to be paid for: the customer is sent back to the payment page. */
    public function awaitsPayment(): bool
    {
        return in_array($this->status, ['awaiting_payment', 'payment_check'], true);
    }

    /**
     * The order a customer walked away from without paying.
     *
     * Placing the order empties the basket, so a visitor who left the payment
     * page and came back to the shop found nothing anywhere and thought his
     * order had vanished. Every page tells him it is waiting.
     */
    public static function unpaidFor(?User $user): ?self
    {
        if (! $user) {
            return null;
        }

        return self::where('user_id', $user->id)
            ->where('status', 'awaiting_payment')
            ->whereNull('payment_confirmed_at')
            ->latest('id')
            ->first()
            ?->whenNotInFlight();
    }

    /**
     * The customer is at the bank, or has just come back from it and the
     * bank's own message to us is still on its way. Asking him to pay again
     * now is asking for a second charge.
     */
    public function paymentInFlight(): bool
    {
        return $this->payment_started_at !== null
            && $this->payment_confirmed_at === null
            && $this->payment_started_at->gt(now()->subMinutes(\App\Support\Epoint::IN_FLIGHT_MINUTES));
    }

    private function whenNotInFlight(): ?self
    {
        return $this->paymentInFlight() ? null : $this;
    }

    public function receiptUrl(): ?string
    {
        return $this->payment_receipt ? \App\Support\Media::url($this->payment_receipt) : null;
    }

    protected static function booted(): void
    {
        // An order taken off the books altogether: what its boxes took out of
        // stock goes back — unless it was cancelled first and already did.
        static::deleting(function (Order $order) {
            if (! in_array($order->status, self::OFF_THE_BOOKS, true)) {
                Accounting::restore($order);
            }
        });

        // A promo code is spent when the money arrives, not when the order
        // is written down. Whichever way payment is confirmed — the gateway's
        // own word, the owner's button, the phone admin — it passes here.
        static::updated(function (Order $order) {
            if ($order->wasChanged('payment_confirmed_at')
                && $order->payment_confirmed_at !== null
                && filled($order->promo_code)) {
                \App\Models\PromoCode::where('code', $order->promo_code)->first()?->used();
            }
        });

        // Cancelling puts the boxes' materials back in stock; bringing an
        // order back from cancelled takes them again.
        static::updated(function (Order $order) {
            if (! $order->wasChanged('status')) {
                return;
            }
            $was = (string) $order->getOriginal('status');
            $offNow = in_array($order->status, self::OFF_THE_BOOKS, true);
            $offBefore = in_array($was, self::OFF_THE_BOOKS, true);
            if ($offNow && ! $offBefore) {
                Accounting::restore($order);
            } elseif ($offBefore && ! $offNow) {
                Accounting::consume($order);
            }

            // Wherever the status was changed from — the list, the order's own
            // page, the payment flow — the customer hears about it here.
            \App\Support\CustomerNotice::email($order);

            // Made and waiting: the courier gets the address and the phone.
            if ($order->status === 'ready' && $was !== 'ready') {
                \App\Support\Telegram::courier($order);
            }
        });
    }

    /** The door-delivery point in Google Maps, for the courier. */
    public function mapUrl(): ?string
    {
        return $this->delivery_lat && $this->delivery_lng
            ? 'https://www.google.com/maps/search/?api=1&query=' . $this->delivery_lat . ',' . $this->delivery_lng
            : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function itemsTotal(): float
    {
        return (float) $this->items->sum(fn (OrderItem $i) => $i->unitPrice() * $i->quantity);
    }

    /** The goods, before any code was taken off them. */
    public function goodsTotal(): float
    {
        return $this->itemsTotal();
    }

    /**
     * What came off the goods — never more than the goods themselves.
     *
     * The discount is frozen on the order, and the order can shrink after it:
     * two boxes bought with a code worth all of them, then one line taken off
     * in the admin. Unclamped, the total went negative and the shop was told
     * to give back more than it ever took. The stored figure is left alone so
     * the order still says what the code was worth on the day.
     */
    public function discountOff(): float
    {
        return round(min($this->itemsTotal(), (float) ($this->discount ?? 0)), 2);
    }

    public function total(): float
    {
        return round($this->itemsTotal() - $this->discountOff()
            + (float) ($this->delivery_price ?? 0) + (float) ($this->rush_fee ?? 0), 2);
    }

    public function hasDiscount(): bool
    {
        return (float) ($this->discount ?? 0) > 0;
    }

    /** Everything the order was changed to after the customer had paid. */
    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class)->orderBy('id');
    }

    /** @return \Illuminate\Support\Collection<int, OrderAdjustment> */
    private function openAdjustments(): \Illuminate\Support\Collection
    {
        return $this->relationLoaded('adjustments')
            ? $this->adjustments->filter(fn (OrderAdjustment $a) => $a->isOpen())
            : $this->adjustments()->whereIn('status', [OrderAdjustment::WAITING, OrderAdjustment::CHECK])->get();
    }

    /** What the customer still owes for changes made after he paid. */
    public function outstanding(): float
    {
        return round((float) $this->openAdjustments()
            ->where('kind', OrderAdjustment::CHARGE)->sum('amount'), 2);
    }

    /** What the shop owes him back, because the order got smaller. */
    public function owedBack(): float
    {
        return round((float) $this->openAdjustments()
            ->where('kind', OrderAdjustment::REFUND)->sum('amount'), 2);
    }

    /**
     * How much of this order has actually been paid for.
     *
     * There is no column for it and none is needed: the order is worth
     * `total()` today, the customer is behind by whatever is still owed and
     * ahead by whatever is owed back to him. It stays true only while every
     * change to a paid order is written down as an adjustment — which is why
     * the admin creates the line and the adjustment in one transaction.
     */
    public function paidSoFar(): float
    {
        return round($this->total() - $this->outstanding() + $this->owedBack(), 2);
    }

    /** Money is still to move over a change — in either direction. */
    public function hasOpenAdjustments(): bool
    {
        return $this->openAdjustments()->isNotEmpty();
    }

    /**
     * Whether a change to this order has to be settled with the customer at
     * all. Before he has paid anything, the order's own payment page still
     * asks for the whole of `total()`, so an edit needs no adjustment.
     */
    public function isPaidFor(): bool
    {
        return $this->payment_confirmed_at !== null;
    }

    /** Whether the customer asked for his box to be made before the others. */
    public function isRush(): bool
    {
        return (float) ($this->rush_fee ?? 0) > 0;
    }

    /** Where it goes, in one line: the address, the post office or the station. */
    public function deliverySummary(): ?string
    {
        return match ($this->delivery_type) {
            DeliveryMethod::POST => trim(($this->recipient_name ? $this->recipient_name . ', ' : '') . 'poçt indeksi ' . $this->postal_index),
            DeliveryMethod::METRO => 'Metro: ' . $this->metro_station,
            default => $this->delivery_address,
        };
    }
}
