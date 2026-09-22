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
