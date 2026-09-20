<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Somewhere for a customer to send the money: the owner's card, M10 wallet or
 * bank account. Orders are spread over them — once an account has taken the
 * day's share of orders, the next one is offered instead.
 */
class PaymentAccount extends Model
{
    public const CARD = 'card';

    public const M10 = 'm10';

    public const IBAN = 'iban';

    /** type => [what the customer sees, the line under it] */
    public const TYPES = [
        self::CARD => ['Kartdan-karta', 'Köçürmə'],
        self::M10 => ['M10', 'E-pul'],
        self::IBAN => ['Bank hesabı', 'IBAN'],
    ];

    protected $fillable = ['type', 'label', 'number', 'note', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }

    public function typeNote(): string
    {
        return self::TYPES[$this->type][1] ?? '';
    }

    /** "4172 1154 2555 3161" — a card reads in fours, a wallet or IBAN as it is. */
    public function formatted(): string
    {
        $raw = preg_replace('/\s+/', '', (string) $this->number);

        return $this->type === self::CARD && preg_match('/^\d{16,19}$/', $raw)
            ? trim(chunk_split($raw, 4, ' '))
            : (string) $this->number;
    }

    /** What the admin list shows, so a full number is not left on screen. */
    public function masked(): string
    {
        $raw = preg_replace('/\s+/', '', (string) $this->number);

        return strlen($raw) > 8
            ? substr($raw, 0, 4) . ' •••• ' . substr($raw, -4)
            : (string) $this->number;
    }

    public static function limit(): int
    {
        return max(1, (int) Setting::get(Setting::PAYMENT_LIMIT));
    }

    public static function windowHours(): int
    {
        return max(1, (int) Setting::get(Setting::PAYMENT_WINDOW_HOURS));
    }

    /** Orders sent to this account inside the window — the day's share. */
    public function used(): int
    {
        return $this->orders()
            ->where('created_at', '>=', now()->subHours(self::windowHours()))
            ->where('status', '!=', 'cancelled')
            ->count();
    }

    public function isFull(): bool
    {
        return $this->used() >= self::limit();
    }

    /**
     * The account a new order is sent to: the first one of its kind that has
     * not taken its share yet, else the least used, so orders never stop.
     */
    public static function pick(?string $type = null): ?self
    {
        $accounts = static::query()->active()->when($type, fn ($q) => $q->where('type', $type))->get();
        if ($accounts->isEmpty()) {
            return null;
        }

        $used = $accounts->mapWithKeys(fn (self $a) => [$a->id => $a->used()]);

        return $accounts->first(fn (self $a) => $used[$a->id] < self::limit())
            ?? $accounts->sortBy(fn (self $a) => $used[$a->id])->first();
    }

    /** The kinds the customer can choose from, each with the account on duty. */
    public static function offered(): array
    {
        $offered = [];
        foreach (array_keys(self::TYPES) as $type) {
            if ($account = self::pick($type)) {
                $offered[$type] = $account;
            }
        }

        return $offered;
    }
}
