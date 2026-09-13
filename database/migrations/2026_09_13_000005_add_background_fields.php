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
            $table->string('background_image')->nullable()->after('template_image');
            $table->unsignedInteger('background_width')->nullable()->after('background_image');
            $table->unsignedInteger('background_height')->nullable()->after('background_width');
            $table->integer('box_area_x')->nullable()->after('background_height');
            $table->integer('box_area_y')->nullable()->after('box_area_x');
            $table->unsignedInteger('box_area_width')->nullable()->after('box_area_y');
            $table->unsignedInteger('box_area_height')->nullable()->after('box_area_width');
            $table->integer('box_area_rotation')->nullable()->after('box_area_height');
        });

        Schema::table('product_angles', function (Blueprint $table) {
            $table->string('background_image')->nullable()->after('template_image');
            $table->unsignedInteger('background_width')->nullable()->after('background_image');
            $table->unsignedInteger('background_height')->nullable()->after('background_width');
            $table->integer('box_area_x')->nullable()->after('background_height');
            $table->integer('box_area_y')->nullable()->after('box_area_x');
            $table->unsignedInteger('box_area_width')->nullable()->after('box_area_y');
            $table->unsignedInteger('box_area_height')->nullable()->after('box_area_width');
            $table->integer('box_area_rotation')->nullable()->after('box_area_height');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['background_image', 'background_width', 'background_height', 'box_area_x', 'box_area_y', 'box_area_width', 'box_area_height', 'box_area_rotation']);
        });

        Schema::table('product_angles', function (Blueprint $table) {
            $table->dropColumn(['background_image', 'background_width', 'background_height', 'box_area_x', 'box_area_y', 'box_area_width', 'box_area_height', 'box_area_rotation']);
        });
    }
};
