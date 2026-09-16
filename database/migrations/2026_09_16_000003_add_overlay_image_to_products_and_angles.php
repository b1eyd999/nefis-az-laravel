<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Artwork that must sit ABOVE the customer photo (frames, foreground props).
     * Designs where the photo simply fills a window leave this null.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('overlay_image')->nullable()->after('template_image');
        });

        Schema::table('product_angles', function (Blueprint $table) {
            $table->string('overlay_image')->nullable()->after('template_image');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('overlay_image');
        });

        Schema::table('product_angles', function (Blueprint $table) {
            $table->dropColumn('overlay_image');
        });
    }
};
