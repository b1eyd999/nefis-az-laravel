<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The three pages that answer a visitor before he buys anything: how the
 * shop works, what people ask, and how to reach a person.
 *
 * All three were only sections on the front page, reachable by «#how» and
 * «#faq» — nothing to send anybody, nothing for a search engine to find, and
 * the footer linked to anchors that vanished the moment a visitor was on any
 * other page. They are pages now, and the front page reads its own two
 * sections from here, so there is one list the owner edits and not two.
 *
 * Kept the way the corporate page is kept: one JSON setting, every word in
 * Azerbaijani, and the Azerbaijani sentence is itself the translation key —
 * so a line the owner has not touched still reads in the visitor's language.
 */
class Info
{
    /** The wording, as the pages read until the owner changes it. */
    public const DEFAULTS = [
        'how_eyebrow' => 'Necə İşləyir',
        'how_title' => 'Üç Addımda Fərdi Hədiyyə',
        'how_lede' => 'Hər addım diqqətlə düşünülüb ki, xatirəniz ən nəfis formada sizə qaytarılsın.',
        'how_outro' => 'Sualınız qaldı? Bizə yazın — bir neçə dəqiqə içində cavab veririk.',

        'faq_eyebrow' => 'Suallar',
        'faq_title' => 'Tez-tez Soruşulan Suallar',
        'faq_lede' => 'Müştərilərimizin ən çox soruşduğu şeylər. Cavabı tapmasanız, bizə yazın.',

        'contact_eyebrow' => 'Əlaqə',
        'contact_title' => 'Bizimlə əlaqə',
        'contact_lede' => 'Zəng edin, WhatsApp-a yazın, Instagram-dan mesaj atın — necə rahatdırsa. '
            . 'Sifarişlə bağlı suallara iş saatları ərzində bir neçə dəqiqədə cavab veririk.',
        'contact_form_title' => 'Mesaj yazın',
        'contact_form_note' => 'Adınızı, nömrənizi və sualınızı yazın — özümüz zəng edək.',
        'contact_form_button' => 'Mesajı göndər',
        'contact_form_thanks' => 'Mesajınız bizə çatdı. Tezliklə sizinlə əlaqə saxlayacağıq.',
        'contact_where_title' => 'Bizi haradan tapmaq olar',
        'contact_where_note' => 'Mağazamız yoxdur — qutular əl ilə, sifarişlə hazırlanır. '
            . 'Hazır sifarişi özümüz çatdırırıq: Bakı daxilində qapıya, metroda görüşlə, '
            . 'ya da poçtla Azərbaycanın hər yerinə.',
    ];

    /**
     * How an order goes, as the front page has always told it.
     *
     * Three steps, because that is what the box really takes; the owner may
     * write more or fewer and the page follows.
     */
    public const STEPS = [
        ['title' => 'Dizaynı Seçin', 'text' => 'Kolleksiyadan xoşunuza gələn qutu dizaynını seçin.'],
        ['title' => 'Şəklinizi Yükləyin', 'text' => 'Öz şəklinizi və istədiyiniz mətni əlavə edib canlı önizləmə görün.'],
        ['title' => 'Sifariş Verin', 'text' => 'Ünvanı yazın, kartla ödəyin — biz hazırlayıb çatdıraq.'],
    ];

