<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The strip that asks whether Google Analytics may run.
 *
 * It is shown only when there is something to decide. Everything else this
 * site puts on a visitor's machine is needed for the shop to work at all —
 * the session that holds his basket, the token that stops a form being
 * submitted from another site, the remembered dark theme — and asking
 * permission for those would be theatre: refusing them means no basket.
 *
 * Google Analytics is the one thing a visitor can genuinely do without, so
 * it is the one thing the strip asks about. With no measurement id saved
 * there is nothing optional running and no strip is shown.
 */
class CookieNotice
{
    /** What the visitor's answer is called in his browser. */
    public const KEY = 'nefis-cookies';

    public static function enabled(): bool
    {
        if (Setting::get(Setting::COOKIE_NOTICE) !== '1') {
            return false;
        }

        // Nothing optional to run, nothing to ask about.
        return Seo::measurementId(Setting::get(Setting::SEO_ANALYTICS)) !== '' && ! app()->isLocal();
    }
}
