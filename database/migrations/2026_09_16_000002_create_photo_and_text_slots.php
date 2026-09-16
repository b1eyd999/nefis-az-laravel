<?php

use App\Models\Product;
use App\Models\ProductAngle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_slots', function (Blueprint $table) {
            $table->id();
            $table->morphs('slotable');
            $table->string('label')->nullable();
            $table->integer('x')->default(0);
            $table->integer('y')->default(0);
            $table->unsignedInteger('width')->default(100);
            $table->unsignedInteger('height')->default(100);
            $table->integer('rotation')->default(0);
            $table->string('shape')->default('rectangle');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('text_slots', function (Blueprint $table) {
            $table->id();
            $table->morphs('slotable');
            $table->string('label')->nullable();
            $table->integer('x')->default(0);
            $table->integer('y')->default(0);
            $table->unsignedInteger('max_width')->default(300);
            $table->unsignedInteger('font_size')->default(32);
            $table->string('color', 7)->default('#3A2617');
            $table->string('align')->default('center');
            $table->string('font_family')->nullable();
            $table->string('font_file')->nullable();
            $table->string('placeholder')->nullable();
            $table->unsignedInteger('max_length')->default(60);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        foreach (Product::whereNotNull('template_image')->get() as $product) {
            $this->backfill($product);
        }

        foreach (ProductAngle::all() as $angle) {
            $this->backfill($angle);
        }
    }

    private function backfill(Product|ProductAngle $owner): void
    {
        $owner->photoSlots()->create([
            'label' => 'Şəkil',
            'x' => $owner->photo_area_x,
            'y' => $owner->photo_area_y,
            'width' => $owner->photo_area_width,
            'height' => $owner->photo_area_height,
            'rotation' => $owner->photo_area_rotation,
            'shape' => $owner->photo_area_shape ?: 'rectangle',
        ]);

        if (! $owner->allow_text) {
            return;
        }

        $owner->textSlots()->create([
            'label' => 'Mətn',
            'x' => $owner->text_x,
            'y' => $owner->text_y,
            'max_width' => $owner->text_max_width,
            'font_size' => $owner->text_font_size,
            'color' => $owner->text_color ?: '#3A2617',
            'align' => $owner->text_align ?: 'center',
            'font_family' => $owner->text_font_family,
            'font_file' => $owner->text_font_file,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('text_slots');
        Schema::dropIfExists('photo_slots');
    }
};
