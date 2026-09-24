<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * The video's size limit moves from the code into the admin. Written as a row
 * because Setting::get keeps its answer for ever in the cache and would never
 * look at a new default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::put(Setting::AR_VIDEO_MB, '18');
        Cache::forget('setting:' . Setting::AR_VIDEO_MB);
    }

    public function down(): void
    {
        Setting::where('key', Setting::AR_VIDEO_MB)->delete();
        Cache::forget('setting:' . Setting::AR_VIDEO_MB);
    }
};
