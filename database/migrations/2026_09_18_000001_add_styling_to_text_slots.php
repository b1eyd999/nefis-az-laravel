<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The captions are no longer baked into the artwork, so the browser has to
 * reproduce how the designer styled them: tilted, outlined, shadowed, and in
 * the weight the font file actually carries.
 *
 * The styling columns stay null on older slots, which keeps them rendering
 * exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('text_slots', function (Blueprint $table) {
            $table->integer('rotation')->default(0)->after('align');
            $table->unsignedSmallInteger('font_weight')->nullable()->after('font_file');
            $table->string('stroke_color', 9)->nullable()->after('font_weight');
            $table->decimal('stroke_width', 6, 2)->nullable()->after('stroke_color');
            $table->string('shadow_color', 9)->nullable()->after('stroke_width');
            $table->unsignedSmallInteger('shadow_blur')->default(0)->after('shadow_color');
            $table->integer('shadow_x')->default(0)->after('shadow_blur');
            $table->integer('shadow_y')->default(0)->after('shadow_x');
            // Slots sharing a key are one field for the customer: a name the
            // design repeats down a strip is typed once.
            $table->string('link_key', 60)->nullable()->after('shadow_y');
        });
    }

    public function down(): void
    {
        Schema::table('text_slots', function (Blueprint $table) {
            $table->dropColumn([
                'rotation', 'font_weight', 'stroke_color', 'stroke_width',
                'shadow_color', 'shadow_blur', 'shadow_x', 'shadow_y', 'link_key',
            ]);
        });
    }
};
