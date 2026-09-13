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
            $table->string('photo_area_shape')->default('rectangle')->after('photo_area_rotation');
        });

        Schema::table('product_angles', function (Blueprint $table) {
            $table->string('photo_area_shape')->default('rectangle')->after('photo_area_rotation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('photo_area_shape');
        });

        Schema::table('product_angles', function (Blueprint $table) {
            $table->dropColumn('photo_area_shape');
        });
    }
};