    /**
     * What people ask.
     *
     * These were written into the front page itself, and one of them said an
     * order needs signing up, which stopped being true the day the checkout
     * let a guest through. The owner edits them in the admin now.
     */
    public const FAQ = [
        [
            'q' => 'Necə sifariş verə bilərəm?',
            'a' => 'Kolleksiyadan dizayn seçin, şəklinizi yükləyin, səbətə əlavə edin və sifarişi tamamlayın. '
                . 'Hesab açmağa ehtiyac yoxdur — ad, nömrə və e-poçt kifayətdir.',
        ],
        [
            'q' => 'Hansı şokolad növləri mövcuddur?',
            'a' => 'Kinder, Milka, Alionka və digər premium brendlərin plitkaları ilə qutular hazırlayırıq. '
                . 'Qutunu düzəldərkən hansı şokoladı istədiyinizi özünüz seçirsiniz.',
        ],
        [
            'q' => 'Sifariş neçə günə hazır olur?',
            'a' => 'Qutular əl ilə hazırlanır. Sifariş verərkən ən tez hansı tarixi seçə biləcəyinizi '
                . 'səhifə özü göstərir; təcili lazımdırsa, növbədənkənar hazırlanma seçimi var.',
        ],
        [
            'q' => 'Bakı xaricinə çatdırılma varmı?',
            'a' => 'Bəli. Bakı daxilində qapıya gətiririk və ya metroda görüşürük, '
                . 'Azərbaycanın qalan yerlərinə poçtla göndəririk.',
        ],
        [
            'q' => 'Necə ödəniş edə bilərəm?',
            'a' => 'Kartla saytın özündə — ödəniş səhifəsi bankın səhifəsidir, kart məlumatları bizə gəlmir. '
                . 'Köçürmə ilə də ödəmək olar: hesab nömrəsini sifarişdən sonra göstəririk.',
        ],
        [
            'q' => 'Şəklim yaxşı çıxacaqmı?',
            'a' => 'Qutunu düzəldərkən şəkli elə orada görürsünüz — kiçik və ya tutqun şəkil olarsa, '
                . 'səhifə sizi xəbərdar edir. Çapdan əvvəl özümüz də bir daha baxırıq; '
                . 'problem olsa, sizə yazırıq.',
        ],
        [
            'q' => 'Fərdi sifarişi geri qaytara bilərəmmi?',
            'a' => 'Fərdi hazırlanan məhsullar geri qaytarılmır — qutu yalnız sizin şəkliniz üçün hazırlanır. '
                . 'Çatdırılma zamanı zədə olarsa, əvəz edirik.',
        ],
        [
            'q' => 'Hədiyyəni başqasına göndərə bilərəmmi?',
            'a' => 'Bəli. Çatdırılma ünvanına onun ünvanını yazın; qiymət etiketi qutuya qoyulmur. '
                . 'İstəsəniz, içinə polaroid məktub da əlavə edə bilərsiniz.',
        ],
    ];

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $saved = json_decode((string) Setting::get(Setting::INFO_PAGES), true);
        $saved = is_array($saved) ? $saved : [];

        $page = array_merge(self::DEFAULTS, array_filter(
            $saved,
            fn ($v, $k) => is_string($v) && trim($v) !== '' && array_key_exists($k, self::DEFAULTS),
            ARRAY_FILTER_USE_BOTH
        ));

        $page['steps'] = self::rows($saved['steps'] ?? null, self::STEPS, ['title']);
        $page['faq'] = self::rows($saved['faq'] ?? null, self::FAQ, ['q', 'a']);

        return $page;
    }

    /** One line of a page, in the language the visitor is reading. */
    public static function text(string $key): string
    {
        $value = self::all()[$key] ?? '';

        return is_string($value) ? __($value) : '';
    }

    /**
     * The questions, each already in the visitor's language.
     *
     * @return array<int, array{q: string, a: string}>
     */
    public static function faq(): array
    {
        return array_map(
            fn (array $row) => ['q' => __($row['q']), 'a' => __($row['a'])],
            self::all()['faq']
        );
    }

    /** @return array<int, array{title: string, text: string}> */
    public static function steps(): array
    {
        return array_map(
            fn (array $row) => ['title' => __($row['title']), 'text' => __($row['text'] ?? '')],
            self::all()['steps']
        );
    }

    /** What the admin page writes back. */
    public static function save(array $data): void
    {
        Setting::put(Setting::INFO_PAGES, (string) json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * A list the owner has edited, or the one the page was written with —
     * the latter only while he has never saved that list at all, so a list
     * he emptied on purpose stays empty and the page drops the section.
     *
     * @param  array<int, string>  $required
     */
    private static function rows(mixed $saved, array $fallback, array $required): array
    {
        if (! is_array($saved)) {
            return $fallback;
        }

        return array_values(array_filter($saved, function ($row) use ($required) {
            if (! is_array($row)) {
                return false;
            }
            foreach ($required as $field) {
                if (trim((string) ($row[$field] ?? '')) === '') {
                    return false;
                }
            }

            return true;
        }));
    }
}
