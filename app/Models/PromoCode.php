<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A code a customer types at the checkout to take a percentage off.
 *
 * The discount is worked out on the goods only — not on the delivery or the
 * rush fee. Those are what the shop pays the courier and the workshop for,
 * and a code that ate into them would quietly take the money out of the
 * owner's own pocket rather than out of his margin.
 */
class PromoCode extends Model
{
    /** No O/0 or I/1: a code is read off a screen and typed by hand. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'code', 'percent', 'is_active', 'starts_at', 'ends_at',
        'max_uses', 'used_count', 'min_total', 'note',
    ];

    protected function casts(): array
    {
        return [
            'percent' => 'float',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'min_total' => 'float',
        ];
    }

    /** Codes are the same code however they were typed. */
    public function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = Str::upper(trim((string) $value));
    }

    public static function make(int $length = 8): string
    {
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /** The one this typing means, whatever its case or spacing. */
    public static function byCode(?string $typed): ?self
    {
        $code = Str::upper(trim((string) $typed));

        return $code === '' ? null : self::where('code', $code)->first();
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'));
    }

    public function hasRunOut(): bool
    {
        return $this->max_uses !== null && $this->used_count >= $this->max_uses;
    }

    public function hasExpired(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    public function hasNotStarted(): bool
    {
        return $this->starts_at !== null && $this->starts_at->isFuture();
    }

    public function isUsable(): bool
    {
        return $this->is_active && ! $this->hasExpired() && ! $this->hasNotStarted() && ! $this->hasRunOut();
    }

    /**
     * Why this code cannot be used on a basket of this size, in the
     * customer's own language — or null when it can.
     */
    public function refusal(float $goods): ?string
    {
        return match (true) {
            ! $this->is_active, $this->hasNotStarted() => __('Bu promokod işləmir.'),
            $this->hasExpired() => __('Bu promokodun vaxtı bitib.'),
            $this->hasRunOut() => __('Bu promokod artıq istifadə olunub.'),
            $this->min_total !== null && $this->min_total > 0 && $goods < $this->min_total => __(
                'Bu promokod :sum məbləğindən başlayan sifarişlər üçündür.',
                ['sum' => \App\Support\Price::format($this->min_total)]),
            default => null,
        };
    }

    /** What it takes off a basket worth this much, in manats. */
    public function discountOn(float $goods): float
    {
        if ($this->refusal($goods) !== null) {
            return 0.0;
        }

        // Never more than the goods themselves: a 100% code makes them free,
        // it does not start paying for the delivery.
        return round(min($goods, $goods * $this->percent / 100), 2);
    }

    public function used(): void
    {
        $this->increment('used_count');
    }

    /** Where it stands, for the owner's list. */
    public function stateLabel(): string
    {
        return match (true) {
            ! $this->is_active => 'Söndürülüb',
            $this->hasNotStarted() => 'Hələ başlamayıb',
            $this->hasExpired() => 'Vaxtı bitib',
            $this->hasRunOut() => 'Limit bitib',
            default => 'İşləyir',
        };
    }
}
