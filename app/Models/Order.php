<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

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
        'recipient_name',
        'postal_index',
        'metro_station',
        'delivery_lat',
        'delivery_lng',
    ];

    protected function casts(): array
    {
        return [
            'delivery_price' => 'float',
            'delivery_lat' => 'float',
            'delivery_lng' => 'float',
        ];
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
        return $this->itemsTotal() + (float) ($this->delivery_price ?? 0);
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
