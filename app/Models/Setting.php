<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A handful of site-wide values the owner sets in the admin, such as the
 * markup on chocolate. Read through a cache, since every page with a price
 * needs them.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public const CHOCOLATE_MARKUP = 'chocolate_markup';

    public const CHOCOLATE_FROM_SALE = 'chocolate_price_from_sale';

    /** What each setting is until the owner changes it. */
    public const DEFAULTS = [
        self::CHOCOLATE_MARKUP => '30',
        self::CHOCOLATE_FROM_SALE => '0',
    ];

    public static function get(string $key): ?string
    {
        return Cache::rememberForever('setting:' . $key, fn () => static::find($key)?->value ?? (self::DEFAULTS[$key] ?? null));
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]);
        Cache::forget('setting:' . $key);
    }
}
