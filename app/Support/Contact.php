<?php

namespace App\Support;

use App\Models\Setting;

/**
 * How customers reach the shop: the owner's phone, which is also the
 * WhatsApp number, and when he answers. Set in admin → Tənzimləmələr.
 */
class Contact
{
    public const INSTAGRAM = Seo::INSTAGRAM;

    /** Digits only, with the country code: 994992308050. */
    public static function digits(): string
    {
        return preg_replace('/\D/', '', (string) Setting::get(Setting::CONTACT_PHONE));
    }

    public static function has(): bool
    {
        return strlen(self::digits()) >= 9;
    }

    /** What a phone dials: +994992308050. */
    public static function dial(): string
    {
        return '+' . self::digits();
    }

    /** What people read: +994 99 230 80 50. */
    public static function display(): string
    {
        return self::pretty(self::digits());
    }

    /** The one place a number is spaced out, so both are spaced alike. */
    private static function pretty(string $d): string
    {
        // Azerbaijani numbers read as +994 XX XXX XX XX; anything else stays whole.
        if (strlen($d) === 12 && str_starts_with($d, '994')) {
            return sprintf('+994 %s %s %s %s', substr($d, 3, 2), substr($d, 5, 3), substr($d, 8, 2), substr($d, 10, 2));
        }

        return '+' . $d;
    }

    public static function whatsapp(): string
    {
        return 'https://wa.me/' . self::digits();
    }

    /* ——— the second number ———
       Written down beside the first and answered the same way. Everything
       below mirrors the four above; nothing else in the shop changes,
       because the main number is still the one a button or a search result
       carries when it can only carry one. */

    public static function digits2(): string
    {
        return preg_replace('/\D/', '', (string) Setting::get(Setting::CONTACT_PHONE_2));
    }

    public static function has2(): bool
    {
        // And never the same number twice: a shop that lists one line as two
        // looks careless, and the second row would link to the first.
        return strlen(self::digits2()) >= 9 && self::digits2() !== self::digits();
    }

    public static function dial2(): string
    {
        return '+' . self::digits2();
    }

    public static function display2(): string
    {
        return self::pretty(self::digits2());
    }

    public static function whatsapp2(): string
    {
        return 'https://wa.me/' . self::digits2();
    }

    public static function hours(): string
    {
        return trim((string) Setting::get(Setting::CONTACT_HOURS));
    }

    /**
     * A customer's Azerbaijani number, written the way the country writes it:
     * +994 55 555 55 55. Accepts it typed with the code, with a leading zero
     * or with nothing at all, and returns null when it is not a number at
     * all, so validation has something to answer.
     */
    public static function az(?string $value): ?string
    {
        $d = preg_replace('/\D/', '', (string) $value);

        if (strlen($d) === 12 && str_starts_with($d, '994')) {
            $d = substr($d, 3);
        } elseif (strlen($d) === 10 && str_starts_with($d, '0')) {
            $d = substr($d, 1);
        }

        return strlen($d) === 9
            ? sprintf('+994 %s %s %s %s', substr($d, 0, 2), substr($d, 2, 3), substr($d, 5, 2), substr($d, 7, 2))
            : null;
    }

    /**
     * The nine digits that make an Azerbaijani number itself, whatever it
     * was typed with — +994, a leading zero, spaces, brackets or nothing.
     * Two numbers are the same number when these match.
     */
    public static function key(?string $value): ?string
    {
        $d = preg_replace('/\D/', '', (string) $value);

        return strlen((string) $d) >= 9 ? substr((string) $d, -9) : null;
    }

    /** Keeps whatever the owner typed as digits with a leading plus. */
    public static function clean(?string $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? '' : '+' . $digits;
    }
}
