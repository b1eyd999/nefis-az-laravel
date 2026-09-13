<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('text_font_family')->nullable()->after('text_color');
            $table->string('text_font_file')->nullable()->after('text_font_family');
        });

        Schema::table('product_angles', function (Blueprint $table) {
            $table->string('text_font_family')->nullable()->after('text_color');
            $table->string('text_font_file')->nullable()->after('text_font_family');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['text_font_family', 'text_font_file']);
        });

        Schema::table('product_angles', function (Blueprint $table) {
            $table->dropColumn(['text_font_family', 'text_font_file']);
        });
    }
};
