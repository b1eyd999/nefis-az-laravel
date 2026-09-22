<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live photos (augmented reality): a printed picture that, seen through a
 * phone's camera after scanning its QR code, plays a video over itself. The
 * customer may send the video with a box; the owner makes the live photo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('live_photos')) {
            Schema::create('live_photos', function (Blueprint $table) {
                $table->id();
                $table->string('code', 16)->unique();          // the QR code's link: /canli/{code}
                $table->string('title');
                $table->string('target_image');                // the picture as printed
                $table->string('target_mind')->nullable();     // what the camera looks for, made from it
                $table->string('video_url', 500);             // a Yandex Disk link: videos are not kept on the hosting
                $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedInteger('views')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'ar_video')) {
                $table->string('ar_video')->nullable()->after('letter_price');
                $table->decimal('ar_price', 8, 2)->nullable()->after('ar_video');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['ar_video', 'ar_price']);
        });
        Schema::dropIfExists('live_photos');
    }
};
