<?php

namespace App\Models;

use App\Support\Cart;
use App\Support\Price;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A basket the shop filled for one customer, waiting behind one address.
 *
 * It is not an order: nothing is reserved, nothing is owed, and the customer
 * is free to change what is in it or to walk away. It is the design page's
 * work done for him, and from the moment he opens it the shop's ordinary
 * checkout is what he is using — same delivery, same promo codes, same
 * payment, same notice to Telegram.
 */
class CartHandoff extends Model
{
    /** How long a basket made today is worth opening. */
    public const DAYS = 21;

    protected $fillable = ['token', 'note', 'items', 'rush', 'created_by', 'opened_at', 'order_id', 'expires_at'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'rush' => 'boolean',
            'opened_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** A basket taken out of whoever's session it was built in. */
    public static function fromCart(array $items, bool $rush, ?string $note, ?int $by): self
    {
        return self::create([
            'token' => Str::random(40),
            'note' => $note ? mb_substr(trim($note), 0, 120) : null,
            'items' => array_values($items),
            'rush' => $rush,
            'created_by' => $by,
            'expires_at' => now()->addDays(self::DAYS),
        ]);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function maker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function url(): string
    {
        return route('cart.handoff.open', $this->token);
    }

    /**
     * Whether the address still opens anything. A basket that has become an
     * order is finished with — the customer who opens it again would be
     * ordering the same box twice without meaning to.
     */
    public function isOpen(): bool
    {
        return $this->order_id === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** Where it stands, for the owner reading a list of them. */
    public function state(): string
    {
        return match (true) {
            $this->order_id !== null => 'ordered',
            $this->expires_at !== null && $this->expires_at->isPast() => 'expired',
            $this->opened_at !== null => 'opened',
            default => 'sent',
        };
    }

    public function stateLabel(): string
    {
        return match ($this->state()) {
            'ordered' => 'Sifariş verildi',
            'expired' => 'Vaxtı keçib',
            'opened' => 'Açıb, sifariş verməyib',
            default => 'Göndərilib',
        };
    }

    /** How many pieces are in it, the way the cart counts them. */
    public function pieces(): int
    {
        return array_sum(array_map(fn ($i) => max(1, (int) ($i['quantity'] ?? 1)), $this->items ?? []));
    }

    /**
     * What it comes to, priced the way the cart prices it. A design that has
     * been taken off the shelf since is simply not counted, which is also
     * what happens when the basket is opened.
     */
    public function total(): float
    {
        $sum = 0.0;
        foreach ($this->items ?? [] as $item) {
            $product = isset($item['product_id']) ? Product::find($item['product_id']) : null;
            if ($product === null && ! Cart::isExtra($item)) {
                continue;
            }
            $sum += Cart::unitPrice($item, $product) * max(1, (int) ($item['quantity'] ?? 1));
        }

        return $sum;
    }

    public function totalLabel(): string
    {
        return Price::format($this->total());
    }
}
