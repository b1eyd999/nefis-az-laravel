<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change made to an order after the customer had already paid for it.
 *
 * A charge is money he still owes and can pay from a link of its own; a
 * refund is money the shop owes him, which the owner gives back by hand in
 * the ePoint cabinet — the gateway has no refund call — and ticks off here.
 */
class OrderAdjustment extends Model
{
    public const CHARGE = 'charge';

    public const REFUND = 'refund';

    public const WAITING = 'waiting';

    /** A transfer receipt has been sent and the owner has not looked yet. */
    public const CHECK = 'check';

    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::WAITING => 'Gözləyir',
        self::CHECK => 'Çek yoxlanılır',
        self::PAID => 'Ödənilib',
        self::CANCELLED => 'Ləğv edildi',
    ];

    protected $fillable = [
        'kind',
        'amount',
        'reason',
        'status',
        'payment_method',
        'epoint_ref',
        'epoint_transaction',
        'payment_asked_for',
        'payment_started_at',
        'payment_confirmed_at',
        'payment_receipt',
        'receipt_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'payment_asked_for' => 'float',
            'payment_started_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'receipt_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCharge(): bool
    {
        return $this->kind === self::CHARGE;
    }

    public function isSettled(): bool
    {
        return in_array($this->status, [self::PAID, self::CANCELLED], true);
    }

    /** Still waiting for money to move, one way or the other. */
    public function isOpen(): bool
    {
        return ! $this->isSettled();
    }

    /**
     * The customer is at the bank, or has just come back and the bank's own
     * message is still on its way. The same guard the order itself uses, for
     * the same reason: asking him to pay again now is asking for a second
     * charge.
     */
    public function paymentInFlight(): bool
    {
        return $this->payment_started_at !== null
            && $this->payment_confirmed_at === null
            && $this->payment_started_at->gt(now()->subMinutes(\App\Support\Epoint::IN_FLIGHT_MINUTES));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function receiptUrl(): ?string
    {
        return $this->payment_receipt ? \App\Support\Media::url($this->payment_receipt) : null;
    }

    /** What it does to the order's money: a charge adds, a refund takes away. */
    public function signed(): float
    {
        return $this->isCharge() ? (float) $this->amount : -(float) $this->amount;
    }
}
