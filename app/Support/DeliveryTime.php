<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * When a box can be at the customer's door. Every order is made by hand, so
 * the shop needs a couple of days before it can hand anything over; the
 * customer picks the day and the part of the day from there on, and is told
 * the earliest date up front instead of finding out after paying.
 */
class DeliveryTime
{
    public const DEFAULT_SLOTS = ['10:00–14:00', '14:00–18:00', '18:00–21:00'];

    /** How many days the shop needs before a box is ready. */
    public static function leadDays(): int
    {
        return max(0, (int) Setting::get(Setting::DELIVERY_LEAD_DAYS));
    }

    /** The first day the customer may choose. */
    public static function earliest(): CarbonInterface
    {
        return Carbon::today()->addDays(self::leadDays());
    }

    /** How far ahead an order may be booked. */
    public static function latest(): CarbonInterface
    {
        return Carbon::today()->addDays(self::leadDays() + 60);
    }

    /** @return array<int, string> the parts of the day the owner delivers in */
    public static function slots(): array
    {
        $slots = array_values(array_filter(array_map(
            'trim',
            preg_split('/\r\n|\r|\n/', (string) Setting::get(Setting::DELIVERY_SLOTS)) ?: []
        )));

        return $slots ?: self::DEFAULT_SLOTS;
    }

    /** The day written the way people read it: "26 sentyabr, cümə". */
    public static function day(CarbonInterface|string|null $date): string
    {
        if (blank($date)) {
            return '';
        }
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        $months = ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avqust', 'sentyabr', 'oktyabr', 'noyabr', 'dekabr'];
        $days = ['bazar', 'bazar ertəsi', 'çərşənbə axşamı', 'çərşənbə', 'cümə axşamı', 'cümə', 'şənbə'];

        return $date->day . ' ' . $months[$date->month - 1] . ', ' . $days[$date->dayOfWeek];
    }

    /** What the customer is told before choosing. */
    public static function notice(): string
    {
        $days = self::leadDays();

        return $days === 0
            ? 'Sifarişi seçdiyiniz gün çatdırırıq.'
            : 'Hər qutu əl ilə hazırlanır: sifariş ' . $days . ' gündən sonra hazır olur. Ən tez ' . self::day(self::earliest()) . ' tarixinə çatdıra bilərik.';
    }
}
