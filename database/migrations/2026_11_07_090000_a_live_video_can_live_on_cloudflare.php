<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where in Cloudflare R2 a live photo's video is kept.
 *
 * Three places can hold it now, and the page reads them in this order:
 * R2 first, the hosting second, the Yandex link last. Yandex is the one
 * that failed — somebody else's allowance, spent without warning, six
 * printed boxes dark at once — so it keeps the archive and answers nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_photos', function (Blueprint $table) {
            // The object's name inside the bucket, e.g. «live/bnjmoc7w.mp4».
            $table->string('video_r2')->nullable()->after('video_path');
        });
    }

    public function down(): void
    {
        Schema::table('live_photos', function (Blueprint $table) {
            $table->dropColumn('video_r2');
        });
    }
};
