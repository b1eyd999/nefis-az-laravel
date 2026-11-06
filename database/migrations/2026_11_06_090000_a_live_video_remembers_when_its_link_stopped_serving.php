<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a live photo's video stopped being reachable.
 *
 * All six of the shop's AR videos went at once: the files behind their
 * Yandex Disk links are gone — `DiskNotFoundError` for every one — and
 * nobody knew until a customer pointed a telephone at a box and got a black
 * screen. The site had deleted its own copies the moment the upload
 * succeeded, so there was nothing to fall back on and nothing to warn with.
 *
 * `live:check` asks Yandex about every published video once a day. The
 * moment one stops answering, the time is written here and the owner is told
 * on Telegram; when it answers again the column is cleared. Null means the
 * link was serving the last time anybody looked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_photos', function (Blueprint $table) {
            $table->timestamp('video_broken_at')->nullable()->after('video_path');
            // So a daily run can tell "never looked" from "looked, it was fine".
            $table->timestamp('video_checked_at')->nullable()->after('video_broken_at');
        });
    }

    public function down(): void
    {
        Schema::table('live_photos', function (Blueprint $table) {
            $table->dropColumn(['video_broken_at', 'video_checked_at']);
        });
    }
};
