<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * Letters to the customer are on from the start. The value is written as a
 * row, because Setting::get keeps its answer for ever in the cache and would
 * never look at a new default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::put(Setting::NOTIFY_EMAIL, '1');
        Cache::forget('setting:' . Setting::NOTIFY_EMAIL);
    }

    public function down(): void
    {
        Setting::put(Setting::NOTIFY_EMAIL, '0');
    }
};
