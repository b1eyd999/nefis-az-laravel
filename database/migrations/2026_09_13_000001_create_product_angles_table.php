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
        Schema::dropIfExists('product_angles');

        Schema::create('product_angles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('template_image');
            $table->unsignedInteger('template_width')->default(1000);
            $table->unsignedInteger('template_height')->default(1000);
            $table->integer('photo_area_x')->default(0);
            $table->integer('photo_area_y')->default(0);
            $table->unsignedInteger('photo_area_width')->default(100);
            $table->unsignedInteger('photo_area_height')->default(100);
            $table->integer('photo_area_rotation')->default(0);
            $table->boolean('allow_text')->default(true);
            $table->unsignedInteger('text_x')->default(0);
            $table->unsignedInteger('text_y')->default(0);
            $table->unsignedInteger('text_max_width')->default(300);
            $table->unsignedInteger('text_font_size')->default(32);
            $table->string('text_color', 7)->default('#3A2617');
            $table->string('text_align')->default('center');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_angles');
    }
};
