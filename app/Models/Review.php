<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * What a customer says once the box is in his hands.
 *
 * Tied to the order rather than to the person, so a review on this site is
 * always a review by somebody who bought something — and only once per
 * order. The owner reads it before anybody else sees it: a hand-made gift
 * goes wrong in ways better answered privately first.
 */
class Review extends Model
{
    /** The shop never asks for less than one star or more than five. */
    public const MOST = 5;

    protected $fillable = [
        'order_id', 'user_id', 'product_id', 'stars', 'body', 'photo', 'shown_name', 'locale',
        'approved_at', 'approved_by', 'reply',
    ];

    protected function casts(): array
    {
        return [
            'stars' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Only the ones the owner has let through. */
    public function scopeShown(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at');
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->whereNull('approved_at');
    }

    public function isShown(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Whose review it is, as a visitor reads it.
     *
     * His own first name, and nothing more: a surname on a public page is
     * more than anybody agreed to when he bought a box of chocolate. He may
     * ask for something else, or for nothing, and then it is the shop that
     * says so rather than leaving an empty line.
     */
    public function who(): string
    {
        if (filled($this->shown_name)) {
            return (string) $this->shown_name;
        }

        $first = Str::of((string) $this->user?->name)->trim()->explode(' ')->first();

        return filled($first) ? (string) $first : __('Müştəri');
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? Media::url($this->photo) : null;
    }

    /**
     * How the shop stands: the average, out of how many.
     *
     * Only approved reviews count — an average that moved as the owner
     * worked through his queue would be a different number every hour.
     *
     * @return array{average: float, count: int}
     */
    public static function standing(?int $productId = null): array
    {
        $query = self::shown();
        if ($productId !== null) {
            $query->where('product_id', $productId);
        }

        $count = $query->count();

        return [
            'average' => $count > 0 ? round((float) $query->avg('stars'), 1) : 0.0,
            'count' => $count,
        ];
    }

    /**
     * Whether this order may still be written about.
     *
     * Handed over, his own, and not written about already. The handover is
     * the whole point: before it there is nothing to say about the box.
     */
    public static function invited(Order $order, ?User $user): bool
    {
        return $user !== null
            && $order->user_id === $user->id
            && $order->delivered_at !== null
            && ! self::where('order_id', $order->id)->exists();
    }
}
