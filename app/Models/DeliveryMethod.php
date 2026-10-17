<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A way an order reaches the customer, priced by the owner in the admin.
 * The type decides what checkout asks for:
 *   door   the address (Baku only) and a phone number
 *   post   the recipient's name and surname, a phone number and the
 *          post office's index
 *   metro  the metro station and a phone number
 */
class DeliveryMethod extends Model
{
    use \App\Models\Concerns\Translatable;

    public const DOOR = 'door';

    public const POST = 'post';

    public const METRO = 'metro';

    public const TYPES = [
        self::DOOR => 'Qapıya (yalnız Bakı)',
        self::POST => 'Poçt',
        self::METRO => 'Metro',
    ];

    /** Baku's metro stations, as offered until the owner edits the list. */
    public const BAKU_METRO = [
        'İçərişəhər', 'Sahil', '28 May', 'Cəfər Cabbarlı', 'Gənclik', 'Nəriman Nərimanov', 'Bakmil', 'Ulduz', 'Koroğlu',
        'Qara Qarayev', 'Neftçilər', 'Xalqlar Dostluğu', 'Əhmədli', 'Həzi Aslanov', 'Xətai', 'Nizami',
        'Elmlər Akademiyası', 'İnşaatçılar', '20 Yanvar', 'Memar Əcəmi', 'Nəsimi', 'Azadlıq prospekti', 'Dərnəgül',
        'Avtovağzal', '8 Noyabr', 'Xocəsən',
    ];

    /** Where door delivery goes: Baku, the Absheron settlements included. */
    /**
     * The whole city as the city is actually drawn: Qaradağ down past
     * Səngəçal in the south-west, Xəzər out to Türkan and Zərə in the east,
     * and the Absheron settlements in the north. The old box stopped short of
     * them, so a customer standing in his own city was told he was not.
     */
    public const BAKU_BOUNDS = ['south' => 40.00, 'west' => 49.25, 'north' => 40.75, 'east' => 50.75];

    public const BAKU_CENTER = ['lat' => 40.4093, 'lng' => 49.8671];

    protected $fillable = [
        'i18n','type', 'name', 'description', 'price', 'is_active', 'sort_order', 'options'];

    /**
     * The basket from which the shop carries the delivery itself, or null
     * when the owner is not giving it away at all.
     */
    public static function freeFrom(): ?float
    {
        $from = (float) Setting::get(Setting::FREE_DELIVERY_FROM);

        return $from > 0 ? $from : null;
    }

    /**
     * Whether a basket worth this much travels free.
     *
     * The goods decide it, not the bill: counting the delivery in would make
     * the promise answer itself, and counting the rush fee in would let a
     * customer buy his way past the threshold with a fee rather than with a
     * box. The discount is already off by the time this is asked.
     */
    public static function freeOn(float $goods): bool
    {
        $from = self::freeFrom();

        return $from !== null && $goods + 0.001 >= $from;
    }

    /** The owner's Google Maps key, if they added one; else the map uses OpenStreetMap. */
    public static function googleMapsKey(): ?string
    {
        $door = static::where('type', self::DOOR)->first();

        return filled($door?->options['google_maps_key'] ?? null) ? trim($door->options['google_maps_key']) : null;
    }

    /**
     * How far from the centre the shop will carry a box to the door.
     *
     * Twenty kilometres, and the number was chosen by measuring rather than
     * guessed. From the centre: the airport is 16.5 km, Maştağa 18, Lökbatan
     * 15 — all still the city a courier crosses in a morning. Buzovna is
     * 24.3, Mərdəkan 24.8, Türkan 34, Zirə 39. Twenty is the widest line
     * that leaves the distant settlements out, so it refuses as little as
     * possible while answering the thing that went wrong: an order arrived
     * from Buzovna.
     *
     * The owner can move it in the admin — one courier, one city, and what
     * that is worth changes with the season.
     */
    public const DOOR_RADIUS_KM = 20;

    /** The limit as the owner has set it, or the default above. */
    public static function doorRadiusKm(): float
    {
        $door = static::where('type', self::DOOR)->first();
        $set = $door?->options['radius_km'] ?? null;

        return is_numeric($set) && (float) $set > 0 ? (float) $set : (float) self::DOOR_RADIUS_KM;
    }

    /**
     * Will the shop drive to this point?
     *
     * Not «is it in Baku», which is the question it used to ask and the
     * reason the rule failed: Buzovna IS Baku on paper, forty minutes out
     * along the peninsula. A courier cares about the distance, so that is
     * what is measured — from the centre, as the crow flies.
     *
     * The rectangle stays as a first, cheap refusal: a point in another
     * country should not need trigonometry to be turned away.
     */
    public static function reaches(float $lat, float $lng): bool
    {
        $b = self::BAKU_BOUNDS;

        if ($lat < $b['south'] || $lat > $b['north'] || $lng < $b['west'] || $lng > $b['east']) {
            return false;
        }

        return self::kmFromCentre($lat, $lng) <= self::doorRadiusKm();
    }

    /** Straight-line kilometres from the city centre. */
    public static function kmFromCentre(float $lat, float $lng): float
    {
        $c = self::BAKU_CENTER;
        $r = 6371.0;

        $dLat = deg2rad($lat - $c['lat']);
        $dLng = deg2rad($lng - $c['lng']);
        $h = sin($dLat / 2) ** 2
            + cos(deg2rad($c['lat'])) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($h)));
    }

    protected function casts(): array
    {
        return [
            'i18n' => 'array',
            'price' => 'float',
            'is_active' => 'boolean',
            'options' => 'array',
        ];
    }

    public function scopeShown(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /** The stations a metro delivery can go to. */
    public function stations(): array
    {
        return array_values(array_filter(array_map('trim', (array) ($this->options['stations'] ?? self::BAKU_METRO))));
    }

    /** A post-office index as written on letters: AZ and four digits. */
    public static function normalizeIndex(string $index): ?string
    {
        return preg_match('/^\s*(?:AZ)?\s*-?\s*(\d{4})\s*$/i', $index, $m) ? 'AZ' . $m[1] : null;
    }
}
