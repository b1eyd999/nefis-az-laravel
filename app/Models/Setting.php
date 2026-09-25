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

    /** Which languages the site offers; the others are still being written. */
    public const SITE_LANGUAGES = 'site_languages';

    public const MAINTENANCE = 'maintenance';

    public const MAINTENANCE_MESSAGE = 'maintenance_message';

    /** JSON list of {name, percent}: how the net profit is shared out. */
    public const PROFIT_SHARES = 'profit_shares';

    /** JSON list of brand names shown first, in orange, in the customer's bar picker. */
    public const TOP_BRANDS = 'chocolate_top_brands';

    /** How many orders one payment account takes before the next one is offered. */
    public const PAYMENT_LIMIT = 'payment_limit';

    /** The span those orders are counted over, in hours. */
    public const PAYMENT_WINDOW_HOURS = 'payment_window_hours';

    /** What the customer is told on the payment page. */
    public const PAYMENT_NOTE = 'payment_note';

    /** The home page's slides: whether they turn by themselves, and how often (seconds). */
    public const HERO_AUTOPLAY = 'hero_autoplay';

    public const HERO_INTERVAL = 'hero_interval';

    /** Polaroid letters: on sale or not, their price, and how many characters they take. */
    public const LETTER_ENABLED = 'letter_enabled';

    public const LETTER_PRICE = 'letter_price';

    public const LETTER_MAX = 'letter_max';

    /** JSON: the letter page's wording and the Polaroid's look (see App\Support\Letter::PAGE_DEFAULTS). */
    public const LETTER_PAGE = 'letter_page';

    /** JSON: the live photo page's wording (see App\Support\LivePage::DEFAULTS). */
    public const LIVE_PAGE = 'live_page';

    /** Live photos (AR) sold to customers: on sale or not, and the price. */
    public const AR_ENABLED = 'ar_enabled';

    public const AR_PRICE = 'ar_price';

    /** The biggest video a customer may send, in MB (the hosting has its own ceiling). */
    public const AR_VIDEO_MB = 'ar_video_mb';

    /**
     * The owner's Yandex Disk, where customers' videos are put: an OAuth token
     * (kept encrypted — see App\Support\YandexDisk::token()) and the folder.
     */
    public const YANDEX_TOKEN = 'yandex_token';

    public const YANDEX_FOLDER = 'yandex_folder';

    /** Ownership codes from Google Search Console, Yandex Webmaster and Bing Webmaster Tools. */
    public const SEO_GOOGLE = 'seo_google_verification';

    public const SEO_YANDEX = 'seo_yandex_verification';

    public const SEO_BING = 'seo_bing_verification';

    /**
     * The owner's Telegram bot: its token (kept encrypted — see
     * App\Support\Telegram::token()) and the chat new orders are sent to.
     */
    public const TELEGRAM_TOKEN = 'telegram_token';

    public const TELEGRAM_CHAT = 'telegram_chat';

    /**
     * The couriers' own bot and the group it writes to: an order that is ready
     * is written there, not to the owner. The token is kept encrypted.
     */
    public const TELEGRAM_COURIER_TOKEN = 'telegram_courier_token';

    public const TELEGRAM_COURIER_CHAT = 'telegram_courier_chat';

    /** The secret in the address Telegram calls when a courier taps a message. */
    public const TELEGRAM_HOOK_SECRET = 'telegram_hook_secret';

    /** The weight of bar the boxes take — the owner widens or narrows it. */
    public const CHOCOLATE_MIN_G = 'chocolate_min_grams';

    public const CHOCOLATE_MAX_G = 'chocolate_max_grams';

    /**
     * When a box can be delivered: how many days the shop needs before an
     * order is ready, and the parts of the day it hands orders over in.
     */
    public const DELIVERY_LEAD_DAYS = 'delivery_lead_days';

    public const DELIVERY_SLOTS = 'delivery_slots';

    /** What a customer pays to have his order made before the others; 0 hides the offer. */
    public const RUSH_FEE = 'rush_fee';

    /** How customers reach the shop: the phone (WhatsApp too) and when it is answered. */
    public const CONTACT_PHONE = 'contact_phone';

    public const CONTACT_HOURS = 'contact_hours';

    /** Google Analytics measurement id (G-XXXXXXX); empty means no counter on the site. */
    public const SEO_ANALYTICS = 'seo_google_analytics';

    /** The chat on the site: its own bot, where it writes, and whether it is open. */
    public const CHAT_ENABLED = 'chat_enabled';

    public const CHAT_TOKEN = 'chat_token';

    public const CHAT_CHAT = 'chat_chat';

    public const CHAT_HOOK = 'chat_hook_secret';

    /** Whether a customer is written to by e-mail when his order moves on. */
    public const NOTIFY_EMAIL = 'notify_customer_email';

    /** What each setting is until the owner changes it. */
    public const DEFAULTS = [
        self::AR_ENABLED => '1',
        self::AR_PRICE => '5',
        self::AR_VIDEO_MB => '18',
        self::YANDEX_FOLDER => 'Nefis canlı şəkillər',
        self::LETTER_ENABLED => '1',
        self::LETTER_PRICE => '3',
        self::LETTER_MAX => '180',
        self::HERO_AUTOPLAY => '1',
        self::HERO_INTERVAL => '6',
        self::TOP_BRANDS => '["Milka","Alpen Gold"]',
        self::PAYMENT_LIMIT => '5',
        self::PAYMENT_WINDOW_HOURS => '24',
        self::PAYMENT_NOTE => 'Köçürmədən sonra çeki (qəbzi) buraya yükləyin. Ödənişi 1 saat ərzində yoxlayıb sifarişinizi təsdiqləyirik.',
        self::CHOCOLATE_MARKUP => '30',
        self::CHOCOLATE_FROM_SALE => '0',
        self::CONTACT_HOURS => 'Hər gün 10:00–20:00',
        self::CHOCOLATE_MIN_G => '90',
        self::CHOCOLATE_MAX_G => '105',
        self::DELIVERY_LEAD_DAYS => '2',
        self::RUSH_FEE => '3',
        self::DELIVERY_SLOTS => "10:00–14:00
14:00–18:00
18:00–21:00",
        self::NOTIFY_EMAIL => '1',
        self::CHAT_ENABLED => '0',
        self::SITE_LANGUAGES => 'az',
        self::MAINTENANCE => '0',
        self::MAINTENANCE_MESSAGE => 'Saytda texniki işlər aparılır. Tezliklə qayıdacağıq!',
        self::PROFIT_SHARES => '[{"name":"Sahibkar","percent":33.34},{"name":"Menecer 1","percent":33.33},{"name":"Menecer 2","percent":33.33}]',
    ];

    /** @return array<int, array{name: string, percent: float}> */
    public static function profitShares(): array
    {
        return array_values(array_filter((array) json_decode((string) static::get(self::PROFIT_SHARES), true), fn ($s) => is_array($s) && isset($s['name'])));
    }

    /** @return array<int, string> */
    public static function topBrands(): array
    {
        return array_values(array_filter((array) json_decode((string) static::get(self::TOP_BRANDS), true), 'is_string'));
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
