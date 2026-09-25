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
    ];

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
        'materials_cost',
        // Which courier took it, and the message in the group he took it from.
        'courier_name',
        'courier_taken_at',
        'courier_chat_id',
        'courier_message_id',
        // Paying by transfer: which account the money goes to, and the receipt.
        'payment_account_id',
        'payment_receipt',
        'receipt_at',
        'payment_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_price' => 'float',
            'rush_fee' => 'float',
            'delivery_date' => 'date',
            'delivery_lat' => 'float',
            'delivery_lng' => 'float',
            'materials_cost' => 'float',
            'courier_taken_at' => 'datetime',
            'receipt_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
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

    public function receiptUrl(): ?string
    {
        return $this->payment_receipt ? \App\Support\Media::url($this->payment_receipt) : null;
    }

    protected static function booted(): void
    {
        // Cancelling puts the boxes' materials back in stock; bringing an
        // order back from cancelled takes them again.
        static::updated(function (Order $order) {
            if (! $order->wasChanged('status')) {
                return;
            }
            $was = $order->getOriginal('status');
            if ($order->status === 'cancelled' && $was !== 'cancelled') {
                Accounting::restore($order);
            } elseif ($was === 'cancelled' && $order->status !== 'cancelled') {
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

    public function total(): float
    {
        return $this->itemsTotal() + (float) ($this->delivery_price ?? 0) + (float) ($this->rush_fee ?? 0);
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
