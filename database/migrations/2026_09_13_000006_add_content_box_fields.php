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
            $table->integer('content_x')->nullable()->after('box_area_rotation');
            $table->integer('content_y')->nullable()->after('content_x');
            $table->unsignedInteger('content_width')->nullable()->after('content_y');
            $table->unsignedInteger('content_height')->nullable()->after('content_width');
            $table->integer('content_rotation')->nullable()->after('content_height');
        });

        Schema::table('product_angles', function (Blueprint $table) {
            $table->integer('content_x')->nullable()->after('box_area_rotation');
            $table->integer('content_y')->nullable()->after('content_x');
            $table->unsignedInteger('content_width')->nullable()->after('content_y');
            $table->unsignedInteger('content_height')->nullable()->after('content_width');
            $table->integer('content_rotation')->nullable()->after('content_height');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['content_x', 'content_y', 'content_width', 'content_height', 'content_rotation']);
        });

        Schema::table('product_angles', function (Blueprint $table) {
            $table->dropColumn(['content_x', 'content_y', 'content_width', 'content_height', 'content_rotation']);
        });
    }
};
