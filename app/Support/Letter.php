<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * A Polaroid letter: an optional photo and optional words, one of them at
 * least. Sold inside a box or on its own, at the price the owner sets.
 */
class Letter
{
    /** The page's wording and the Polaroid's look, as the owner leaves them in the admin. */
    public const PAGE_DEFAULTS = [
        'menu' => 'Polaroid məktub',
        'eyebrow' => 'Qutunun içinə və ya ayrıca',
        'title' => 'Polaroid məktub',
        'lede' => 'Şəkliniz və bir neçə sözünüz, polaroid kimi çap edib qutunun içinə qoyuruq və ya ayrıca göndəririk.',
        'photo_label' => 'Şəkil seçin',
        'text_placeholder' => 'Məs. Səni çox sevirəm! Ad günün mübarək ❤',
        'hint' => 'Şəkil olmasa, mətn polaroidin içində yazılır.',
        'button' => 'Səbətə Əlavə Et',
        'note' => 'Dizayn seçəndə də məktubu birbaşa qutunun içinə əlavə edə bilərsiniz.',
        'box_label' => 'Qutunun içinə polaroid məktub qoy',
        'placeholder' => 'Sözləriniz burada',
        'frame' => '#FBFAF6',
        'ink' => '#2B2622',
        'tilt' => -2.5,
        'font' => 'Caveat',
        'filter' => 'polaroid',
    ];

    /** Handwriting faces the site's layout already loads. */
    public const FONTS = ['Caveat' => 'Caveat', 'Pacifico' => 'Pacifico', 'Great Vibes' => 'Great Vibes', 'Sacramento' => 'Sacramento'];

    public const FILTERS = [
        'polaroid' => 'Polaroid (isti rəng, künclər tünd)',
        'soft' => 'Yumşaq',
        'bw' => 'Ağ-qara',
        'none' => 'Effektsiz',
    ];

    /** @return array<string, mixed> */
    public static function page(): array
    {
        $saved = json_decode((string) Setting::get(Setting::LETTER_PAGE), true);

        return array_merge(self::PAGE_DEFAULTS, array_filter(is_array($saved) ? $saved : [], fn ($v) => $v !== null && $v !== ''));
    }

    public static function text(string $key): string
    {
        return (string) (self::page()[$key] ?? '');
    }

    /** The Polaroid's look as custom properties for its figure's style. */
    public static function style(): string
    {
        $p = self::page();
        $hex = fn ($v, $d) => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $v) ? $v : $d;
        $font = array_key_exists($p['font'], self::FONTS) ? $p['font'] : 'Caveat';

        return '--pol-frame:' . $hex($p['frame'], '#FBFAF6') . ';--pol-ink:' . $hex($p['ink'], '#2B2622')
            . ';--pol-tilt:' . max(-10, min(10, (float) $p['tilt'])) . 'deg;--pol-font:\'' . $font . '\'';
    }

    public static function filterClass(): string
    {
        $f = self::page()['filter'];

        return 'pol-f-' . (array_key_exists($f, self::FILTERS) ? $f : 'polaroid');
    }

    public static function enabled(): bool
    {
        return Setting::get(Setting::LETTER_ENABLED) === '1';
    }

    public static function price(): float
    {
        return round((float) Setting::get(Setting::LETTER_PRICE), 2);
    }

    public static function maxLength(): int
    {
        return max(20, (int) Setting::get(Setting::LETTER_MAX));
    }

    /** How big the handwriting is, by how much there is to write. */
    public static function sizeClass(?string $text): string
    {
        $n = mb_strlen((string) $text);

        return $n <= 40 ? 'pol-s' : ($n <= 110 ? 'pol-m' : 'pol-l');
    }

    /** Validation rules for the letter's fields under a prefix ("letter_"). */
    public static function rules(): array
    {
        return [
            'letter_text' => ['nullable', 'string', 'max:' . self::maxLength()],
            'letter_photo' => ['nullable', 'image', 'max:8192'],
        ];
    }

    public static function messages(): array
    {
        return [
            'letter_text.max' => 'Məktubun mətni ' . self::maxLength() . ' simvoldan uzun ola bilməz.',
            'letter_photo.image' => 'Məktubun şəkli şəkil faylı olmalıdır (JPG, PNG və s.).',
            'letter_photo.max' => 'Məktubun şəkli 8 MB-dan böyük ola bilməz.',
        ];
    }

    /**
     * The letter as the cart keeps it: its words, its photo (stored now) and
     * its price as it is today. Null when neither words nor photo came.
     */
    public static function fromRequest(Request $request): ?array
    {
        $text = trim(str_replace("\r\n", "\n", (string) $request->input('letter_text')));
        $photo = $request->file('letter_photo');
        if ($text === '' && ! $photo) {
            return null;
        }

        return [
            'text' => $text !== '' ? $text : null,
            'photo' => $photo ? $photo->store('cart-photos', 'public') : null,
            'price' => self::price(),
        ];
    }
}
