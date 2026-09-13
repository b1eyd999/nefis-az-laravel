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
        // A photo area rotated near ±90° can have a negative x/y when reconstructed
        // from its center point, so these can no longer be unsigned.
        Schema::table('products', function (Blueprint $table) {
            $table->integer('photo_area_x')->default(0)->change();
            $table->integer('photo_area_y')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('photo_area_x')->default(0)->change();
            $table->unsignedInteger('photo_area_y')->default(0)->change();
        });
    }
};
