<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * The shop's taxpayer number, as the owner gave it.
 *
 * It is written here rather than typed into the admin so the footer carries
 * it from the first minute after the deploy; the owner can change or clear it
 * at any time in «Sayt ayarları» → «Hüquqi məlumat», and what he writes there
 * wins from then on.
 *
 * Nothing secret: a VÖEN is on every invoice the shop issues and in the
 * state register, and the point of it here is to be printed publicly.
 */
return new class extends Migration
{
    private const VOEN = '1906837672';

    public function up(): void
    {
        if (blank(Setting::get(Setting::LEGAL_VOEN))) {
            Setting::put(Setting::LEGAL_VOEN, self::VOEN);
        }
    }

    public function down(): void
    {
        if (Setting::get(Setting::LEGAL_VOEN) === self::VOEN) {
            Setting::put(Setting::LEGAL_VOEN, '');
        }
    }
};
