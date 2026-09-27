<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The page that sells the small branded chocolate to companies: a café that
 * puts one on the saucer, a salon that leaves one at the reception desk.
 *
 * Every word, every picture and every figure on it belongs to the owner, so
 * all of it is kept here as one JSON setting he edits in the admin. What he
 * leaves alone keeps the wording the page was written with — and because the
 * Azerbaijani sentence is itself the translation key, an untouched line still
 * reads in the visitor's own language.
 */
class CorporatePage
{
    /** The page as it reads until the owner changes it. */
    public const DEFAULTS = [
        'eyebrow' => 'Şirkətlər üçün',
        'title' => 'Loqonuzla mini şokolad',
        'lede' => 'Qəhvənin yanında verilən kiçik şokolad: üstündə sizin loqonuz, arxasında QR kod. '
            . 'Qonaq onu cibinə qoyur, evdə açır — və markanız bir daha yadına düşür.',

        'size_label' => 'Ölçü',
        'size' => '4,5 × 9 × 1,5 sm — bir dilim şokolad',

        'min_qty' => '100',
        'min_qty_label' => 'Minimum sifariş',
        'min_qty_note' => 'Ən azı 100 ədəddən hazırlayırıq. Say artdıqca bir ədədin qiyməti aşağı düşür.',

        'lead_label' => 'Hazırlanma müddəti',
        'lead' => '5–7 iş günü',

        'faces_title' => 'Qutunun üstündə nə olur',
        'front_title' => 'Ön tərəf',
        'front_text' => 'Loqonuz, şüarınız və əlaqə nömrəniz — istədiyiniz rəngdə qutunun üstündə.',
        'back_title' => 'Arxa tərəf',
        'back_text' => 'QR kod: saytınıza, Instagram səhifənizə, menyuya və ya rəy formasına aparır.',

        'whom_title' => 'Kimlər sifariş edir',
        'try_title' => 'Loqonuzu yoxlayın',
        'try_note' => 'Loqonuzu yükləyin, qutunun rəngini seçin — necə görünəcəyini elə burada görürsünüz. '
            . 'Sifariş verməzdən əvvəl heç nəyə ehtiyac yoxdur.',

        'perks_title' => 'Nəyə görə işləyir',
        'gallery_title' => 'Necə görünür',

        'form_title' => 'Sifariş üçün müraciət',
        'form_note' => 'Loqonuzu və sayı yazın — qiyməti və nümunəni sizə göndərək.',
        'form_button' => 'Müraciət göndər',
        'form_thanks' => 'Müraciətiniz bizə çatdı. Tezliklə sizinlə əlaqə saxlayacağıq.',
    ];

    /** Who orders these, as the page lists them until the owner rewrites it. */
    public const WHOM = [
        ['icon' => '☕️', 'title' => 'Kafe və restoran', 'text' => 'Qəhvənin yanında, hesabın üstündə.'],
        ['icon' => '💇', 'title' => 'Gözəllik salonu', 'text' => 'Resepşnda, gözləyən qonaq üçün.'],
        ['icon' => '🦷', 'title' => 'Klinika və aptek', 'text' => 'Qəbuldan sonra kiçik diqqət.'],
        ['icon' => '🏨', 'title' => 'Otel', 'text' => 'Otaqda, yastığın üstündə.'],
        ['icon' => '🏢', 'title' => 'Ofis', 'text' => 'Qonaqlara və əməkdaşlara.'],
        ['icon' => '🎤', 'title' => 'Tədbir və konfrans', 'text' => 'Bеyc ilə birlikdə, iştirakçıya.'],
    ];

    /** Why a company bothers, in the owner's own words. */
    public const PERKS = [
        ['title' => 'Atılmayan reklam', 'text' => 'Vərəqə zibil qutusuna gedir, şokolad yeyilir — və qutu qalır.'],
        ['title' => 'QR kod real müştəri gətirir', 'text' => 'Arxadakı kod birbaşa menyunuza, saytınıza və ya Instagram-a aparır.'],
        ['title' => 'Tam sizin dizaynınız', 'text' => 'Loqo, rəng, şüar, nömrə — hamısı sizin. Bizim adımız qutuda yoxdur.'],
        ['title' => 'Bir ədədin qiyməti kiçikdir', 'text' => 'Hədiyyə kimi görünür, xərc kimi görünmür.'],
        ['title' => 'Nümunə əvvəlcədən', 'text' => 'Çapa getməzdən əvvəl hazır maketi göndəririk, siz təsdiqləyirsiniz.'],
        ['title' => 'Bakıya çatdırılma', 'text' => 'Hazır olanda özümüz gətiririk.'],
    ];

    /** The colours the box is offered in; the owner may add his own. */
    public const COLORS = [
        ['name' => 'Tünd göy', 'hex' => '#1B3A6B'],
        ['name' => 'Qara', 'hex' => '#15130F'],
        ['name' => 'Ağ', 'hex' => '#F7F3EC'],
        ['name' => 'Şokolad', 'hex' => '#3A2617'],
        ['name' => 'Yaşıl', 'hex' => '#1F5B43'],
        ['name' => 'Al qırmızı', 'hex' => '#A3231F'],
        ['name' => 'Narıncı', 'hex' => '#F0541F'],
        ['name' => 'Bej', 'hex' => '#E3D2B8'],
    ];

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $saved = json_decode((string) Setting::get(Setting::CORPORATE), true);
        $saved = is_array($saved) ? $saved : [];

        $page = array_merge(self::DEFAULTS, array_filter(
            $saved,
            fn ($v, $k) => is_string($v) && trim($v) !== '' && array_key_exists($k, self::DEFAULTS),
            ARRAY_FILTER_USE_BOTH
        ));

        $page['whom'] = self::rows($saved['whom'] ?? null, self::WHOM, ['title']);
        $page['perks'] = self::rows($saved['perks'] ?? null, self::PERKS, ['title']);
        $page['colors'] = self::rows($saved['colors'] ?? null, self::COLORS, ['name', 'hex']);
        // Photographs have no sensible default: an empty gallery simply does
        // not show, rather than promising pictures that are not there.
        $page['gallery'] = self::rows($saved['gallery'] ?? null, [], ['image']);

        return $page;
    }

    /** One line of the page, in the language the visitor is reading. */
    public static function text(string $key): string
    {
        $value = self::all()[$key] ?? '';

        return is_string($value) ? __($value) : '';
    }

    public static function minimum(): int
    {
        return max(1, (int) (self::all()['min_qty'] ?? 100));
    }

    /**
     * A list the owner has edited, or the one the page was written with.
     * Rows missing the fields that make them worth showing are dropped, so a
     * half-filled row in the admin cannot leave a blank card on the page.
     *
     * @param  array<int, string>  $required
     */
    private static function rows(mixed $saved, array $fallback, array $required): array
    {
        if (! is_array($saved)) {
            return $fallback;
        }

        $rows = array_values(array_filter($saved, function ($row) use ($required) {
            if (! is_array($row)) {
                return false;
            }
            foreach ($required as $field) {
                if (! isset($row[$field]) || trim((string) $row[$field]) === '') {
                    return false;
                }
            }

            return true;
        }));

        return $rows ?: $fallback;
    }
}
