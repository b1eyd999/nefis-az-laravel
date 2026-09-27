<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Russian and English go live.
 *
 * They have been reachable by address all along, unpublished, so the pages
 * could be looked at while they were being written. Everything is written
 * now — the interface, every design, and a gift page in all three languages —
 * so the switcher shows them, hreflang names them and the sitemap carries
 * them.
 *
 * Only if the owner has not already decided this himself: if he has set the
 * languages by hand, his answer stands.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = (string) Setting::get(Setting::SITE_LANGUAGES);

        if (trim($now) === '' || trim($now) === 'az') {
            Setting::put(Setting::SITE_LANGUAGES, 'az,ru,en');
        }
    }

    public function down(): void
    {
        Setting::put(Setting::SITE_LANGUAGES, 'az');
    }
};
