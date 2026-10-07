<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Setting;

/**
 * The small chocolates that go on a xonça.
 *
 * A xonça is carried to an engagement, a henna night or a wedding piled with
 * sweets, and what goes on it is not the shop's usual box: the chocolates are
 * smaller, there are many of them, and they are chosen to match the tray
 * rather than to be opened one at a time. They are their own designs and
 * their own page.
 *
 * Which designs belong here is decided the way every other shelf is — by the
 * category the owner files them under — so he adds a design to the page by
 * choosing "Xonça" on it, and nothing in code has to change. The wording is
 * one JSON setting he edits in the admin.
 */
class Xonca
{
    /** The shelf these designs are filed under. */
    public const CATEGORY = 'xonca';

    /** The page as it reads until the owner changes a line. */
    public const DEFAULTS = [
        'eyebrow' => 'Xonça və nişan günləri',
        'title' => 'Xonçaya kiçik şokoladlar',
        'lede' => 'Nişan, hinayaxdı və toy xonçası üçün adi qutudan kiçik şokoladlar: '
            . 'bir xonçaya onlarla yerləşir, hamısı eyni dizaynda, üstündə sizin adlarınız.',

        'size_label' => 'Ölçü',
        'size' => 'Adi qutudan kiçik — xonçada yan-yana düzülmək üçün',

        'how_title' => 'Necə sifariş olunur',
        'how_1' => 'Aşağıdan dizayn seçin.',
        'how_2' => 'Adları və tarixi yazın, şəkil istəyirsinizsə yükləyin.',
        'how_3' => 'Neçə ədəd lazım olduğunu seçin — xonçanın ölçüsündən asılıdır.',
        'how_4' => 'Sifarişi verin; Bakıda ünvana çatdırırıq, bölgələrə poçtla göndəririk.',

        'note_title' => 'Nə qədər vaxt lazımdır',
        'note' => 'Xonça sifarişləri çox sayda olur, ona görə onları adi sifarişdən əvvəl planlaşdırmaq lazımdır. '
            . 'Tarixdən ən azı bir həftə əvvəl yazın ki, hamısı vaxtında hazır olsun.',

        'empty' => 'Bu günlər üçün dizaynlar hazırlanır. Tezliklə burada olacaq.',
    ];

    public static function page(): array
    {
        $saved = json_decode((string) Setting::get(Setting::XONCA), true);

        return array_filter(is_array($saved) ? $saved : [], fn ($v) => filled($v)) + self::DEFAULTS;
    }

    public static function text(string $key): string
    {
        $page = self::page();

        return (string) ($page[$key] ?? self::DEFAULTS[$key] ?? '');
    }

    public static function save(array $words): void
    {
        $keep = [];
        foreach (self::DEFAULTS as $key => $_) {
            $value = trim((string) ($words[$key] ?? ''));
            if ($value !== '') {
                $keep[$key] = $value;
            }
        }

        Setting::put(Setting::XONCA, json_encode($keep, JSON_UNESCAPED_UNICODE));
    }

    /** The designs on the shelf, newest first, the way the catalogue orders them. */
    public static function designs()
    {
        return Product::where('is_active', true)
            ->where('category', self::CATEGORY)
            ->withCount('layers')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();
    }

    /** There is a page worth linking to only once a design is on it. */
    public static function ready(): bool
    {
        return Product::where('is_active', true)->where('category', self::CATEGORY)->exists();
    }
}
