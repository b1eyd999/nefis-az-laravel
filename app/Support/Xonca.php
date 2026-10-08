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

    /**
     * How tall a band stands. Three steps rather than a number of pixels:
     * the owner is choosing how much of the screen a block takes, and any
     * figure he typed would also have to be right on a phone.
     */
    public const SIZES = [
        'alcaq' => 'Alçaq',
        'orta' => 'Orta',
        'hundur' => 'Hündür',
    ];

    /** The page as it reads until the owner changes a line. */
    public const DEFAULTS = [
        'eyebrow' => 'Xonça və nişan günləri',
        'title' => 'Xonçaya kiçik şokoladlar',
        'lede' => 'Nişan, hinayaxdı və toy xonçası üçün adi qutudan kiçik şokoladlar: '
            . 'bir xonçaya onlarla yerləşir, hamısı eyni dizaynda, üstündə sizin adlarınız.',

        'size_label' => 'Ölçü',
        'size' => 'Adi qutudan kiçik — xonçada yan-yana düzülmək üçün',

        /* Three wide bands down the page: a photograph on one half, the
           words and a button on the other, turning sides as they go. Every
           line of them is the owner's, and a band he leaves empty simply
           does not appear. */
        'block1_eyebrow' => 'Ölçü',
        'block1_title' => 'Adi qutudan kiçik',
        'block1_text' => 'Xonçaya onlarla şokolad düzülür, ona görə hər biri adi hədiyyə qutusundan kiçik olur. '
            . 'Yan-yana düzüləndə xonça dolu görünür, qonaq isə onu bir əli ilə götürür.',
        'block1_button' => 'Dizaynlara bax',
        'block1_url' => '/xonca',
        'block1_size' => 'orta',

        'block2_eyebrow' => 'Dizayn',
        'block2_title' => 'Hamısı eyni dizaynda',
        'block2_text' => 'Bütün xonça bir dizaynda hazırlanır: iki ad, nişan və ya toy tarixi, istəsəniz şəkil. '
            . 'Süfrədə onlar bir dəst kimi oxunur, ayrı-ayrı hədiyyələr kimi yox.',
        'block2_button' => '',
        'block2_url' => '',
        'block2_size' => 'orta',

        'block3_eyebrow' => 'Say',
        'block3_title' => 'Sayı xonçanın ölçüsündən asılıdır',
        'block3_text' => 'Kiçik xonçaya 20–30, böyüyünə 50-dən çox şokolad gedir. '
            . 'Dəqiq bilmirsinizsə, xonçanın ölçüsünü yazın — birlikdə hesablayaq.',
        'block3_button' => 'Bizə yazın',
        'block3_url' => '',
        'block3_size' => 'orta',

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

    /** The raw stored value of one key, pictures included. */
    public static function raw(string $key): ?string
    {
        $saved = json_decode((string) Setting::get(Setting::XONCA), true);

        return is_array($saved) ? ($saved[$key] ?? null) : null;
    }

    /**
     * The picture on one of the three blocks, or null.
     *
     * Kept apart from the words: DEFAULTS is the wording the page reads with
     * until the owner writes his own, and there is no sensible default
     * photograph — an empty block simply shows its words.
     */
    public static function image(int $n): ?string
    {
        $saved = json_decode((string) Setting::get(Setting::XONCA), true);
        $path = is_array($saved) ? ($saved['block' . $n . '_image'] ?? null) : null;

        return filled($path) ? \App\Support\Media::url($path) : null;
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

        // The pictures travel with the words but are not words themselves.
        foreach ([1, 2, 3] as $n) {
            $path = $words['block' . $n . '_image'] ?? null;
            $path = is_array($path) ? reset($path) : $path;
            if (filled($path)) {
                $keep['block' . $n . '_image'] = (string) $path;
            }
        }

        Setting::put(Setting::XONCA, json_encode($keep, JSON_UNESCAPED_UNICODE));
    }

    /** One band's height, always one the sheet knows about. */
    public static function size(int $n): string
    {
        $size = (string) (self::page()['block' . $n . '_size'] ?? 'orta');

        return isset(self::SIZES[$size]) ? $size : 'orta';
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
