<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers make their own live photos: the video they send waits on the
 * hosting only until it is handed to Yandex Disk, so a live photo may, for
 * a moment, have a local video and no Yandex link yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_photos', function (Blueprint $table) {
            $table->string('video_url', 500)->nullable()->change();
            if (! Schema::hasColumn('live_photos', 'video_path')) {
                $table->string('video_path')->nullable()->after('video_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('live_photos', function (Blueprint $table) {
            $table->dropColumn('video_path');
        });
    }
};
