<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * Live photos go on sale at 5 ₼ (the owner's word, 2026-09-22). Settings are
 * cached for good, so the old default ("off") would outlive the new one;
 * the switch is written down and the cached values are dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Setting::whereKey(Setting::AR_ENABLED)->exists()) {
            Setting::put(Setting::AR_ENABLED, true);
        }
        foreach ([Setting::AR_ENABLED, Setting::AR_PRICE, Setting::YANDEX_FOLDER] as $key) {
            Cache::forget('setting:' . $key);
        }
    }

    public function down(): void
    {
        //
    }
};
