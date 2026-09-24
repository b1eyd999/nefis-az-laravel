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
        $d = self::digits();

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

    public static function hours(): string
    {
        return trim((string) Setting::get(Setting::CONTACT_HOURS));
    }

    /** Keeps whatever the owner typed as digits with a leading plus. */
    public static function clean(?string $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? '' : '+' . $digits;
    }
}
