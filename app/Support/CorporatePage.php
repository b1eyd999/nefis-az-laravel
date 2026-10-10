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

        'back_shot' => 'Qutunun arxası: QR kod ağ fonda, altında ünvanınız.',

        /* A company with a designer of its own does not want our mock-up; it
           wants the measurements. The template says what the printer needs,
           and the finished file comes back through the same form. */
        'designer_check' => 'Dizaynı öz dizaynerimiz hazırlayacaq',
        'designer_note' => 'Şablonu yükləyin, dizayneriniz onun üstündə işləsin, hazır faylı burada bizə göndərin. '
            . 'Şablonda qutunun ölçüləri, kəsim xətti və QR kodun yeri göstərilib.',
        'designer_template' => 'Dizayn şablonunu yüklə',
        'designer_template_note' => 'JPG · A4 · 300 dpi — ölçülər və nümunə',
        'designer_field' => 'Hazır dizayn faylı',
        'designer_formats' => 'PDF, AI, EPS, PNG, JPG və ya ZIP. 20 MB-a qədər.',

        /* What a company actually pays, by the number it orders. The page
           has always said «say artdıqca bir ədədin qiyməti aşağı düşür» and
           then named no figure at all, so every enquiry began with somebody
           asking what it costs. The ladder is the owner's own and starts
           empty: a price invented here would be a price on a live page. */
        'ladder_title' => 'Qiymət — sayına görə',
        'ladder_note' => 'Bir ədədin qiyməti sifarişin sayından asılıdır. Dizayn və çap qiymətin içindədir; '
            . 'Bakıya çatdırılma bizdəndir.',
        'ladder_from_label' => 'ədəddən',
        'ladder_per_label' => 'bir ədəd',
        'ladder_pick' => 'Sayı yazın — qiyməti dərhal görün',

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

    /**
     * The photographs the box is shown in, and the four corners of its
     * printed face in each — as fractions of the frame, so the same numbers
     * hold whatever size the picture is served at.
     *
     * The scenes are real photographs (Pexels, whose licence allows this)
     * with a blank box montaged into them; the customer's own logo is warped
     * onto that face in his browser, so he sees his own mark on the counter
     * rather than ours.
     */
    public const SCENES = [
        [
            'image' => 'images/corporate/scene-cafe.jpg',
            'caption' => 'Qəhvənin yanında, kafedə',
            'corners' => [[0.57593, 0.44676], [0.69136, 0.40046], [0.8216, 0.58009], [0.70123, 0.62778]],
        ],
        [
            'image' => 'images/corporate/scene-reception.jpg',
            'caption' => 'Resepşnda, qonağın qarşısında',
            'corners' => [[0.45455, 0.42227], [0.5697, 0.42], [0.57697, 0.59591], [0.45939, 0.59818]],
        ],
        [
            'image' => 'images/corporate/scene-wood.jpg',
            'caption' => 'Masada, hesabla birlikdə',
            'corners' => [[0.70437, 0.40975], [0.85313, 0.41256], [0.84688, 0.64041], [0.695, 0.6376]],
        ],
    ];

    /** The sheet a company's own designer works on, and the back of the box. */
    public const TEMPLATE = 'images/corporate/dizayn-sablonu.jpg';

    public const BACK_SHOT = 'images/corporate/qutunun-arxasi.jpg';

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

    /**
     * Whether the shop takes company orders at all. Switched off, the page
     * is gone and so is its place in the menu — the same way the letters and
     * the live photos come and go.
     */
    public static function enabled(): bool
    {
        return Setting::get(Setting::CORPORATE_ENABLED) === '1';
    }

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

        /* The price ladder has no sensible default: a figure put here would
           go live as the shop's own price. Empty, the page simply does not
           show the block and the enquiry works as it always has. */
        $page['ladder'] = self::ladderRows($saved['ladder'] ?? null);

        $page['whom'] = self::rows($saved['whom'] ?? null, self::WHOM, ['title']);
        $page['perks'] = self::rows($saved['perks'] ?? null, self::PERKS, ['title']);
        $page['colors'] = self::rows($saved['colors'] ?? null, self::COLORS, ['name', 'hex']);
        // Photographs have no sensible default: an empty gallery simply does
        // not show, rather than promising pictures that are not there.
        $page['gallery'] = self::rows($saved['gallery'] ?? null, [], ['image']);
        $page['scenes'] = self::SCENES;

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
     * The price ladder, smallest number first.
     *
     * @return array<int, array{from: int, price: float}>
     */
    public static function ladder(): array
    {
        return self::all()['ladder'];
    }

    /**
     * What one piece costs at this number, or null while the owner has named
     * no prices.
     *
     * The highest step the number reaches — so 250 pieces against steps of
     * 100, 300 and 500 is priced at the 100 step, not at the 300 one. The
     * other reading would quote a discount nobody has earned.
     */
    public static function unitPriceFor(int $quantity): ?float
    {
        $price = null;
        foreach (self::ladder() as $step) {
            if ($quantity >= $step['from']) {
                $price = $step['price'];
            }
        }

        return $price;
    }

    /** What the whole order comes to at that number, or null. */
    public static function totalFor(int $quantity): ?float
    {
        $unit = self::unitPriceFor($quantity);

        return $unit === null ? null : round($unit * $quantity, 2);
    }

    /**
     * The ladder as the owner saved it: whole numbers, real prices, in order,
     * and one step per starting number.
     *
     * @return array<int, array{from: int, price: float}>
     */
    public static function ladderRows(mixed $saved): array
    {
        if (! is_array($saved)) {
            return [];
        }

        $steps = [];
        foreach ($saved as $row) {
            if (! is_array($row)) {
                continue;
            }
            $from = (int) ($row['from'] ?? 0);
            $price = round((float) ($row['price'] ?? 0), 2);
            if ($from < 1 || $price <= 0) {
                continue;
            }
            // Two steps from the same number: the last one wins, which is
            // what the owner sees himself doing as he edits the list.
            $steps[$from] = ['from' => $from, 'price' => $price];
        }

        ksort($steps);

        return array_values($steps);
    }

    /**
     * A list the owner has edited, or the one the page was written with —
     * the latter only while he has never saved the list at all: a list he
     * emptied on purpose stays empty, the page drops that section.
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

        return $rows;
    }
}
