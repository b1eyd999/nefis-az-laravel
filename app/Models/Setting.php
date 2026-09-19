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

    public const MAINTENANCE = 'maintenance';

    public const MAINTENANCE_MESSAGE = 'maintenance_message';

    /** JSON list of {name, percent}: how the net profit is shared out. */
    public const PROFIT_SHARES = 'profit_shares';

    /** What each setting is until the owner changes it. */
    public const DEFAULTS = [
        self::CHOCOLATE_MARKUP => '30',
        self::CHOCOLATE_FROM_SALE => '0',
        self::MAINTENANCE => '0',
        self::MAINTENANCE_MESSAGE => 'Saytda texniki işlər aparılır. Tezliklə qayıdacağıq!',
        self::PROFIT_SHARES => '[{"name":"Sahibkar","percent":33.34},{"name":"Menecer 1","percent":33.33},{"name":"Menecer 2","percent":33.33}]',
    ];

    /** @return array<int, array{name: string, percent: float}> */
    public static function profitShares(): array
    {
        return array_values(array_filter((array) json_decode((string) static::get(self::PROFIT_SHARES), true), fn ($s) => is_array($s) && isset($s['name'])));
    }

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
