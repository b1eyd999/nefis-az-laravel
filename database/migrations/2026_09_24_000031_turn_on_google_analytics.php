<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * The owner's Google Analytics counter. Written once here so the site starts
 * counting on this deploy; from then on it is his to change or clear in
 * admin → Tənzimləmələr → Axtarış sistemləri.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (blank(Setting::get(Setting::SEO_ANALYTICS))) {
            Setting::put(Setting::SEO_ANALYTICS, 'G-PCXS029TE7');
        }
    }

    public function down(): void
    {
        Setting::put(Setting::SEO_ANALYTICS, '');
    }
};
