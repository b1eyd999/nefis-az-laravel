<?php

namespace App\Support;

/**
 * Tells a bar's brand from its name, so the customer can browse the bars by
 * brand. The shops write names every which way ("Kr/O Alenka", "Победа",
 * "BIanka"), hence patterns rather than exact words. The owner can always
 * set a bar's brand by hand in the admin; this only fills an empty one.
 */
class ChocolateBrand
{
    public const OTHER = 'Digər';

    /** Pattern (case-insensitive, Latin or Cyrillic) => the brand as shown. */
    private const BRANDS = [
        'alpen\s*gold|альпен' => 'Alpen Gold',
        'milka|милка' => 'Milka',
        'nestl[eé]|нестле' => 'Nestlé',
        'ferrero|rocher|raffaello' => 'Ferrero',
        'merci\b|мерси' => 'Merci',
        'ritte?r?\s*sport' => 'Ritter Sport',
        'excelcium' => 'Excelcium',
        'gutenberg' => 'Gutenberg',
        'lindt|lindor' => 'Lindt',
        'kinder' => 'Kinder',
        'schogetten' => 'Schogetten',
        'hamlet|chocolas' => 'Hamlet',
        'al[eë]nka|ал[её]нка' => 'Alenka',
        'babaevsk|бабаевск' => 'Babaevskiy',
        'pobeda|победа' => 'Pobeda',
        'roshen|рошен' => 'Roshen',
        'korkunov|коркунов' => 'Korkunov',
        'kommunarka|коммунарка' => 'Kommunarka',
        'spartak|спартак' => 'Spartak',
        'maitre\s*truffout' => 'Maitre Truffout',
        'das\s*exquisite' => 'Das Exquisite',
        'bian[ck]a|бьянка' => 'Bianca',
        'alpinio' => 'Alpinio',
        'baby\s*fox' => 'Baby Fox',
        'yummy' => 'Yummy',
        'ülker|ulker' => 'Ülker',
        'torku' => 'Torku',
        'elvan' => 'Elvan',
        'cadbury' => 'Cadbury',
        'dove\b' => 'Dove',
        'wawel' => 'Wawel',
        'heidi' => 'Heidi',
        'millennium|миллениум' => 'Millennium',
        'rossiya|россия' => 'Rossiya',
        'o[\'’]?zera|оз[её]рский' => "O'Zera",
    ];

    public static function detect(?string $name): ?string
    {
        foreach (self::BRANDS as $pattern => $brand) {
            if (preg_match('/' . $pattern . '/iu', (string) $name)) {
                return $brand;
            }
        }

        return null;
    }
}
