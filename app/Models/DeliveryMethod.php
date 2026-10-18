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
     * Where the courier goes.
     *
     * Not a circle, and the reason is worth writing down: the owner asked to
     * drop Binəqədi while keeping Sabunçu and Suraxanı, and no circle can do
     * that. Binəqədi is 7.4 km from the centre, Sabunçu 7.7, Suraxanı 9.5 —
     * every circle that keeps the last two keeps the first. He was not
     * describing a distance at all; he was naming districts.
     *
     * So this is the outline of the eight districts he delivers to —
     * Səbail, Nəsimi, Nərimanov, Yasamal, Xətai, Nizami, Suraxanı,
     * Sabunçu — cut off at 20 km from the centre, because Sabunçu as a
     * district runs on to Maştağa and Bilgəh and that is the same «too far»
     * that the order from Buzovna was.
     *
     * Traced from the districts' real boundaries (OpenStreetMap relations
     * 11826939-11827250), rasterised at 170 m and simplified to a quarter
     * kilometre. It holds every one of the eight districts inside 20 km,
     * and crosses no more than 170 m into a neighbouring one.
     */
    public const DOOR_ZONE = [
        [40.2283, 49.8410], [40.2403, 49.7770], [40.2867, 49.7410], [40.2927, 49.7410], [40.3062, 49.7650],
        [40.3272, 49.7850], [40.3377, 49.7830], [40.3513, 49.7950], [40.3663, 49.7930], [40.3798, 49.7850],
        [40.3978, 49.7850], [40.4143, 49.7950], [40.4158, 49.8050], [40.4218, 49.8110], [40.4188, 49.8270],
        [40.4113, 49.8270], [40.4188, 49.8610], [40.4353, 49.8610], [40.4382, 49.8710], [40.4562, 49.8710],
        [40.4592, 49.8770], [40.4817, 49.8850], [40.4817, 49.8910], [40.4997, 49.8930], [40.4997, 49.9010],
        [40.4937, 49.9010], [40.4937, 49.9090], [40.5027, 49.9270], [40.5207, 49.9270], [40.5192, 49.8670],
        [40.5403, 49.8690], [40.5433, 49.8530], [40.5493, 49.8530], [40.5568, 49.7770], [40.5763, 49.7710],
        [40.5868, 49.8110], [40.5898, 49.8450], [40.5898, 49.9010], [40.5793, 49.9550], [40.5613, 50.0010],
        [40.5358, 50.0410], [40.5297, 50.0390], [40.5297, 50.0270], [40.5192, 50.0270], [40.5192, 50.0230],
        [40.5102, 50.0230], [40.5057, 50.0350], [40.5012, 50.0350], [40.4997, 50.0270], [40.4817, 50.0270],
        [40.4787, 50.0310], [40.4637, 50.0270], [40.4637, 50.0310], [40.4412, 50.0490], [40.4472, 50.0930],
        [40.4338, 50.0990], [40.4308, 50.1050], [40.3888, 50.1050], [40.3528, 50.0950], [40.3152, 50.0730],
        [40.2897, 50.0490], [40.2702, 50.0230], [40.2493, 49.9830], [40.2343, 49.9350], [40.2283, 49.8950]
    ];

    /** The zone as the owner has redrawn it, or the one above. */
    public static function doorZone(): array
    {
        $door = static::where('type', self::DOOR)->first();
        $set = $door?->options['zone'] ?? null;

        $points = [];
        foreach (is_array($set) ? $set : [] as $p) {
            $p = is_array($p) ? array_values($p) : [];
            if (count($p) === 2 && is_numeric($p[0]) && is_numeric($p[1])) {
                $points[] = [(float) $p[0], (float) $p[1]];
            }
        }

        // Fewer than three corners is not a shape. Falling back to the
        // default beats refusing the whole city over a mistyped line.
        return count($points) >= 3 ? $points : self::DOOR_ZONE;
    }

    /**
     * An extra limit in kilometres, off unless the owner sets one.
     *
     * The zone above already stops at 20 km, so this is only ever a
     * tightening — a number here shrinks what is drawn and what is
     * accepted, and nothing widens past the districts.
     */
    public static function doorRadiusKm(): float
    {
        $door = static::where('type', self::DOOR)->first();
        $set = $door?->options['radius_km'] ?? null;

        return is_numeric($set) && (float) $set > 0 ? (float) $set : 0.0;
    }

    /**
     * Will the shop drive to this point?
     *
     * Not «is it in Baku», which is the question it used to ask and the
     * reason the rule failed twice over: Buzovna IS Baku, forty minutes out
     * along the peninsula, and Binəqədi is Baku and nearer than half the
     * places he does deliver to. The answer is a shape, and this is the
     * point-in-polygon test for it.
     *
     * The rectangle stays as a first, cheap refusal: a point in another
     * country should not need sixty-five comparisons to be turned away.
     */
    public static function reaches(float $lat, float $lng): bool
    {
        $b = self::BAKU_BOUNDS;

        if ($lat < $b['south'] || $lat > $b['north'] || $lng < $b['west'] || $lng > $b['east']) {
            return false;
        }

        if (! self::inZone($lat, $lng)) {
            return false;
        }

        $limit = self::doorRadiusKm();

        return $limit <= 0 || self::kmFromCentre($lat, $lng) <= $limit;
    }

    /** Whether the point falls inside the delivery outline. */
    public static function inZone(float $lat, float $lng): bool
    {
        $zone = self::doorZone();
        $inside = false;

        for ($i = 0, $j = count($zone) - 1; $i < count($zone); $j = $i++) {
            [$yi, $xi] = $zone[$i];
            [$yj, $xj] = $zone[$j];

            if (($yi > $lat) !== ($yj > $lat)
                && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
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
